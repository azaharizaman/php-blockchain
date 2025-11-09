# Reliability Components

This directory contains components for building resilient blockchain integrations with automatic retry and rate limiting capabilities.

## Overview

The Reliability components provide:
- **RetryPolicy**: Exponential backoff with jitter for handling transient failures
- **RateLimiter**: Token-bucket algorithm for client-side rate limiting
- **CircuitBreaker**: Circuit breaker pattern to prevent cascading failures
- **Bulkhead**: Bulkhead isolation pattern for limiting concurrent operations
- **ReliableGuzzleAdapter**: HTTP adapter combining both policies

## Components

### RetryPolicy

Implements exponential backoff with configurable parameters:

```php
use Blockchain\Reliability\RetryPolicy;

$policy = new RetryPolicy(
    maxAttempts: 3,           // Maximum number of attempts
    baseDelayMs: 100,         // Base delay in milliseconds
    backoffMultiplier: 2.0,   // Exponential backoff multiplier
    jitterMs: 50,             // Random jitter to add
    maxDelayMs: 30000         // Maximum delay cap
);

// Execute operation with retry
$result = $policy->execute(
    fn() => $httpClient->get('/api/data'),
    [\GuzzleHttp\Exception\ConnectException::class]
);
```

**Features:**
- Exponential backoff: Each retry waits exponentially longer
- Jitter: Adds randomness to prevent thundering herd
- Maximum delay cap: Prevents excessive wait times
- Configurable retry exceptions: Only retry specific error types
- Testable: Override `delay()` method for deterministic tests

### RateLimiter

Implements token-bucket algorithm for rate limiting:

```php
use Blockchain\Reliability\RateLimiter;

$limiter = new RateLimiter(
    requestsPerSecond: 10.0,  // Sustained rate limit
    bucketCapacity: 20        // Burst capacity
);

// Blocking acquire (waits for token)
$limiter->acquire();
// Make your request...

// Non-blocking try acquire
if ($limiter->tryAcquire()) {
    // Make your request...
} else {
    // Rate limit exceeded
}
```

**Features:**
- Token-bucket algorithm: Smooth rate limiting with burst support
- Fractional rates: Support rates like 0.5 requests/second
- Burst capacity: Handle temporary traffic spikes
- Blocking and non-blocking modes
- Validation: Prevents requesting more tokens than bucket capacity
- Testable: Override `getCurrentTime()` and `delay()` for deterministic tests

**Important:** You cannot acquire more tokens than the bucket capacity in a single call. 
If you need to acquire more tokens, either increase the bucket capacity or split into 
multiple acquire calls.

### CircuitBreaker

Implements circuit breaker pattern to prevent cascading failures:

```php
use Blockchain\Reliability\CircuitBreaker;

$breaker = new CircuitBreaker(
    failureThreshold: 5,      // Failures to open circuit
    windowSizeSeconds: 60,    // Time window for failures
    cooldownSeconds: 30,      // Cooldown before retry
    successThreshold: 2       // Successes to close from half-open
);

// Execute operation with circuit breaker protection
try {
    $result = $breaker->call(fn() => $this->makeRpcCall());
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, fail fast
}
```

**Features:**
- State machine: Closed, Open, Half-Open states
- Sliding window: Tracks failures within time window
- Automatic recovery: Transitions to half-open after cooldown
- Force open: Manual circuit opening for maintenance
- Fail fast: Immediate failure when circuit is open
- Testable: Override `getCurrentTime()` for deterministic tests

**States:**
- **Closed**: Normal operation, requests pass through
- **Open**: Circuit tripped, requests fail immediately
- **Half-Open**: Testing if service recovered

### Bulkhead

Implements bulkhead isolation pattern for limiting concurrent operations:

```php
use Blockchain\Reliability\Bulkhead;

$bulkhead = new Bulkhead(
    maxConcurrent: 10,        // Max concurrent operations
    maxQueueSize: 0,          // Queue size (0 = no queue)
    queueTimeoutSeconds: 30   // Queue timeout
);

// Execute operation with concurrency limit
try {
    $result = $bulkhead->execute(fn() => $this->processRequest());
} catch (BulkheadFullException $e) {
    // Concurrency limit reached
}
```

**Features:**
- Concurrency limiting: Caps simultaneous operations
- Resource isolation: Prevents resource exhaustion
- Queue support: Reserved for future implementation (currently rejects when at capacity)
- Statistics: Real-time utilization metrics
- Manual control: Acquire/release slots explicitly
- Concurrency-aware design: Designed for safe usage in typical PHP environments. For true thread/process safety in multi-threaded or multi-process environments, additional synchronization mechanisms are required.

### ReliableGuzzleAdapter

Combines RetryPolicy and RateLimiter with GuzzleAdapter:

