# Reliability Components

This directory contains components for building resilient blockchain integrations with automatic retry and rate limiting capabilities.

## Overview

The Reliability components provide:
- **RetryPolicy**: Exponential backoff with jitter for handling transient failures
- **RateLimiter**: Token-bucket algorithm for client-side rate limiting
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

3. **Combine retry and rate limiting**
   - Retry handles transient failures
   - Rate limiting prevents overwhelming APIs

4. **Test with realistic scenarios**
   - Simulate network failures
   - Verify retry behavior
   - Validate rate limit enforcement

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

## Performance Considerations

### Memory Usage
- RetryPolicy: Minimal (< 1KB per instance)
- RateLimiter: Minimal (< 1KB per instance)
- ReliableGuzzleAdapter: Same as GuzzleAdapter

### CPU Usage
- RetryPolicy: Negligible (simple calculations)
- RateLimiter: Negligible (arithmetic operations)
- Delay operations use `usleep()` - non-blocking at OS level

### Latency Impact
- RetryPolicy: Adds delay only on failures
- RateLimiter: Adds delay only when rate exceeded
- Combined: Minimal impact under normal conditions

## Integration with Drivers

To use reliability components with blockchain drivers:

```php
use Blockchain\BlockchainManager;
use Blockchain\Transport\ReliableGuzzleAdapter;

// Create reliable adapter
$adapter = new ReliableGuzzleAdapter(
    retryPolicy: new RetryPolicy(maxAttempts: 3),
    rateLimiter: new RateLimiter(requestsPerSecond: 10.0)
);

// Use with driver
$manager = new BlockchainManager('ethereum', [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_KEY',
    'http_client' => $adapter  // Inject reliable adapter
]);
```

## See Also

- [Examples](../../examples/reliability-example.php) - Comprehensive usage examples
- [Tests](../../tests/Reliability/) - Test suite with usage patterns
- [GuzzleAdapter](../Transport/GuzzleAdapter.php) - Base HTTP adapter
- [CODING_GUIDELINES.md](../../CODING_GUIDELINES.md) - Project coding standards

## License

MIT License - See LICENSE file for details
