<?php

declare(strict_types=1);

namespace Blockchain\Reliability;

/**
 * RetryPolicy implements exponential backoff with jitter for resilient operations.
 *
 * This class provides configurable retry logic with exponential backoff and jitter
 * to handle transient failures in blockchain RPC operations. It supports:
 * - Exponential backoff with configurable multiplier
 * - Jitter to prevent thundering herd problem
 * - Maximum retry attempts cap
 * - Maximum delay cap
 *
 * @package Blockchain\Reliability
 *
 * @example
 * ```php
 * // Create a retry policy with 3 attempts, 100ms base delay, 2x backoff
 * $policy = new RetryPolicy(maxAttempts: 3, baseDelayMs: 100, backoffMultiplier: 2.0);
 *
 * // Execute an operation with retry
 * $result = $policy->execute(function() {
 *     // Your operation that might fail
 *     return $this->makeRpcCall();
 * });
 *
 * // With jitter to randomize delays
 * $policy = new RetryPolicy(maxAttempts: 5, baseDelayMs: 100, jitterMs: 50);
 * ```
 */
class RetryPolicy
{
    /**
     * Maximum number of retry attempts (including the initial attempt).
     */
    private int $maxAttempts;

    /**
     * Base delay in milliseconds before the first retry.
     */
    private int $baseDelayMs;

    /**
     * Exponential backoff multiplier applied to each subsequent retry.
     */
    private float $backoffMultiplier;

    /**
     * Jitter in milliseconds to add randomness to delays.
     */
    private int $jitterMs;

    /**
     * Maximum delay in milliseconds to cap exponential growth.
     */
    private int $maxDelayMs;

    /**
     * Create a new RetryPolicy instance.
     *
     * @param int $maxAttempts Maximum number of attempts (must be >= 1)
     * @param int $baseDelayMs Base delay in milliseconds (must be >= 0)
     * @param float $backoffMultiplier Exponential backoff multiplier (must be >= 1.0)
     * @param int $jitterMs Maximum jitter to add in milliseconds (must be >= 0)
     * @param int $maxDelayMs Maximum delay cap in milliseconds (must be >= baseDelayMs)
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        int $maxAttempts = 3,
        int $baseDelayMs = 100,
        float $backoffMultiplier = 2.0,
        int $jitterMs = 0,
        int $maxDelayMs = 30000
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1');
        }
        if ($baseDelayMs < 0) {
            throw new \InvalidArgumentException('baseDelayMs must be non-negative');
        }
        if ($backoffMultiplier < 1.0) {
            throw new \InvalidArgumentException('backoffMultiplier must be at least 1.0');
        }
        if ($jitterMs < 0) {
            throw new \InvalidArgumentException('jitterMs must be non-negative');
        }
        if ($maxDelayMs < $baseDelayMs) {
            throw new \InvalidArgumentException('maxDelayMs must be >= baseDelayMs');
        }

        $this->maxAttempts = $maxAttempts;
        $this->baseDelayMs = $baseDelayMs;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->jitterMs = $jitterMs;
        $this->maxDelayMs = $maxDelayMs;
    }

    /**
     * Execute a callable with retry logic.
     *
     * The callable will be retried up to maxAttempts times with exponential backoff.
     * Only exceptions specified in $retryableExceptions will trigger retries.
     *
     * @template T
     * @param callable(): T $operation The operation to execute
     * @param array<class-string<\Throwable>> $retryableExceptions List of exception classes to retry on
     * @return T The result of the operation
     * @throws \Throwable If all retry attempts fail, the last exception is thrown
     *
     * @example
     * ```php
     * $policy = new RetryPolicy(maxAttempts: 3);
     * $result = $policy->execute(
     *     fn() => $this->httpClient->get('/data'),
     *     [\GuzzleHttp\Exception\ConnectException::class]
     * );
     * ```
     */
    public function execute(callable $operation, array $retryableExceptions = [\Exception::class])
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                // Check if this exception is retryable
                if (!$this->isRetryable($e, $retryableExceptions)) {
                    throw $e;
                }

                // Don't delay after the last failed attempt
                if ($attempt < $this->maxAttempts) {
                    $delayMs = $this->calculateDelay($attempt);
                    $this->delay($delayMs);
                }
            }
        }

        // All attempts failed, throw the last exception
        throw $lastException;
    }

    /**
     * Calculate the delay for a given attempt number with exponential backoff and jitter.
     *
     * @param int $attempt The current attempt number (1-indexed)
     * @return int The delay in milliseconds
     */
    public function calculateDelay(int $attempt): int
    {
        // Calculate exponential backoff: baseDelay * (multiplier ^ (attempt - 1))
        $exponentialDelay = $this->baseDelayMs * pow($this->backoffMultiplier, $attempt - 1);

        // Apply max delay cap
        $exponentialDelay = min($exponentialDelay, $this->maxDelayMs);

        // Add random jitter
        $jitter = $this->jitterMs > 0 ? random_int(0, $this->jitterMs) : 0;

        return (int) ($exponentialDelay + $jitter);
    }

    /**
     * Get the maximum number of attempts.
     *
     * @return int Maximum attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the base delay in milliseconds.
     *
     * @return int Base delay in milliseconds
     */
    public function getBaseDelayMs(): int
    {
        return $this->baseDelayMs;
    }

    /**
     * Get the backoff multiplier.
     *
     * @return float Backoff multiplier
     */
    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    /**
     * Get the jitter in milliseconds.
     *
     * @return int Jitter in milliseconds
     */
    public function getJitterMs(): int
    {
        return $this->jitterMs;
    }

    /**
     * Get the maximum delay in milliseconds.
     *
     * @return int Maximum delay in milliseconds
     */
    public function getMaxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    /**
     * Check if an exception is retryable.
     *
     * @param \Throwable $exception The exception to check
     * @param array<class-string<\Throwable>> $retryableExceptions List of retryable exception classes
     * @return bool True if the exception should trigger a retry
     */
    private function isRetryable(\Throwable $exception, array $retryableExceptions): bool
    {
        foreach ($retryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }
        return false;
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
