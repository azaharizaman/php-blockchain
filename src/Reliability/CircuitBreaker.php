<?php

declare(strict_types=1);

namespace Blockchain\Reliability;

/**
 * CircuitBreaker implements the circuit breaker pattern to prevent cascading failures.
 *
 * This class protects downstream services by tracking failures and opening the circuit
 * when a threshold is reached. It supports three states:
 * - Closed: Normal operation, requests pass through
 * - Open: Circuit is open, requests fail fast without calling downstream
 * - Half-Open: Testing if service has recovered
 *
 * The circuit breaker uses a sliding window to track failures and automatically
 * attempts to recover after a cooldown period.
 *
 * @package Blockchain\Reliability
 *
 * @example
 * ```php
 * // Create a circuit breaker with 5 failure threshold in 60s window
 * $breaker = new CircuitBreaker(
 *     failureThreshold: 5,
 *     windowSizeSeconds: 60,
 *     cooldownSeconds: 30
 * );
 *
 * // Execute operation with circuit breaker protection
 * try {
 *     $result = $breaker->call(function() {
 *         return $this->makeRpcCall();
 *     });
 * } catch (CircuitBreakerOpenException $e) {
 *     // Circuit is open, fail fast
 * }
 * ```
 */
class CircuitBreaker
{
    /**
     * Circuit breaker states
     */
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Number of failures required to open the circuit.
     */
    private int $failureThreshold;

    /**
     * Time window in seconds for counting failures.
     */
    private int $windowSizeSeconds;

    /**
     * Cooldown period in seconds before attempting recovery.
     */
    private int $cooldownSeconds;

    /**
     * Success threshold for closing circuit from half-open state.
     */
    private int $successThreshold;

    /**
     * Current state of the circuit breaker.
     */
    private string $state = self::STATE_CLOSED;

    /**
     * Timestamps of failures within the current window.
     *
     * @var array<float>
     */
    private array $failures = [];

    /**
     * Number of consecutive successes in half-open state.
     */
    private int $consecutiveSuccesses = 0;

    /**
     * Timestamp when the circuit was opened.
     */
    private ?float $openedAt = null;

    /**
     * Whether circuit is forced open for maintenance.
     */
    private bool $forcedOpen = false;

    /**
     * Create a new CircuitBreaker instance.
     *
     * @param int $failureThreshold Number of failures to open circuit (must be >= 1)
     * @param int $windowSizeSeconds Time window for counting failures (must be > 0)
     * @param int $cooldownSeconds Cooldown period before retry (must be > 0)
     * @param int $successThreshold Successes needed to close from half-open (must be >= 1)
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        int $failureThreshold = 5,
        int $windowSizeSeconds = 60,
        int $cooldownSeconds = 30,
        int $successThreshold = 2
    ) {
        if ($failureThreshold < 1) {
            throw new \InvalidArgumentException('failureThreshold must be at least 1');
        }
        if ($windowSizeSeconds <= 0) {
            throw new \InvalidArgumentException('windowSizeSeconds must be greater than 0');
        }
        if ($cooldownSeconds <= 0) {
            throw new \InvalidArgumentException('cooldownSeconds must be greater than 0');
        }
        if ($successThreshold < 1) {
            throw new \InvalidArgumentException('successThreshold must be at least 1');
        }

        $this->failureThreshold = $failureThreshold;
        $this->windowSizeSeconds = $windowSizeSeconds;
        $this->cooldownSeconds = $cooldownSeconds;
        $this->successThreshold = $successThreshold;
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * The circuit breaker will fail fast if the circuit is open, otherwise
     * it will execute the operation and track success/failure.
     *
     * @template T
     * @param callable(): T $operation The operation to execute
     * @return T The result of the operation
     * @throws CircuitBreakerOpenException If the circuit is open
     * @throws \Throwable If the operation fails
     *
     * @example
     * ```php
     * $breaker = new CircuitBreaker(failureThreshold: 3);
     * $result = $breaker->call(fn() => $this->httpClient->get('/api/data'));
     * ```
     */
    public function call(callable $operation)
    {
        // Check if circuit should transition from open to half-open
        if ($this->state === self::STATE_OPEN && !$this->forcedOpen) {
            $this->tryHalfOpen();
        }

        // Fail fast if circuit is open
        if ($this->isOpen()) {
            throw new CircuitBreakerOpenException(
                'Circuit breaker is open. Service is unavailable.'
            );
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Record a successful operation.
     *
     * In half-open state, consecutive successes will close the circuit.
     * In closed state, this resets any accumulated failures.
     *
     * @return void
     */
    public function recordSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->consecutiveSuccesses++;
            
            if ($this->consecutiveSuccesses >= $this->successThreshold) {
                $this->close();
            }
        } elseif ($this->state === self::STATE_CLOSED) {
            // Reset failures on success in closed state
            $this->failures = [];
        }
    }