```php
use Blockchain\Transport\ReliableGuzzleAdapter;
use Blockchain\Reliability\RetryPolicy;
use Blockchain\Reliability\RateLimiter;

$adapter = new ReliableGuzzleAdapter(
    retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelayMs: 100),
    rateLimiter: new RateLimiter(requestsPerSecond: 10.0)
);

// Use like normal GuzzleAdapter - retry and rate limiting are automatic
$result = $adapter->post('https://api.example.com/rpc', [
    'jsonrpc' => '2.0',
    'method' => 'eth_blockNumber',
    'params' => [],
    'id' => 1
]);
```

**Features:**
- Transparent retry and rate limiting
- Compatible with existing GuzzleAdapter code
- Configurable policies per adapter instance
- Automatic handling of retryable exceptions

## Usage Examples

### Basic Retry

```php
$policy = new RetryPolicy(maxAttempts: 3);

try {
    $result = $policy->execute(
        fn() => $this->makeRpcCall(),
        [\RuntimeException::class]
    );
} catch (\Throwable $e) {
    // All retries failed
}
```

### Basic Rate Limiting

```php
$limiter = new RateLimiter(requestsPerSecond: 5.0);

foreach ($requests as $request) {
    $limiter->acquire(); // Wait if necessary
    $this->processRequest($request);
}
```

### Circuit Breaker Usage

```php
$breaker = new CircuitBreaker(
    failureThreshold: 5,
    windowSizeSeconds: 60,
    cooldownSeconds: 30
);

// Protect service calls
try {
    $result = $breaker->call(fn() => $this->callExternalService());
    echo "Success: $result\n";
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, use fallback or cache
    $result = $this->getFallbackData();
}

// Force open for maintenance
$breaker->forceOpen();

// Manually close when ready
$breaker->close();
```

### Bulkhead Usage

```php
$bulkhead = new Bulkhead(
    maxConcurrent: 5,
    maxQueueSize: 10
);

// Limit concurrent operations
foreach ($tasks as $task) {
    try {
        $result = $bulkhead->execute(fn() => $this->processTask($task));
    } catch (BulkheadFullException $e) {
        // Too many concurrent operations, queue or skip
        $this->queueForLater($task);
    }
}

// Check utilization
$stats = $bulkhead->getStats();
echo "Active: {$stats['active']}/{$stats['maxConcurrent']}\n";
echo "Utilization: {$stats['utilizationPercent']}%\n";
```

### Combined Usage

```php
// Production configuration
$adapter = new ReliableGuzzleAdapter(
    retryPolicy: new RetryPolicy(
        maxAttempts: 5,
        baseDelayMs: 200,
        backoffMultiplier: 2.0,
        jitterMs: 100,
        maxDelayMs: 10000
    ),
    rateLimiter: new RateLimiter(
        requestsPerSecond: 10.0,
        bucketCapacity: 50
    )
);

// Use in your blockchain driver
$driver->setHttpClient($adapter);
```

## Configuration Guidelines

### RetryPolicy

| Parameter | Recommended | Notes |
|-----------|-------------|-------|
| maxAttempts | 3-5 | Balance between resilience and latency |
| baseDelayMs | 100-500 | Start with shorter delays for faster feedback |
| backoffMultiplier | 2.0-3.0 | 2.0 is standard, 3.0 for more aggressive backoff |
| jitterMs | 0-100 | Use jitter to prevent thundering herd |
| maxDelayMs | 5000-30000 | Prevent excessive wait times |

### RateLimiter

| Parameter | Recommended | Notes |
|-----------|-------------|-------|
| requestsPerSecond | Per API docs | Never exceed documented API limits |
| bucketCapacity | 2-10x rate | Higher = more burst tolerance |

### CircuitBreaker

| Parameter | Recommended | Notes |
|-----------|-------------|-------|
| failureThreshold | 3-10 | Lower for critical services, higher for tolerant ones |
| windowSizeSeconds | 30-120 | Longer window for smoother failure detection |
| cooldownSeconds | 15-60 | Allow time for service to recover |
| successThreshold | 1-3 | Higher for more confident recovery |

### Bulkhead

| Parameter | Recommended | Notes |
|-----------|-------------|-------|
| maxConcurrent | 5-20 | Based on resource capacity and latency |
| maxQueueSize | 0-50 | 0 for immediate rejection, higher for queuing |
| queueTimeoutSeconds | 10-60 | Balance between patience and responsiveness |

### Retryable Exceptions

**DO Retry:**
- Network connection failures (`ConnectException`)
- Request timeouts (`RequestException`)
- Server errors 5xx (`TransactionException`)
- Rate limit errors 429 (with backoff)

**DON'T Retry:**
- Validation errors 4xx (`ValidationException`)
- Authentication errors 401/403
- Not found errors 404
- Bad request errors 400

## Testing

The components are designed for testability:

```php
// Override delay() to avoid sleeping in tests
class TestableRetryPolicy extends RetryPolicy
{
    protected function delay(int $milliseconds): void
    {
        // Don't actually sleep in tests
    }
}

// Override time functions for deterministic tests
class TestableRateLimiter extends RateLimiter
{
    private float $currentTime = 0.0;
    
    protected function getCurrentTime(): float
    {
        return $this->currentTime;
    }
    
    public function advanceTime(float $microseconds): void
    {
        $this->currentTime += $microseconds;
    }
}
```

