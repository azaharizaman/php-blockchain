# Chaos Testing Guide

This guide explains how to use the chaos testing harness to validate resilience and recovery behaviors in the PHP Blockchain Integration Layer.

## Overview

The chaos testing harness (`tests/Resilience/chaos-harness.php`) provides a framework for injecting various failure modes into your tests to validate that the system's resilience patterns (retry policies, circuit breakers, rate limiters) handle failures gracefully.

## Quick Start

### Running Chaos Tests Locally

```bash
# Enable chaos mode and run all resilience tests
CHAOS_TESTING=true vendor/bin/phpunit tests/Resilience/ResilienceScenariosTest.php

# Run a specific chaos scenario
CHAOS_TESTING=true vendor/bin/phpunit --filter testSystemToleratesHighLatency tests/Resilience/

# Run without chaos mode (normal resilience tests)
vendor/bin/phpunit tests/Resilience/
```

## Available Scenarios

### 1. Latency Injection

Simulates slow network conditions or high server response times.

```php
use Blockchain\Tests\Resilience\ChaosHarness;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

// Create a scenario with 500ms latency
$handler = ChaosHarness::createLatencyScenario(delayMs: 500);
$handlerStack = HandlerStack::create($handler);
$client = new Client(['handler' => $handlerStack]);

// Requests will now have 500ms delay
```

**Use Cases:**
- Test timeout handling
- Validate user experience under latency
- Ensure operations don't fail due to slow responses

### 2. Rate Limit Spikes

Simulates API rate limiting with 429 responses.

```php
// Create scenario with 3 rate limit responses before success
$handler = ChaosHarness::createRateLimitScenario(
    rateLimitCount: 3,
    retryAfterSeconds: 1
);

// First 3 requests return 429, 4th succeeds
```

**Use Cases:**
- Test retry logic respects Retry-After headers
- Validate backoff strategies
- Ensure rate limits don't crash the application

### 3. Intermittent Errors

Random failures to simulate unreliable networks or services.

```php
// 30% of requests will fail randomly
$handler = ChaosHarness::createIntermittentErrorScenario(
    failureRate: 30,
    totalRequests: 10,
    errorType: 'server' // 'timeout', 'connection', 'server'
);
```

**Use Cases:**
- Test retry policies with various failure rates
- Validate error recovery mechanisms
- Ensure transient failures are handled gracefully

### 4. Partial Batch Failures

Some operations in a batch succeed while others fail.

```php
// 7 succeed, 3 fail in a batch of 10
$handler = ChaosHarness::createPartialBatchFailureScenario(
    batchSize: 10,
    failureCount: 3,
    sequentialFailures: false // Random distribution
);
```

**Use Cases:**
- Test batch processing error handling
- Validate partial success scenarios
- Ensure successful operations complete despite some failures

### 5. Combined Scenarios

Multiple concurrent failure modes for comprehensive testing.

```php
$handler = ChaosHarness::createCombinedScenario([
    'latency_ms' => 200,
    'rate_limit_count' => 2,
    'error_count' => 2,
    'success_count' => 3
]);
```

**Use Cases:**
- Test comprehensive resilience strategies
- Validate recovery under complex real-world conditions
- Ensure system remains stable with multiple failure types

## Integration with Tests

### Basic Pattern

```php
use Blockchain\Tests\Resilience\ChaosHarness;
use PHPUnit\Framework\TestCase;

class MyResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ChaosHarness::enable();
    }
    
    protected function tearDown(): void
    {
        ChaosHarness::reset();
        parent::tearDown();
    }
    
    public function testMyResilience(): void
    {
        // Create chaos scenario
        $handler = ChaosHarness::createLatencyScenario(500);
        
        // Use handler with your HTTP client
        // ... test implementation
        
        // Assertions
        $this->assertTrue($operationSucceeded);
    }
}
```

### With Retry Policy

```php
use Blockchain\Reliability\RetryPolicy;
use Blockchain\Transport\GuzzleAdapter;

public function testRetryUnderChaos(): void
{
    // Create chaos handler
    $handler = ChaosHarness::createIntermittentErrorScenario(
        failureRate: 40,
        totalRequests: 10,
        errorType: 'server'
    );
    
    // Configure retry policy
    $retryPolicy = new RetryPolicy(
        maxAttempts: 5,
        baseDelayMs: 10,
        backoffMultiplier: 1.5
    );
    
    // Use with chaos handler
    $client = new Client(['handler' => HandlerStack::create($handler)]);
    $adapter = new GuzzleAdapter($client);
    
    // Execute with retry
    $result = $retryPolicy->execute(function () use ($adapter) {
        return $adapter->post('http://localhost:8545', [
            'method' => 'eth_blockNumber'
        ]);
    });
    
    $this->assertNotFalse($result);
}
```

