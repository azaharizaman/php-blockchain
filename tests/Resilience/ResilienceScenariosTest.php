<?php

declare(strict_types=1);

namespace Blockchain\Tests\Resilience;

use Blockchain\Reliability\CircuitBreaker;
use Blockchain\Reliability\CircuitBreakerOpenException;
use Blockchain\Reliability\RetryPolicy;
use Blockchain\Reliability\RateLimiter;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

/**
 * ResilienceScenariosTest validates system behavior under chaos conditions.
 *
 * This test suite uses the ChaosHarness to inject various failure modes and
 * validates that the system's resilience patterns (retry, circuit breaker,
 * rate limiter) handle failures gracefully and recover within expected windows.
 *
 * Tests can be run with chaos mode enabled:
 * ```bash
 * CHAOS_TESTING=true vendor/bin/phpunit tests/Resilience/ResilienceScenariosTest.php
 * ```
 *
 * @package Blockchain\Tests\Resilience
 */
class ResilienceScenariosTest extends TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Enable chaos testing for these tests
        ChaosHarness::enable();
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        // Reset chaos testing state
        ChaosHarness::reset();
        parent::tearDown();
    }

    /**
     * Test system recovery from high latency conditions.
     *
     * Validates that operations complete successfully despite high latency
     * and that timeouts are handled appropriately.
     */
    public function testSystemToleratesHighLatency(): void
    {
        // Create scenario with 500ms latency
        $handler = ChaosHarness::createLatencyScenario(delayMs: 500);
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $startTime = microtime(true);

        // Execute request
        $response = $adapter->post('http://localhost:8545', [
            'jsonrpc' => '2.0',
            'method' => 'eth_blockNumber',
            'params' => [],
            'id' => 1
        ]);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Verify latency was injected (should be >= 500ms)
        $this->assertGreaterThanOrEqual(500, $duration, 'Expected at least 500ms latency');

        // Verify response structure (post() returns decoded JSON directly)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('result', $response);
    }

    /**
     * Test retry policy handles intermittent failures.
     *
     * Validates that RetryPolicy successfully retries failed operations
     * and eventually succeeds when the service recovers.
     */
    public function testRetryPolicyHandlesIntermittentFailures(): void
    {
        // Create scenario with 40% failure rate across 10 requests
        $handler = ChaosHarness::createIntermittentErrorScenario(
            failureRate: 40,
            totalRequests: 10,
            errorType: 'server'
        );
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Configure retry policy: 5 attempts with short delays for test speed
        $retryPolicy = new RetryPolicy(
            maxAttempts: 5,
            baseDelayMs: 10,
            backoffMultiplier: 1.5
        );

        $successCount = 0;
        $attemptCount = 0;

        // Execute multiple operations
        for ($i = 0; $i < 10; $i++) {
            $attemptCount++;

            try {
                $result = $retryPolicy->execute(function () use ($adapter) {
                    $response = $adapter->post('http://localhost:8545', [
                        'jsonrpc' => '2.0',
                        'method' => 'eth_blockNumber',
                        'params' => [],
                        'id' => 1
                    ]);

                    // Check for error in response (post() returns decoded JSON directly)
                    if (isset($response['error'])) {
                        throw new \RuntimeException($response['error']['message']);
                    }

                    return $response;
                });

                if ($result !== false) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                // Some operations may exhaust retries
                continue;
            }
        }

        // Verify that some operations succeeded despite failures
        $this->assertGreaterThan(0, $successCount, 'Expected some successful operations after retries');

        // With 40% failure rate and 5 retries, we should have decent success rate
        $this->assertGreaterThanOrEqual(5, $successCount, 'Expected at least 50% success rate with retries');
    }

    /**
     * Test circuit breaker opens after threshold failures.
     *
     * Validates that CircuitBreaker protects the system by opening the circuit
     * after repeated failures and recovers appropriately.
     */
    public function testCircuitBreakerOpensAfterThresholdFailures(): void
    {
        // Create scenario with sequential failures then success
        $handler = ChaosHarness::createPartialBatchFailureScenario(
            batchSize: 10,
            failureCount: 6,
            sequentialFailures: true
        );
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Configure circuit breaker: 3 failures in 60s window, 1s cooldown
        $breaker = new CircuitBreaker(
            failureThreshold: 3,
            windowSizeSeconds: 60,
            cooldownSeconds: 1
        );

        $circuitOpenCount = 0;
        $successCount = 0;

        // Execute operations
        for ($i = 0; $i < 10; $i++) {
            try {
                $result = $breaker->call(function () use ($adapter) {
                    $response = $adapter->post('http://localhost:8545', [
                        'jsonrpc' => '2.0',
                        'method' => 'eth_blockNumber',
                        'params' => [],
                        'id' => 1
                    ]);

                    // GuzzleAdapter throws exceptions for HTTP errors (400+)
                    // If we get here, the request succeeded
                    return $response;
                });

                $successCount++;
            } catch (CircuitBreakerOpenException $e) {
                $circuitOpenCount++;
            } catch (\Exception $e) {
                // Expected failures before circuit opens (thrown by GuzzleAdapter)
                continue;
            }
        }

        // Verify circuit breaker opened
        $this->assertGreaterThan(0, $circuitOpenCount, 'Expected circuit breaker to open');

        // Verify some requests were blocked by open circuit
        $this->assertGreaterThanOrEqual(3, $circuitOpenCount, 'Expected multiple requests blocked by open circuit');
    }

    /**
     * Test rate limiter handles rate limit spikes.
     *
     * Validates that the system can handle rate limiting responses
     * and respects retry-after headers.
     */
    public function testSystemHandlesRateLimitSpikes(): void
    {
        // Create scenario with 3 rate limit responses then success
        $handler = ChaosHarness::createRateLimitScenario(
            rateLimitCount: 3,
            retryAfterSeconds: 1
        );
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Configure retry policy with more attempts for rate limits
        $retryPolicy = new RetryPolicy(
            maxAttempts: 5,
            baseDelayMs: 100,
            backoffMultiplier: 2.0
        );

        $attempts = 0;
        $rateLimitHit = false;
        $eventualSuccess = false;

        $result = $retryPolicy->execute(function () use ($adapter, &$attempts, &$rateLimitHit) {
            $attempts++;

            try {
                $response = $adapter->post('http://localhost:8545', [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_blockNumber',
                    'params' => [],
                    'id' => 1
                ]);

                // If we get here, request succeeded
                return $response;
            } catch (\Blockchain\Exceptions\ValidationException $e) {
                // GuzzleAdapter throws ValidationException for 4xx errors (including 429)
                $rateLimitHit = true;
                throw new \RuntimeException('Rate limit exceeded');
            }
        });

        if ($result !== false) {
            $eventualSuccess = true;
        }

        // Verify rate limiting was encountered
        $this->assertTrue($rateLimitHit, 'Expected to encounter rate limiting');

        // Verify system retried multiple times
        $this->assertGreaterThanOrEqual(3, $attempts, 'Expected at least 3 attempts');

        // Verify eventual success after rate limit recovery
        $this->assertTrue($eventualSuccess, 'Expected eventual success after rate limit recovery');
    }

    /**
     * Test system handles partial batch failures gracefully.
     *
     * Validates that batch operations with partial failures are handled
     * appropriately and successful operations complete.
     */
    public function testSystemHandlesPartialBatchFailures(): void
    {
        // Create scenario with 7 successes and 3 failures in random order
        $handler = ChaosHarness::createPartialBatchFailureScenario(
            batchSize: 10,
            failureCount: 3,
            sequentialFailures: false
        );
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $successCount = 0;
        $failureCount = 0;

        // Execute batch of operations
        for ($i = 0; $i < 10; $i++) {
            try {
                $response = $adapter->post('http://localhost:8545', [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBlockByNumber',
                    'params' => [sprintf('0x%x', $i), false],
                    'id' => $i
                ]);

                // GuzzleAdapter returns decoded JSON directly and throws for errors
                // If we get here, it's a success unless there's an error in the JSON-RPC response
                if (!isset($response['error'])) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Exception $e) {
                // GuzzleAdapter throws exceptions for HTTP errors
                $failureCount++;
            }
        }

        // Verify partial success
        $this->assertGreaterThan(0, $successCount, 'Expected some successful operations');
        $this->assertGreaterThan(0, $failureCount, 'Expected some failed operations');

        // Verify expected ratio (7 success, 3 failures)
        $this->assertSame(7, $successCount, 'Expected 7 successful operations');
        $this->assertSame(3, $failureCount, 'Expected 3 failed operations');
    }

    /**
     * Test system recovery window under combined failures.
     *
     * Validates that the system can recover within acceptable time windows
     * when facing multiple concurrent failure modes.
     */
    public function testSystemRecoveryUnderCombinedFailures(): void
    {
        // Create combined scenario with latency, rate limits, and errors
        $handler = ChaosHarness::createCombinedScenario([
            'latency_ms' => 200,
            'rate_limit_count' => 2,
            'error_count' => 2,
            'success_count' => 3
        ]);
        $handlerStack = HandlerStack::create($handler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Configure aggressive retry policy for combined failures
        $retryPolicy = new RetryPolicy(
            maxAttempts: 8,
            baseDelayMs: 50,
            backoffMultiplier: 1.5
        );

        $startTime = microtime(true);
        $attempts = 0;
        $recovered = false;

        try {
            $result = $retryPolicy->execute(function () use ($adapter, &$attempts) {
                $attempts++;

                $response = $adapter->post('http://localhost:8545', [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_blockNumber',
                    'params' => [],
                    'id' => 1
                ]);

                // GuzzleAdapter throws exceptions for HTTP errors (rate limits, server errors)
                // If we get here, the request succeeded
                return $response;
            });

            if ($result !== false) {
                $recovered = true;
            }
        } catch (\Exception $e) {
            // Recovery failed - GuzzleAdapter exceptions will be caught here
        }

        $recoveryTime = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Verify system attempted recovery
        $this->assertGreaterThan(1, $attempts, 'Expected multiple recovery attempts');

        // Verify eventual recovery (should succeed after rate limits and errors clear)
        $this->assertTrue($recovered, 'Expected system to recover from combined failures');

        // Verify recovery happened within reasonable time window (< 5 seconds)
        $this->assertLessThan(5000, $recoveryTime, 'Expected recovery within 5 seconds');
    }

    /**
     * Test chaos harness can be enabled and disabled.
     *
     * Validates that the chaos testing mode can be toggled via environment variable.
     */
    public function testChaosHarnessCanBeToggled(): void
    {
        // Initially enabled in setUp
        $this->assertTrue(ChaosHarness::isEnabled(), 'Expected chaos testing to be enabled');

        // Disable
        ChaosHarness::disable();
        $this->assertFalse(ChaosHarness::isEnabled(), 'Expected chaos testing to be disabled');

        // Re-enable
        ChaosHarness::enable();
        $this->assertTrue(ChaosHarness::isEnabled(), 'Expected chaos testing to be re-enabled');

        // Reset
        ChaosHarness::reset();
        $this->assertFalse(ChaosHarness::isEnabled(), 'Expected chaos testing to be reset');
    }
}