See `tests/Reliability/` for comprehensive test examples.

## Best Practices

### ✅ DO

1. **Use retry for transient failures**
   - Network timeouts
   - Connection refused
   - Server temporarily unavailable (503)

2. **Configure rate limits conservatively**
   - Stay below documented API limits
   - Account for other clients/processes
   - Use burst capacity for traffic spikes

3. **Combine multiple patterns for resilience**
   - Retry handles transient failures
   - Rate limiting prevents overwhelming APIs
   - Circuit breaker prevents cascading failures
   - Bulkhead isolates resource usage

4. **Test with realistic scenarios**
   - Simulate network failures
   - Verify retry behavior
   - Validate rate limit enforcement
   - Test circuit breaker state transitions
   - Monitor bulkhead utilization

5. **Use circuit breakers for external dependencies**
   - Protect against slow/failing services
   - Fail fast when service is down
   - Allow time for recovery

6. **Apply bulkheads per resource pool**
   - Separate limits for different drivers
   - Isolate critical vs non-critical operations
   - Prevent resource exhaustion

### ❌ DON'T

1. **Don't retry validation errors**
   - 400 Bad Request
   - 401 Unauthorized
   - 404 Not Found
   - These won't succeed on retry

2. **Don't set excessive retry attempts**
   - Increases latency
   - Delays error feedback
   - Can hide underlying issues

3. **Don't ignore HTTP 429 responses**
   - Respect Retry-After headers
   - Reduce request rate
   - Consider exponential backoff

4. **Don't retry non-idempotent operations blindly**
   - POST requests that create resources
   - Financial transactions
   - State-changing operations

5. **Don't set bulkhead limits too low**
   - Can unnecessarily throttle operations
   - May cause legitimate requests to fail
   - Monitor utilization before tuning

6. **Don't keep circuit open indefinitely**
   - Allow automatic recovery attempts
   - Monitor circuit breaker metrics
   - Investigate root cause of failures

## Performance Considerations

### Memory Usage
- RetryPolicy: Minimal (< 1KB per instance)
- RateLimiter: Minimal (< 1KB per instance)
- CircuitBreaker: Minimal (< 1KB per instance, tracks failure timestamps)
- Bulkhead: Minimal (< 1KB per instance, tracks active count)
- ReliableGuzzleAdapter: Same as GuzzleAdapter

### CPU Usage
- RetryPolicy: Negligible (simple calculations)
- RateLimiter: Negligible (arithmetic operations)
- CircuitBreaker: Negligible (state checks and timestamp comparisons)
- Bulkhead: Negligible (counter operations)
- Delay operations use `usleep()` - non-blocking at OS level

### Latency Impact
- RetryPolicy: Adds delay only on failures
- RateLimiter: Adds delay only when rate exceeded
- CircuitBreaker: Minimal overhead, fails fast when open
- Bulkhead: Minimal overhead, may reject when full
- Combined: Minimal impact under normal conditions

## Integration with Drivers

To use reliability components with blockchain drivers:

```php
use Blockchain\BlockchainManager;
use Blockchain\Transport\ReliableGuzzleAdapter;
use Blockchain\Reliability\CircuitBreaker;
use Blockchain\Reliability\Bulkhead;

// Create reliable adapter with all protections
$adapter = new ReliableGuzzleAdapter(
    retryPolicy: new RetryPolicy(maxAttempts: 3),
    rateLimiter: new RateLimiter(requestsPerSecond: 10.0)
);

// Add circuit breaker for the driver
$breaker = new CircuitBreaker(
    failureThreshold: 5,
    windowSizeSeconds: 60,
    cooldownSeconds: 30
);

// Add bulkhead for concurrency control
$bulkhead = new Bulkhead(
    maxConcurrent: 10,
    maxQueueSize: 5
);

// Use with driver
$manager = new BlockchainManager('ethereum', [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_KEY',
    'http_client' => $adapter  // Inject reliable adapter
]);

// Wrap operations with circuit breaker and bulkhead
try {
    $result = $breaker->call(function() use ($bulkhead, $manager) {
        return $bulkhead->execute(function() use ($manager) {
            return $manager->getBalance('0x...');
        });
    });
} catch (CircuitBreakerOpenException $e) {
    // Handle circuit open
} catch (BulkheadFullException $e) {
    // Handle capacity exceeded
}
```

## See Also

- [Examples](../../examples/reliability-example.php) - Comprehensive usage examples
- [CircuitBreaker Tests](../../tests/Reliability/CircuitBreakerTest.php) - Test patterns
- [Bulkhead Tests](../../tests/Reliability/BulkheadTest.php) - Test patterns
- [Tests](../../tests/Reliability/) - Test suite with usage patterns
- [GuzzleAdapter](../Transport/GuzzleAdapter.php) - Base HTTP adapter
- [CODING_GUIDELINES.md](../../CODING_GUIDELINES.md) - Project coding standards

## License

MIT License - See LICENSE file for details