    /**
     * Record a failed operation.
     *
     * Failures are tracked within a sliding time window. If the failure
     * threshold is reached or exceeded, the circuit opens.
     *
     * @return void
     */
    public function recordFailure(): void
    {
        $now = $this->getCurrentTime();
        
        // In half-open state, any failure immediately reopens the circuit
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->open();
            return;
        }

        // Add failure timestamp
        $this->failures[] = $now;
        
        // Remove old failures outside the window
        $this->pruneOldFailures();

        // Check if threshold exceeded
        if (count($this->failures) >= $this->failureThreshold) {
            $this->open();
        }
    }

    /**
     * Force the circuit breaker open for maintenance.
     *
     * When forced open, the circuit will not automatically transition to
     * half-open even after the cooldown period. Use close() to reset.
     *
     * @return void
     */
    public function forceOpen(): void
    {
        $this->forcedOpen = true;
        $this->state = self::STATE_OPEN;
        $this->openedAt = $this->getCurrentTime();
    }

    /**
     * Close the circuit breaker and reset state.
     *
     * This manually closes the circuit and clears all failure tracking.
     *
     * @return void
     */
    public function close(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failures = [];
        $this->consecutiveSuccesses = 0;
        $this->openedAt = null;
        $this->forcedOpen = false;
    }

    /**
     * Open the circuit breaker.
     *
     * @return void
     */
    private function open(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = $this->getCurrentTime();
        $this->consecutiveSuccesses = 0;
    }

    /**
     * Try to transition from open to half-open state.
     *
     * This is called after the cooldown period has elapsed.
     *
     * @return void
     */
    private function tryHalfOpen(): void
    {
        if ($this->openedAt === null) {
            return;
        }

        $now = $this->getCurrentTime();
        $cooldownElapsed = ($now - $this->openedAt) >= $this->cooldownSeconds;

        if ($cooldownElapsed) {
            $this->state = self::STATE_HALF_OPEN;
            $this->consecutiveSuccesses = 0;
        }
    }

    /**
     * Remove failures outside the current time window.
     *
     * @return void
     */
    private function pruneOldFailures(): void
    {
        $now = $this->getCurrentTime();
        $windowStart = $now - $this->windowSizeSeconds;

        $this->failures = array_values(
            array_filter($this->failures, fn($timestamp) => $timestamp >= $windowStart)
        );
    }

    /**
     * Check if the circuit is open.
     *
     * @return bool True if circuit is open
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Check if the circuit is closed.
     *
     * @return bool True if circuit is closed
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Check if the circuit is half-open.
     *
     * @return bool True if circuit is half-open
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Get the current state of the circuit breaker.
     *
     * @return string One of STATE_CLOSED, STATE_OPEN, or STATE_HALF_OPEN
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get the number of failures in the current window.
     *
     * @return int Number of failures
     */
    public function getFailureCount(): int
    {
        $this->pruneOldFailures();
        return count($this->failures);
    }

    /**
     * Get the failure threshold.
     *
     * @return int Failure threshold
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Get the window size in seconds.
     *
     * @return int Window size in seconds
     */
    public function getWindowSizeSeconds(): int
    {
        return $this->windowSizeSeconds;
    }

    /**
     * Get the cooldown period in seconds.
     *
     * @return int Cooldown period in seconds
     */
    public function getCooldownSeconds(): int
    {
        return $this->cooldownSeconds;
    }

    /**
     * Get the success threshold for closing from half-open.
     *
     * @return int Success threshold
     */
    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    /**
     * Check if circuit is forced open.
     *
     * @return bool True if forced open
     */
    public function isForcedOpen(): bool
    {
        return $this->forcedOpen;
    }

    /**
     * Get the current time in seconds.
     *
     * This method is extracted to allow mocking in tests.
     *
     * @return float Current time in seconds
     */
    protected function getCurrentTime(): float
    {
        return microtime(true);
    }
}

/**
 * Exception thrown when circuit breaker is open.
 */
class CircuitBreakerOpenException extends \RuntimeException
{
}
