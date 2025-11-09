<?php

declare(strict_types=1);

namespace Blockchain\Reliability;

/**
 * Bulkhead implements the bulkhead isolation pattern for concurrent operations.
 *
 * This class limits concurrent executions to prevent resource exhaustion and
 * isolate failures. It acts like a semaphore, allowing a maximum number of
 * concurrent operations and queueing or rejecting excess requests.
 *
 * The bulkhead pattern is named after ship bulkheads that prevent water from
 * flooding the entire vessel. Similarly, this pattern prevents failures in one
 * area from consuming all resources and affecting other areas.
 *
 * @package Blockchain\Reliability
 *
 * @example
 * ```php
 * // Create a bulkhead allowing 5 concurrent operations
 * $bulkhead = new Bulkhead(maxConcurrent: 5);
 *
 * // Execute operation with bulkhead protection
 * try {
 *     $result = $bulkhead->execute(function() {
 *         return $this->makeRpcCall();
 *     });
 * } catch (BulkheadFullException $e) {
 *     // Too many concurrent operations
 * }
 * ```
 */
class Bulkhead
{
    /**
     * Maximum number of concurrent operations allowed.
     */
    private int $maxConcurrent;

    /**
     * Maximum queue size for waiting operations.
     */
    private int $maxQueueSize;

    /**
     * Current number of active operations.
     */
    private int $activeCount = 0;

    /**
     * Queue of waiting operations.
     *
     * @var array<callable>
     */
    private array $queue = [];

    /**
     * Timeout in seconds for waiting in queue.
     */
    private int $queueTimeoutSeconds;

    /**
     * Create a new Bulkhead instance.
     *
     * @param int $maxConcurrent Maximum concurrent operations (must be >= 1)
     * @param int $maxQueueSize Maximum queue size, 0 for no queueing (must be >= 0)
     * @param int $queueTimeoutSeconds Timeout for queue wait (must be > 0)
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        int $maxConcurrent = 10,
        int $maxQueueSize = 0,
        int $queueTimeoutSeconds = 30
    ) {
        if ($maxConcurrent < 1) {
            throw new \InvalidArgumentException('maxConcurrent must be at least 1');
        }
        if ($maxQueueSize < 0) {
            throw new \InvalidArgumentException('maxQueueSize must be non-negative');
        }
        if ($queueTimeoutSeconds <= 0) {
            throw new \InvalidArgumentException('queueTimeoutSeconds must be greater than 0');
        }

        $this->maxConcurrent = $maxConcurrent;
        $this->maxQueueSize = $maxQueueSize;
        $this->queueTimeoutSeconds = $queueTimeoutSeconds;
    }

    /**
     * Execute a callable with bulkhead protection.
     *
     * If the concurrency limit is reached and queueing is disabled, this will
     * throw BulkheadFullException. If queueing is enabled, operations would be queued
     * (not yet implemented). Currently rejects immediately when at capacity.
     *
     * @template T
     * @param callable(): T $operation The operation to execute
     * @return T The result of the operation
     * @throws BulkheadFullException If bulkhead is full and cannot queue
     * @throws \Throwable If the operation fails
     *
     * @example
     * ```php
     * $bulkhead = new Bulkhead(maxConcurrent: 5);
     * $result = $bulkhead->execute(fn() => $this->processRequest());
     * ```
     */
    public function execute(callable $operation)
    {
        // Try to acquire a slot
        if (!$this->tryAcquire()) {
            throw new BulkheadFullException(
                "Bulkhead is full. Active: {$this->activeCount}/{$this->maxConcurrent}, " .
                "Queue: " . count($this->queue) . "/{$this->maxQueueSize}"
            );
        }

        try {
            // Execute the operation
            $result = $operation();
            return $result;
        } finally {
            // Always release the slot
            $this->release();
        }
    }

    /**
     * Try to acquire a slot for execution.
     *
     * Returns true if a slot is immediately available, false otherwise.
     *
     * @return bool True if slot acquired, false if bulkhead is full
     */
    public function tryAcquire(): bool
    {
        if ($this->activeCount < $this->maxConcurrent) {
            $this->activeCount++;
            return true;
        }

        // Check if we can queue
        if ($this->maxQueueSize > 0 && count($this->queue) < $this->maxQueueSize) {
            // In a real implementation with async support, we would queue here
            // For now, we just reject if at capacity
            return false;
        }

        return false;
    }

    /**
     * Release a slot after operation completes.
     *
     * @return void
     */
    public function release(): void
    {
        if ($this->activeCount > 0) {
            $this->activeCount--;
        }
    }

    /**
     * Check if bulkhead has available capacity.
     *
     * @return bool True if capacity is available
     */
    public function hasCapacity(): bool
    {
        return $this->activeCount < $this->maxConcurrent;
    }

    /**
     * Get the number of currently active operations.
     *
     * @return int Number of active operations
     */
    public function getActiveCount(): int
    {
        return $this->activeCount;
    }

    /**
     * Get the maximum concurrent operations allowed.
     *
     * @return int Maximum concurrent operations
     */
    public function getMaxConcurrent(): int
    {
        return $this->maxConcurrent;
    }

    /**
     * Get the number of available slots.
     *
     * @return int Number of available slots
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->maxConcurrent - $this->activeCount);
    }

    /**
     * Get the current queue size.
     *
     * @return int Number of operations in queue
     */
    public function getQueueSize(): int
    {
        return count($this->queue);
    }

    /**
     * Get the maximum queue size.
     *
     * @return int Maximum queue size
     */
    public function getMaxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    /**
     * Get the queue timeout in seconds.
     *
     * @return int Queue timeout in seconds
     */
    public function getQueueTimeoutSeconds(): int
    {
        return $this->queueTimeoutSeconds;
    }

    /**
     * Reset the bulkhead state.
     *
     * This clears all active operations and the queue. Use with caution.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->activeCount = 0;
        $this->queue = [];
    }

    /**
     * Get bulkhead statistics.
     *
     * @return array{
     *     active: int,
     *     maxConcurrent: int,
     *     available: int,
     *     queueSize: int,
     *     maxQueueSize: int,
     *     utilizationPercent: float
     * }
     */
    public function getStats(): array
    {
        return [
            'active' => $this->activeCount,
            'maxConcurrent' => $this->maxConcurrent,
            'available' => $this->getAvailableSlots(),
            'queueSize' => $this->getQueueSize(),
            'maxQueueSize' => $this->maxQueueSize,
            'utilizationPercent' => ($this->activeCount / $this->maxConcurrent) * 100,
        ];
    }
}

/**
 * Exception thrown when bulkhead is full.
 */
class BulkheadFullException extends \RuntimeException
{
}
