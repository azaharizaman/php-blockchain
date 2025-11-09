<?php

declare(strict_types=1);

namespace Blockchain\Reliability;

/**
 * RateLimiter implements a token-bucket algorithm for client-side rate limiting.
 *
 * This class provides in-memory rate limiting to protect downstream RPC endpoints
 * from being overwhelmed. It uses the token-bucket algorithm where:
 * - Tokens are added at a fixed rate (refill rate)
 * - Each request consumes one or more tokens
 * - Requests are blocked when insufficient tokens are available
 *
 * The rate limiter respects HTTP 429 responses and can be configured per-driver
 * or per-transport adapter.
 *
 * @package Blockchain\Reliability
 *
 * @example
 * ```php
 * // Allow 10 requests per second with a burst of 20
 * $limiter = new RateLimiter(requestsPerSecond: 10, bucketCapacity: 20);
 *
 * // Wait for permission to make a request
 * $limiter->acquire();
 * // Make your RPC call...
 *
 * // Or check if we can make a request without blocking
 * if ($limiter->tryAcquire()) {
 *     // Make your RPC call...
 * }
 * ```
 */
class RateLimiter
{
    /**
     * Number of requests allowed per second.
     */
    private float $requestsPerSecond;

    /**
     * Maximum number of tokens the bucket can hold (burst capacity).
     */
    private int $bucketCapacity;

    /**
     * Current number of available tokens.
     */
    private float $availableTokens;

    /**
     * Timestamp of the last token refill (in microseconds).
     */
    private float $lastRefillTime;

    /**
     * Create a new RateLimiter instance.
     *
     * @param float $requestsPerSecond Number of requests allowed per second (must be > 0)
     * @param int|null $bucketCapacity Maximum burst capacity. If null, defaults to requestsPerSecond
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function __construct(float $requestsPerSecond, ?int $bucketCapacity = null)
    {
        if ($requestsPerSecond <= 0) {
            throw new \InvalidArgumentException('requestsPerSecond must be greater than 0');
        }

        $this->requestsPerSecond = $requestsPerSecond;
        $this->bucketCapacity = $bucketCapacity ?? (int) ceil($requestsPerSecond);

        if ($this->bucketCapacity < 1) {
            throw new \InvalidArgumentException('bucketCapacity must be at least 1');
        }

        // Start with a full bucket
        $this->availableTokens = (float) $this->bucketCapacity;
        $this->lastRefillTime = $this->getCurrentTime();
    }

    /**
     * Acquire permission to make a request, blocking if necessary.
     *
     * This method will wait until a token becomes available. It refills tokens
     * based on the time elapsed since the last refill.
     *
     * @param int $tokens Number of tokens to acquire (default: 1)
     * @return void
     * @throws \InvalidArgumentException If tokens is less than 1
     *
     * @example
     * ```php
     * $limiter = new RateLimiter(requestsPerSecond: 10);
     * $limiter->acquire(); // Wait if necessary, then proceed
     * // Make your request...
     * ```
     */
    public function acquire(int $tokens = 1): void
    {
        if ($tokens < 1) {
            throw new \InvalidArgumentException('tokens must be at least 1');
        }

        while (!$this->tryAcquire($tokens)) {
            // Calculate how long to wait for the next token
            $waitTimeMs = (int) ceil((1.0 / $this->requestsPerSecond) * 1000);
            $this->delay($waitTimeMs);
        }
    }

    /**
     * Try to acquire permission to make a request without blocking.
     *
     * This method attempts to consume the specified number of tokens.
     * If sufficient tokens are available, it returns true immediately.
     * Otherwise, it returns false without blocking.
     *
     * @param int $tokens Number of tokens to try to acquire (default: 1)
     * @return bool True if tokens were acquired, false otherwise
     * @throws \InvalidArgumentException If tokens is less than 1
     *
     * @example
     * ```php
     * $limiter = new RateLimiter(requestsPerSecond: 10);
     * if ($limiter->tryAcquire()) {
     *     // Make your request...
     * } else {
     *     // Rate limit exceeded, handle accordingly
     * }
     * ```
     */
    public function tryAcquire(int $tokens = 1): bool
    {
        if ($tokens < 1) {
            throw new \InvalidArgumentException('tokens must be at least 1');
        }

        $this->refill();

        if ($this->availableTokens >= $tokens) {
            $this->availableTokens -= $tokens;
            return true;
        }

        return false;
    }

    /**
     * Get the current number of available tokens.
     *
     * This method refills tokens before returning the count, so it represents
     * the current state including any tokens that should have been added since
     * the last operation.
     *
     * @return float Number of available tokens
     */
    public function getAvailableTokens(): float
    {
        $this->refill();
        return $this->availableTokens;
    }

    /**
     * Get the requests per second rate.
     *
     * @return float Requests per second
     */
    public function getRequestsPerSecond(): float
    {
        return $this->requestsPerSecond;
    }

    /**
     * Get the bucket capacity.
     *
     * @return int Maximum number of tokens
     */
    public function getBucketCapacity(): int
    {
        return $this->bucketCapacity;
    }

    /**
     * Reset the rate limiter to a full bucket.
     *
     * This method is useful for testing or when you need to reset the rate limiter state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->availableTokens = (float) $this->bucketCapacity;
        $this->lastRefillTime = $this->getCurrentTime();
    }

    /**
     * Refill tokens based on elapsed time since last refill.
     *
     * This method calculates how many tokens should be added based on the
     * time elapsed and the configured requests per second rate.
     *
     * @return void
     */
    private function refill(): void
    {
        $now = $this->getCurrentTime();
        $elapsedSeconds = ($now - $this->lastRefillTime) / 1_000_000.0;

        // Calculate tokens to add based on elapsed time
        $tokensToAdd = $elapsedSeconds * $this->requestsPerSecond;

        if ($tokensToAdd > 0) {
            $this->availableTokens = min(
                $this->availableTokens + $tokensToAdd,
                (float) $this->bucketCapacity
            );
            $this->lastRefillTime = $now;
        }
    }

    /**
     * Get the current time in microseconds.
     *
     * This method is extracted to allow mocking in tests.
     *
     * @return float Current time in microseconds
     */
    protected function getCurrentTime(): float
    {
        return microtime(true) * 1_000_000.0;
    }

    /**
     * Delay execution for the specified number of milliseconds.
     *
     * This method is extracted to allow mocking in tests.
     *
     * @param int $milliseconds Delay duration in milliseconds
     * @return void
     */
    protected function delay(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