### With Circuit Breaker

```php
use Blockchain\Reliability\CircuitBreaker;
use Blockchain\Reliability\CircuitBreakerOpenException;

public function testCircuitBreakerUnderChaos(): void
{
    // Create chaos scenario with sequential failures
    $handler = ChaosHarness::createPartialBatchFailureScenario(
        batchSize: 10,
        failureCount: 6,
        sequentialFailures: true
    );
    
    // Configure circuit breaker
    $breaker = new CircuitBreaker(
        failureThreshold: 3,
        windowSizeSeconds: 60,
        cooldownSeconds: 1
    );
    
    $circuitOpened = false;
    
    // Execute operations
    for ($i = 0; $i < 10; $i++) {
        try {
            $breaker->call(function () use ($adapter) {
                // Your operation
            });
        } catch (CircuitBreakerOpenException $e) {
            $circuitOpened = true;
        }
    }
    
    $this->assertTrue($circuitOpened, 'Circuit should open after failures');
}
```

## CI Integration

Chaos tests run automatically in CI on a nightly schedule via the `chaos-testing.yml` workflow.

### Workflow Configuration

```yaml
# .github/workflows/chaos-testing.yml
name: Chaos Testing (Nightly)

on:
  schedule:
    - cron: '0 2 * * *'  # 2 AM UTC daily
  workflow_dispatch:      # Manual trigger
```

### Manual CI Trigger

1. Go to Actions tab in GitHub
2. Select "Chaos Testing (Nightly)" workflow
3. Click "Run workflow"
4. Optionally specify scenarios to run

### Non-Blocking Design

The chaos testing workflow is configured with `continue-on-error: true` to prevent blocking the build pipeline. This is intentional because:

- Chaos tests validate graceful degradation
- Some failures are expected behavior
- Tests verify recovery, not perfect success
- Results are informational rather than pass/fail

## Environment Variables

- `CHAOS_TESTING=true`: Enable chaos mode (required for chaos scenarios)
- `CHAOS_TESTING=false`: Explicitly disable chaos mode
- Not set: Chaos mode disabled by default

## Best Practices

### 1. Use Realistic Failure Rates

Base your failure rates on real-world observations:
- Network timeouts: 1-5%
- Rate limits: Depends on API provider
- Server errors: 0.1-1%

### 2. Test Recovery, Not Just Failure

Focus on:
- Does the system recover?
- How long does recovery take?
- Are errors handled gracefully?
- Does the system maintain data consistency?

### 3. Combine with Resilience Patterns

Always test chaos scenarios with:
- Retry policies for transient failures
- Circuit breakers for cascading failures
- Rate limiters for API protection
- Timeout handling for hung operations

### 4. Document Expected Behavior

In your tests, clearly document:
- What failure is being simulated
- Expected system behavior
- Acceptable recovery windows
- Failure thresholds

### 5. Avoid Sleep in Tests

Use the chaos harness to inject delays rather than `sleep()`:

```php
// ❌ Bad: Slows down all test runs
sleep(2);

// ✅ Good: Only adds delay when chaos mode is enabled
$handler = ChaosHarness::createLatencyScenario(delayMs: 2000);
```

## Troubleshooting

### Tests Always Pass/Fail

Check that `CHAOS_TESTING` environment variable is set correctly:

```bash
# Verify it's set
echo $CHAOS_TESTING

# Should output: true
```

### Chaos Not Being Applied

Ensure you're using the handler with your HTTP client:

```php
$handler = ChaosHarness::createLatencyScenario(500);
$handlerStack = HandlerStack::create($handler);
$client = new Client(['handler' => $handlerStack]); // Must use handler
```

### Tests Timeout

Increase PHPUnit timeout or reduce chaos severity:

```php
// Reduce latency
$handler = ChaosHarness::createLatencyScenario(delayMs: 100); // Instead of 1000

// Or increase retry timeouts
$retryPolicy = new RetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 50 // Lower for faster tests
);
```

## Examples

See `tests/Resilience/ResilienceScenariosTest.php` for complete examples of:
- Latency tolerance testing
- Retry policy validation
- Circuit breaker behavior
- Rate limit handling
- Partial batch failures
- Combined failure scenarios

## Contributing

When adding new chaos scenarios:

1. Add the scenario method to `ChaosHarness` class
2. Document the scenario in this guide
3. Add corresponding test cases
4. Update README.md chaos testing section
5. Ensure tests are non-blocking in CI

## References

- [Resilience Patterns Documentation](../src/Reliability/README.md)
- [Testing Guide](../TESTING.md)
- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Retry Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/retry)
- [Chaos Engineering Principles](https://principlesofchaos.org/)
