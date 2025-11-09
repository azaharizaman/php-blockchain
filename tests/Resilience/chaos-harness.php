<?php

declare(strict_types=1);

namespace Blockchain\Tests\Resilience;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

/**
 * ChaosHarness provides fault injection capabilities for resilience testing.
 *
 * This harness allows simulating various failure scenarios including:
 * - Latency injection: Add delays to simulate slow networks
 * - Rate limit spikes: Simulate API rate limiting
 * - Intermittent errors: Random failures to test retry logic
 * - Partial batch failures: Some operations succeed, others fail
 *
 * Usage:
 * ```php
 * // Enable chaos testing via environment variable
 * putenv('CHAOS_TESTING=true');
 *
 * // Create a chaos handler
 * $handler = ChaosHarness::createLatencyScenario(delayMs: 2000);
 *
 * // Use with HTTP client
 * $client = new \GuzzleHttp\Client(['handler' => $handler]);
 * ```
 *
 * @package Blockchain\Tests\Resilience
 */
class ChaosHarness
{
    /**
     * Check if chaos testing is enabled via environment variable.
     *
     * @return bool True if chaos testing is enabled
     */
    public static function isEnabled(): bool
    {
        return getenv('CHAOS_TESTING') === 'true';
    }

    /**
     * Create a scenario with injected latency.
     *
     * This simulates slow network conditions or high server response times.
     * Useful for testing timeout handling and user experience under latency.
     *
     * @param int $delayMs Delay in milliseconds to inject
     * @param array<Response> $responses Optional responses to return after delay
     *
     * @return MockHandler Handler with latency injection
     */
    public static function createLatencyScenario(int $delayMs, array $responses = []): MockHandler
    {
        if (empty($responses)) {
            // Default successful response
            $responses = [
                new Response(200, [], json_encode([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x0'
                ]))
            ];
        }

        // Wrap each response with a delay
        $delayedResponses = array_map(function ($response) use ($delayMs) {
            return function () use ($response, $delayMs) {
                usleep($delayMs * 1000); // Convert ms to microseconds
                return $response;
            };
        }, $responses);

        return new MockHandler($delayedResponses);
    }

