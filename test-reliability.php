<?php

declare(strict_types=1);

// Simple test runner without PHPUnit dependencies
require_once __DIR__ . '/src/Reliability/RetryPolicy.php';
require_once __DIR__ . '/src/Reliability/RateLimiter.php';

use Blockchain\Reliability\RetryPolicy;
use Blockchain\Reliability\RateLimiter;

echo "Testing RetryPolicy...\n";

// Test 1: Basic instantiation
$policy = new RetryPolicy();
assert($policy->getMaxAttempts() === 3, "Default max attempts should be 3");
assert($policy->getBaseDelayMs() === 100, "Default base delay should be 100ms");
assert($policy->getBackoffMultiplier() === 2.0, "Default backoff multiplier should be 2.0");
echo "✓ RetryPolicy instantiation test passed\n";

// Test 2: Custom parameters
$policy2 = new RetryPolicy(maxAttempts: 5, baseDelayMs: 200, backoffMultiplier: 3.0);
assert($policy2->getMaxAttempts() === 5, "Custom max attempts should be 5");
assert($policy2->getBaseDelayMs() === 200, "Custom base delay should be 200ms");
assert($policy2->getBackoffMultiplier() === 3.0, "Custom backoff multiplier should be 3.0");
echo "✓ RetryPolicy custom parameters test passed\n";

// Test 3: Exponential backoff calculation
$delay1 = $policy->calculateDelay(1);
$delay2 = $policy->calculateDelay(2);
$delay3 = $policy->calculateDelay(3);
assert($delay1 === 100, "First delay should be 100ms (actual: $delay1)");
assert($delay2 === 200, "Second delay should be 200ms (actual: $delay2)");
assert($delay3 === 400, "Third delay should be 400ms (actual: $delay3)");
echo "✓ RetryPolicy exponential backoff test passed\n";

// Test 4: Max delay cap
$policy3 = new RetryPolicy(baseDelayMs: 100, backoffMultiplier: 2.0, jitterMs: 0, maxDelayMs: 500);
$delay4 = $policy3->calculateDelay(4);
assert($delay4 === 500, "Delay should be capped at 500ms (actual: $delay4)");
echo "✓ RetryPolicy max delay cap test passed\n";

// Test 5: Successful execution
$callCount = 0;
$result = $policy->execute(function () use (&$callCount) {
    $callCount++;
    return 'success';
});
assert($result === 'success', "Should return 'success'");
assert($callCount === 1, "Should only call once for successful operation");
echo "✓ RetryPolicy successful execution test passed\n";

// Test 6: Invalid parameters
try {
    new RetryPolicy(maxAttempts: 0);
    assert(false, "Should throw exception for maxAttempts = 0");
} catch (InvalidArgumentException $e) {
    echo "✓ RetryPolicy validates maxAttempts\n";
}

echo "\nTesting RateLimiter...\n";

// Test 1: Basic instantiation
$limiter = new RateLimiter(requestsPerSecond: 10.0);
assert($limiter->getRequestsPerSecond() === 10.0, "Requests per second should be 10.0");
assert($limiter->getBucketCapacity() === 10, "Default bucket capacity should be 10");
echo "✓ RateLimiter instantiation test passed\n";

// Test 2: Custom capacity
$limiter2 = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 20);
assert($limiter2->getBucketCapacity() === 20, "Custom bucket capacity should be 20");
echo "✓ RateLimiter custom capacity test passed\n";

// Test 3: Full bucket on start
assert($limiter2->getAvailableTokens() === 20.0, "Should start with full bucket");
echo "✓ RateLimiter starts with full bucket\n";

// Test 4: Token consumption
assert($limiter2->tryAcquire(5) === true, "Should acquire 5 tokens");
assert($limiter2->getAvailableTokens() === 15.0, "Should have 15 tokens left");
echo "✓ RateLimiter token consumption test passed\n";

// Test 5: Insufficient tokens
$limiter3 = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 2);
assert($limiter3->tryAcquire(2) === true, "Should acquire 2 tokens");
assert($limiter3->tryAcquire(1) === false, "Should fail to acquire when no tokens");
echo "✓ RateLimiter insufficient tokens test passed\n";

// Test 6: Reset
$limiter3->reset();
assert($limiter3->getAvailableTokens() === 2.0, "Should reset to full bucket");
echo "✓ RateLimiter reset test passed\n";

// Test 7: Invalid parameters
try {
    new RateLimiter(requestsPerSecond: 0);
    assert(false, "Should throw exception for requestsPerSecond = 0");
} catch (InvalidArgumentException $e) {
    echo "✓ RateLimiter validates requestsPerSecond\n";
}

try {
    $limiter->tryAcquire(0);
    assert(false, "Should throw exception for tokens = 0");
} catch (InvalidArgumentException $e) {
    echo "✓ RateLimiter validates token count\n";
}

echo "\n✅ All manual tests passed!\n";
echo "\nNote: Full test suite requires PHPUnit. Run 'composer test' once dependencies are installed.\n";
