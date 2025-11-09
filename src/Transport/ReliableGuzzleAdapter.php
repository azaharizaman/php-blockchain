<?php

declare(strict_types=1);

namespace Blockchain\Transport;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\ValidationException;
use Blockchain\Reliability\RetryPolicy;
use Blockchain\Reliability\RateLimiter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * ReliableGuzzleAdapter wraps GuzzleAdapter with retry and rate limiting.
 *
 * This adapter extends GuzzleAdapter functionality with:
 * - Automatic retry on transient failures with exponential backoff
 * - Client-side rate limiting to protect RPC endpoints
 * - Configurable policies for different use cases
 *
 * @package Blockchain\Transport
 *
 * @example
 * ```php
 * use Blockchain\Transport\ReliableGuzzleAdapter;
 * use Blockchain\Reliability\RetryPolicy;
 * use Blockchain\Reliability\RateLimiter;
 *
 * // Create adapter with retry and rate limiting
 * $adapter = new ReliableGuzzleAdapter(
 *     retryPolicy: new RetryPolicy(maxAttempts: 3, baseDelayMs: 100),
 *     rateLimiter: new RateLimiter(requestsPerSecond: 10.0)
 * );
 *
 * // Use like normal GuzzleAdapter - retries and rate limiting are automatic
 * $result = $adapter->post('https://api.example.com/rpc', ['method' => 'eth_blockNumber']);
 * ```
 */
class ReliableGuzzleAdapter extends GuzzleAdapter
{
    /**
     * Retry policy for handling transient failures.
     */
    private ?RetryPolicy $retryPolicy;

    /**
     * Rate limiter for controlling request rate.
     */
    private ?RateLimiter $rateLimiter;

    /**
     * Create a new ReliableGuzzleAdapter instance.
     *
     * @param Client|null $client Optional Guzzle client instance
     * @param array<string,mixed> $config Optional configuration
     * @param RetryPolicy|null $retryPolicy Optional retry policy. If null, no retries are performed.
     * @param RateLimiter|null $rateLimiter Optional rate limiter. If null, no rate limiting is applied.
     *
     * @example
     * ```php
     * // With retry only
     * $adapter = new ReliableGuzzleAdapter(
     *     retryPolicy: new RetryPolicy(maxAttempts: 5, baseDelayMs: 200)
     * );
     *
     * // With rate limiting only
     * $adapter = new ReliableGuzzleAdapter(
     *     rateLimiter: new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 20)
     * );
     *
     * // With both retry and rate limiting
     * $adapter = new ReliableGuzzleAdapter(
     *     retryPolicy: new RetryPolicy(maxAttempts: 3),
     *     rateLimiter: new RateLimiter(requestsPerSecond: 5.0)
     * );
     * ```
     */
    public function __construct(
        ?Client $client = null,
        array $config = [],
        ?RetryPolicy $retryPolicy = null,
        ?RateLimiter $rateLimiter = null
    ) {
        parent::__construct($client, $config);
        $this->retryPolicy = $retryPolicy;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Perform a GET request with retry and rate limiting.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $options Additional request options
     * @return array<string,mixed> The decoded JSON response
     * @throws ConfigurationException If the request fails due to network/configuration issues
     * @throws ValidationException If the request fails due to client errors (4xx)
     * @throws TransactionException If the request fails due to server errors (5xx)
     */
    public function get(string $url, array $options = []): array
    {
        return $this->executeWithPolicies(
            fn() => parent::get($url, $options)
        );
    }

    /**
     * Perform a POST request with retry and rate limiting.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $data The data to send in the request body
     * @param array<string,mixed> $options Additional request options
     * @return array<string,mixed> The decoded JSON response
     * @throws ConfigurationException If the request fails due to network/configuration issues
     * @throws ValidationException If the request fails due to client errors (4xx)
     * @throws TransactionException If the request fails due to server errors (5xx)
     */
    public function post(string $url, array $data, array $options = []): array
    {
        return $this->executeWithPolicies(
            fn() => parent::post($url, $data, $options)
        );
    }

    /**
     * Execute a callable with retry policy and rate limiting.
     *
     * @template T
     * @param callable(): T $operation The operation to execute
     * @return T The result of the operation
     * @throws \Throwable If the operation fails after all retries
     */
    private function executeWithPolicies(callable $operation)
    {
        // Apply rate limiting first (before attempting the operation)
        if ($this->rateLimiter !== null) {
            $this->rateLimiter->acquire();
        }

        // Apply retry policy if configured
        if ($this->retryPolicy !== null) {
            return $this->retryPolicy->execute(
                $operation,
                $this->getRetryableExceptions()
            );
        }

        // No retry policy, execute once
        return $operation();
    }

    /**
     * Get the list of exceptions that should trigger retries.
     *
     * By default, only transient network errors and rate limit errors are retryable.
     * Validation errors (4xx) and other errors are not retried.
     *
     * @return array<class-string<\Throwable>> List of retryable exception classes
     */
    private function getRetryableExceptions(): array
    {
        return [
            ConnectException::class,      // Network connection failures
            RequestException::class,      // Request timeout, etc.
            TransactionException::class,  // Server errors (5xx) - may be transient
        ];
    }

    /**
     * Get the configured retry policy.
     *
     * @return RetryPolicy|null The retry policy or null if not configured
     */
    public function getRetryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * Get the configured rate limiter.
     *
     * @return RateLimiter|null The rate limiter or null if not configured
     */
    public function getRateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }
}