    /**
     * Create a scenario simulating rate limit spikes.
     *
     * Simulates API rate limiting with 429 responses followed by successful responses.
     * Tests retry logic and backoff strategies.
     *
     * @param int $rateLimitCount Number of rate limit responses before success
     * @param int $retryAfterSeconds Suggested retry delay in seconds
     *
     * @return MockHandler Handler with rate limit responses
     */
    public static function createRateLimitScenario(
        int $rateLimitCount = 3,
        int $retryAfterSeconds = 1
    ): MockHandler {
        $responses = [];

        // Add rate limit responses
        for ($i = 0; $i < $rateLimitCount; $i++) {
            $responses[] = new Response(429, [
                'Retry-After' => (string)$retryAfterSeconds,
                'X-RateLimit-Remaining' => '0'
            ], json_encode([
                'error' => [
                    'code' => -32005,
                    'message' => 'Rate limit exceeded'
                ]
            ]));
        }

        // Add successful response after rate limits
        $responses[] = new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => '0x1234567890'
        ]));

        return new MockHandler($responses);
    }

    /**
     * Create a scenario with intermittent errors.
     *
     * Randomly fails a percentage of requests to simulate unreliable networks
     * or services. Tests retry policies and error handling.
     *
     * @param int $failureRate Percentage of requests that should fail (0-100)
     * @param int $totalRequests Total number of requests to simulate
     * @param string $errorType Type of error: 'timeout', 'connection', 'server'
     *
     * @return MockHandler Handler with intermittent failures
     */
    public static function createIntermittentErrorScenario(
        int $failureRate = 30,
        int $totalRequests = 10,
        string $errorType = 'server'
    ): MockHandler {
        $responses = [];
        $failureCount = (int)ceil($totalRequests * ($failureRate / 100));
        $successCount = $totalRequests - $failureCount;

        // Create shuffled array of success/failure indicators
        $outcomes = array_merge(
            array_fill(0, $failureCount, 'fail'),
            array_fill(0, $successCount, 'success')
        );
        shuffle($outcomes);

        foreach ($outcomes as $outcome) {
            if ($outcome === 'fail') {
                $responses[] = self::createErrorResponse($errorType);
            } else {
                $responses[] = new Response(200, [], json_encode([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0xabcdef'
                ]));
            }
        }

        return new MockHandler($responses);
    }

    /**
     * Create a scenario with partial batch failures.
     *
     * Simulates batch operations where some succeed and others fail.
     * Tests batch processing error handling and partial success recovery.
     *
     * @param int $batchSize Total number of operations in batch
     * @param int $failureCount Number of operations that should fail
     * @param bool $sequentialFailures If true, failures are sequential; if false, random
     *
     * @return MockHandler Handler with partial batch failures
     */
    public static function createPartialBatchFailureScenario(
        int $batchSize = 10,
        int $failureCount = 3,
        bool $sequentialFailures = false
    ): MockHandler {
        $responses = [];

        if ($sequentialFailures) {
            // First operations fail, rest succeed
            for ($i = 0; $i < $batchSize; $i++) {
                if ($i < $failureCount) {
                    $responses[] = new Response(500, [], json_encode([
                        'error' => [
                            'code' => -32603,
                            'message' => 'Internal error'
                        ]
                    ]));
                } else {
                    $responses[] = new Response(200, [], json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $i,
                        'result' => sprintf('0x%x', $i)
                    ]));
                }
            }
        } else {
            // Random failures
            $outcomes = array_merge(
                array_fill(0, $failureCount, 'fail'),
                array_fill(0, $batchSize - $failureCount, 'success')
            );
            shuffle($outcomes);

            foreach ($outcomes as $i => $outcome) {
                if ($outcome === 'fail') {
                    $responses[] = new Response(500, [], json_encode([
                        'error' => [
                            'code' => -32603,
                            'message' => 'Internal error'
                        ]
                    ]));
                } else {
                    $responses[] = new Response(200, [], json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $i,
                        'result' => sprintf('0x%x', $i)
                    ]));
                }
            }
        }

        return new MockHandler($responses);
    }

    /**
     * Create a scenario combining multiple failure types.
     *
     * Simulates a complex failure scenario with latency, intermittent errors,
     * and rate limiting. Tests comprehensive resilience strategies.
     *
     * @param array<string,mixed> $config Configuration for combined scenario
     *
     * @return MockHandler Handler with combined failures
     */
    public static function createCombinedScenario(array $config = []): MockHandler
    {
        $defaults = [
            'latency_ms' => 100,
            'rate_limit_count' => 2,
            'error_count' => 2,
            'success_count' => 5
        ];

        $config = array_merge($defaults, $config);
        $responses = [];

        // Add rate limit responses
        for ($i = 0; $i < $config['rate_limit_count']; $i++) {
            $responses[] = function () use ($config) {
                usleep($config['latency_ms'] * 1000);
                return new Response(429, ['Retry-After' => '1'], json_encode([
                    'error' => ['code' => -32005, 'message' => 'Rate limit exceeded']
                ]));
            };
        }

        // Add error responses
        for ($i = 0; $i < $config['error_count']; $i++) {
            $responses[] = function () use ($config) {
                usleep($config['latency_ms'] * 1000);
                return new Response(500, [], json_encode([
                    'error' => ['code' => -32603, 'message' => 'Internal error']
                ]));
            };
        }

        // Add success responses
        for ($i = 0; $i < $config['success_count']; $i++) {
            $responses[] = function () use ($config, $i) {
                usleep($config['latency_ms'] * 1000);
                return new Response(200, [], json_encode([
                    'jsonrpc' => '2.0',
                    'id' => $i,
                    'result' => sprintf('0x%x', $i)
                ]));
            };
        }

        return new MockHandler($responses);
    }

    /**
     * Create an error response based on error type.
     *
     * @param string $errorType Type of error: 'timeout', 'connection', 'server'
     *
     * @return Response|callable Error response or exception factory
     */
    private static function createErrorResponse(string $errorType): Response|callable
    {
        return match ($errorType) {
            'timeout' => function (RequestInterface $request) {
                return new ConnectException(
                    'Connection timeout',
                    $request
                );
            },
            'connection' => function (RequestInterface $request) {
                return new ConnectException(
                    'Could not resolve host',
                    $request
                );
            },
            'server' => new Response(500, [], json_encode([
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal JSON-RPC error'
                ]
            ])),
            default => new Response(500, [], json_encode([
                'error' => [
                    'code' => -32603,
                    'message' => 'Unknown error'
                ]
            ]))
        };
    }

    /**
     * Enable chaos testing for the current process.
     *
     * Sets the environment variable to enable chaos scenarios.
     *
     * @return void
     */
    public static function enable(): void
    {
        putenv('CHAOS_TESTING=true');
    }

    /**
     * Disable chaos testing for the current process.
     *
     * Removes the environment variable to disable chaos scenarios.
     *
     * @return void
     */
    public static function disable(): void
    {
        putenv('CHAOS_TESTING=false');
    }

    /**
     * Reset chaos testing state.
     *
     * Unsets the environment variable completely.
     *
     * @return void
     */
    public static function reset(): void
    {
        putenv('CHAOS_TESTING');
    }
}
