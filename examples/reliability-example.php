<?php

/**
 * Example: Using Retry Policies and Rate Limiting
 *
 * This example demonstrates how to use the RetryPolicy and RateLimiter
 * components to build resilient blockchain integrations.
 */

declare(strict_types=1);

// Manual requires for when autoloader is not available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../src/Reliability/RetryPolicy.php';
    require_once __DIR__ . '/../src/Reliability/RateLimiter.php';
    // Note: ReliableGuzzleAdapter requires Guzzle, so we skip it in standalone mode
}

use Blockchain\Reliability\RetryPolicy;
use Blockchain\Reliability\RateLimiter;

echo "=== Retry Policy and Rate Limiting Examples ===\n\n";

// Example 1: Basic Retry Policy
echo "1. Basic Retry Policy\n";
echo "   Creating a retry policy with exponential backoff...\n";

$retryPolicy = new RetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 100,
    backoffMultiplier: 2.0,
    jitterMs: 50,
    maxDelayMs: 5000
);

echo "   - Max attempts: {$retryPolicy->getMaxAttempts()}\n";
echo "   - Base delay: {$retryPolicy->getBaseDelayMs()}ms\n";
echo "   - Backoff multiplier: {$retryPolicy->getBackoffMultiplier()}\n";
echo "   - Jitter: {$retryPolicy->getJitterMs()}ms\n\n";

// Simulate delays for each retry attempt
echo "   Calculated delays for retry attempts:\n";
for ($i = 1; $i <= 3; $i++) {
    $delay = $retryPolicy->calculateDelay($i);
    echo "   - Attempt $i delay: {$delay}ms\n";
}
echo "\n";

// Example 2: Retry Policy in Action
echo "2. Retry Policy in Action\n";
echo "   Simulating a flaky operation that fails twice then succeeds...\n";

$attemptCount = 0;
$testPolicy = new RetryPolicy(maxAttempts: 3, baseDelayMs: 10);

try {
    $result = $testPolicy->execute(function () use (&$attemptCount) {
        $attemptCount++;
        echo "   - Attempt $attemptCount: ";
        
        if ($attemptCount < 3) {
            echo "Failed ❌\n";
            throw new RuntimeException("Temporary failure");
        }
        
        echo "Success ✅\n";
        return "Operation completed successfully";
    }, [RuntimeException::class]);
    
    echo "   Result: $result\n\n";
} catch (Throwable $e) {
    echo "   Final error: {$e->getMessage()}\n\n";
}

// Example 3: Basic Rate Limiter
echo "3. Basic Rate Limiter\n";
echo "   Creating a rate limiter allowing 5 requests per second...\n";

$rateLimiter = new RateLimiter(
    requestsPerSecond: 5.0,
    bucketCapacity: 10
);

echo "   - Requests per second: {$rateLimiter->getRequestsPerSecond()}\n";
echo "   - Bucket capacity: {$rateLimiter->getBucketCapacity()}\n";
echo "   - Available tokens: {$rateLimiter->getAvailableTokens()}\n\n";

// Example 4: Rate Limiter in Action
echo "4. Rate Limiter in Action\n";
echo "   Attempting to make 12 requests (capacity is 10)...\n";

$successCount = 0;
$blockedCount = 0;

for ($i = 1; $i <= 12; $i++) {
    if ($rateLimiter->tryAcquire()) {
        $successCount++;
        echo "   - Request $i: Allowed ✅ (tokens left: " . 
             round($rateLimiter->getAvailableTokens(), 2) . ")\n";
    } else {
        $blockedCount++;
        echo "   - Request $i: Rate limited ⛔\n";
    }
}

echo "\n   Summary: $successCount allowed, $blockedCount rate limited\n\n";

// Example 5: Combined Usage with ReliableGuzzleAdapter
echo "5. Reliable Guzzle Adapter (Combined Usage)\n";
echo "   ReliableGuzzleAdapter combines retry and rate limiting...\n";

echo "   ✅ Configuration example:\n";
echo "   \$reliableAdapter = new ReliableGuzzleAdapter(\n";
echo "       retryPolicy: new RetryPolicy(\n";
echo "           maxAttempts: 3,\n";
echo "           baseDelayMs: 100,\n";
echo "           backoffMultiplier: 2.0\n";
echo "       ),\n";
echo "       rateLimiter: new RateLimiter(\n";
echo "           requestsPerSecond: 10.0,\n";
echo "           bucketCapacity: 20\n";
echo "       )\n";
echo "   );\n";
echo "\n   Usage:\n";
echo "   \$result = \$reliableAdapter->post('https://api.example.com/rpc', [\n";
echo "       'jsonrpc' => '2.0',\n";
echo "       'method' => 'eth_blockNumber',\n";
echo "       'params' => [],\n";
echo "       'id' => 1\n";
echo "   ]);\n";
echo "   // Automatic retry on failures + rate limiting applied!\n\n";

// Example 6: Different Rate Limiting Scenarios
echo "6. Rate Limiting Scenarios\n\n";

echo "   Scenario A: Strict rate limiting (1 request per second)\n";
$strictLimiter = new RateLimiter(requestsPerSecond: 1.0, bucketCapacity: 1);
echo "   - Rate: {$strictLimiter->getRequestsPerSecond()} req/s\n";
echo "   - Burst: {$strictLimiter->getBucketCapacity()}\n";
echo "   - Use case: Public APIs with strict limits\n\n";

echo "   Scenario B: Generous rate limiting with burst (100 req/s, burst of 500)\n";
$generousLimiter = new RateLimiter(requestsPerSecond: 100.0, bucketCapacity: 500);
echo "   - Rate: {$generousLimiter->getRequestsPerSecond()} req/s\n";
echo "   - Burst: {$generousLimiter->getBucketCapacity()}\n";
echo "   - Use case: Internal/premium APIs\n\n";

echo "   Scenario C: Fractional rate (1 request every 2 seconds)\n";
$fractionalLimiter = new RateLimiter(requestsPerSecond: 0.5, bucketCapacity: 1);
echo "   - Rate: {$fractionalLimiter->getRequestsPerSecond()} req/s\n";
echo "   - Burst: {$fractionalLimiter->getBucketCapacity()}\n";
echo "   - Use case: Very limited/throttled endpoints\n\n";

// Example 7: Best Practices
echo "7. Best Practices\n\n";

echo "   ✅ DO:\n";
echo "   - Use RetryPolicy for transient network failures\n";
echo "   - Use RateLimiter to stay within API limits\n";
echo "   - Combine both for production reliability\n";
echo "   - Configure retry attempts based on operation importance\n";
echo "   - Set appropriate rate limits based on API documentation\n";
echo "   - Use burst capacity for handling traffic spikes\n\n";

echo "   ❌ DON'T:\n";
echo "   - Retry on validation errors (4xx) - these won't succeed\n";
echo "   - Set maxDelayMs too high - it delays error feedback\n";
echo "   - Ignore HTTP 429 responses - respect rate limits\n";
echo "   - Use retry for non-idempotent operations without careful consideration\n";
echo "   - Set rate limits higher than what the API allows\n\n";

echo "=== Examples Complete ===\n";
echo "\nFor more information, see:\n";
echo "- src/Reliability/RetryPolicy.php\n";
echo "- src/Reliability/RateLimiter.php\n";
echo "- src/Transport/ReliableGuzzleAdapter.php\n";
