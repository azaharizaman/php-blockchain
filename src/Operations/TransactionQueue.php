<?php

declare(strict_types=1);

namespace Blockchain\Operations;

use Throwable;

/**
 * TransactionQueue
 *
 * In-memory transaction queue that supports enqueue, dequeue, retry scheduling,
 * and exponential backoff for orchestrating transaction broadcasts deterministically
 * in high-volume environments.
 *
 * ## Key Responsibilities
 *
 * 1. **Queue Management**: Maintains pending transactions in an ordered queue
 *    with respect to retry timing constraints.
 *
 * 2. **Retry Scheduling**: Implements exponential backoff for failed transactions
 *    with configurable max attempts and backoff parameters.
 *
 * 3. **Timing Control**: Respects nextAvailableAt timestamps to prevent premature
 *    dequeue operations during backoff periods.
 *
 * 4. **Observability**: Provides PSR-3 compatible logging without exposing
 *    sensitive payload data (SEC-001 compliance).
 *
 * ## Usage Examples
 *
 * ### Basic Queue Operations
 *
 * ```php
 * use Blockchain\Operations\TransactionQueue;
 * use Blockchain\Operations\TransactionJob;
 *
 * $queue = new TransactionQueue([
 *     'maxAttempts' => 5,
 *     'baseBackoffSeconds' => 2,
 *     'maxBackoffSeconds' => 300
 * ]);
 *
 * // Enqueue a job
 * $job = new TransactionJob(
 *     id: 'tx-123',
 *     payload: ['to' => '0x...', 'amount' => 1.0],
 *     metadata: ['from' => '0x...']
 * );
 * $queue->enqueue($job);
 *
 * // Dequeue when available
 * $next = $queue->dequeue(); // null if nextAvailableAt is in future
 * ```
 *
 * ### Retry and Failure Handling
 *
 * ```php
 * try {
 *     // Attempt transaction broadcast
 *     $driver->broadcast($job->getPayload());
 *     $queue->acknowledge($job);
 * } catch (Exception $e) {
 *     $queue->recordFailure($job, $e);
 *     // Job is re-enqueued with exponential backoff
 * }
 * ```
 *
 * ### With Custom Clock and Jitter
 *
 * ```php
 * $queue = new TransactionQueue(
 *     options: ['maxAttempts' => 3],
 *     clockFn: fn() => $fakeClock->now(),
 *     jitterFn: fn($delay) => $delay + random_int(0, 1)
 * );
 * ```
 *
 * ## Security Considerations (SEC-001)
 *
 * - Payload data is NEVER logged
 * - Only job IDs and metadata summaries are included in logs
 * - Failure reasons are logged but payload contents are excluded
 *
 * @package Blockchain\Operations
 */
class TransactionQueue
{
    /**
     * Internal queue storage
     *
     * @var \SplQueue<TransactionJob>
     */
    private \SplQueue $queue;

    /**
     * Maximum retry attempts before giving up
     */
    private int $maxAttempts;

    /**
     * Base backoff in seconds for exponential calculation
     */
    private int $baseBackoffSeconds;

    /**
     * Maximum backoff delay in seconds (cap to prevent overflow)
     */
    private int $maxBackoffSeconds;

    /**
     * Clock function for time injection (testing support)
     *
     * @var callable(): int
     */
    private $clockFn;

    /**
     * Jitter function for backoff randomization
     *
     * @var callable(int): int
     */
    private $jitterFn;

    /**
     * Logger instance (optional)
     */
    private ?LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param array<string,mixed> $options Queue configuration options
     * @param callable|null $clockFn Custom clock function returning current timestamp
     * @param callable|null $jitterFn Custom jitter function for backoff randomization
     * @param LoggerInterface|null $logger PSR-3 logger for observability
     */
    public function __construct(
        array $options = [],
        ?callable $clockFn = null,
        ?callable $jitterFn = null,
        ?LoggerInterface $logger = null
    ) {
        $this->queue = new \SplQueue();
        $this->maxAttempts = $options['maxAttempts'] ?? 5;
        $this->baseBackoffSeconds = $options['baseBackoffSeconds'] ?? 2;
        $this->maxBackoffSeconds = $options['maxBackoffSeconds'] ?? 300;
        $this->clockFn = $clockFn ?? fn() => time();
        $this->jitterFn = $jitterFn ?? fn(int $delay) => $delay;
        $this->logger = $logger;
    }

    /**
     * Enqueue a transaction job
     *
     * Adds a new job to the queue. The job will be available for dequeue
     * only after its nextAvailableAt timestamp has passed.
     *
     * @param TransactionJob $job Job to enqueue
     *
     * @return void
     */
    public function enqueue(TransactionJob $job): void
    {
        $this->queue->enqueue($job);
        $this->logger?->debug('Job enqueued', [
            'jobId' => $job->getId(),
            'attempts' => $job->getAttempts(),
            'nextAvailableAt' => $job->getNextAvailableAt(),
        ]);
    }

    /**
     * Dequeue a transaction job if available
     *
     * Returns the next available job that is ready for processing (i.e., its
     * nextAvailableAt timestamp has passed). Returns null if no jobs are ready.
     *
     * This method scans the queue and temporarily removes items that aren't
     * ready yet, then re-enqueues them. This ensures proper ordering while
     * respecting timing constraints.
     *
     * @return TransactionJob|null Next available job or null if none ready
     */
    public function dequeue(): ?TransactionJob
    {
        if ($this->queue->isEmpty()) {
            return null;
        }

        $currentTime = ($this->clockFn)();
        $tempQueue = new \SplQueue();
        $found = null;

        // Scan queue for first available job
        while (!$this->queue->isEmpty()) {
            $job = $this->queue->dequeue();

            if ($found === null && $job->getNextAvailableAt() <= $currentTime) {
                $found = $job;
                $this->logger?->debug('Job dequeued', [
                    'jobId' => $job->getId(),
                    'attempts' => $job->getAttempts(),
                ]);
            } else {
                $tempQueue->enqueue($job);
            }
        }

        // Re-enqueue jobs that weren't ready
        while (!$tempQueue->isEmpty()) {
            $this->queue->enqueue($tempQueue->dequeue());
        }

        return $found;
    }

    /**
     * Record a failure and schedule retry with backoff
     *
     * Increments the job's attempt count and calculates the next retry time
     * using exponential backoff. If max attempts is exceeded, the job is
     * not re-enqueued (effectively dropped).
     *
     * **Security Note**: The failure reason is logged but payload data is NOT.
     *
     * @param TransactionJob $job Job that failed
     * @param Throwable $reason Failure exception
     *
     * @return void
     */
    public function recordFailure(TransactionJob $job, Throwable $reason): void
    {
        $newAttempts = $job->getAttempts() + 1;

        $this->logger?->warning('Job failed', [
            'jobId' => $job->getId(),
            'attempts' => $newAttempts,
            'maxAttempts' => $this->maxAttempts,
            'reason' => $reason->getMessage(),
            // SEC-001: payload is NOT logged
        ]);

        if ($newAttempts >= $this->maxAttempts) {
            $this->logger?->error('Job exhausted max attempts', [
                'jobId' => $job->getId(),
                'attempts' => $newAttempts,
            ]);
            return; // Don't re-enqueue
        }

        // Calculate exponential backoff: baseSeconds * 2^attempt
        $delay = $this->calculateBackoff($newAttempts);
        $nextAvailableAt = ($this->clockFn)() + $delay;

        // Create new job with updated attempts and nextAvailableAt
        $retriedJob = new TransactionJob(
            id: $job->getId(),
            payload: $job->getPayload(),
            metadata: $job->getMetadata(),
            attempts: $newAttempts,
            nextAvailableAt: $nextAvailableAt,
            idempotencyToken: $job->getIdempotencyToken()
        );

        $this->enqueue($retriedJob);

        $this->logger?->info('Job scheduled for retry', [
            'jobId' => $job->getId(),
            'attempts' => $newAttempts,
            'delaySeconds' => $delay,
            'nextAvailableAt' => $nextAvailableAt,
        ]);
    }

    /**
     * Acknowledge successful job completion
     *
     * Marks a job as successfully completed. Since we use an in-memory queue,
     * acknowledgment is implicit (job is not re-enqueued after dequeue).
     * This method is provided for API completeness and future extensibility.
     *
     * @param TransactionJob $job Job to acknowledge
     *
     * @return void
     */
    public function acknowledge(TransactionJob $job): void
    {
        $this->logger?->info('Job acknowledged', [
            'jobId' => $job->getId(),
            'attempts' => $job->getAttempts(),
        ]);
        // In memory implementation: job is already removed from queue
        // Future implementations may need to mark as complete in persistent store
    }

    /**
     * Get current queue size
     *
     * @return int Number of jobs in queue
     */
    public function size(): int
    {
        return $this->queue->count();
    }

    /**
     * Calculate exponential backoff with jitter
     *
     * Implements exponential backoff: baseSeconds * 2^(attempts-1)
     * Applies jitter function for randomization and caps at maxBackoffSeconds.
     *
     * @param int $attempts Current attempt count
     *
     * @return int Delay in seconds
     */
    private function calculateBackoff(int $attempts): int
    {
        // Calculate base exponential backoff
        $exponent = $attempts - 1;

        // Prevent overflow: cap exponent to reasonable value
        $exponent = min($exponent, 20);

        $delay = $this->baseBackoffSeconds * (2 ** $exponent);

        // Cap at maximum backoff
        $delay = min($delay, $this->maxBackoffSeconds);

        // Apply jitter
        $delay = ($this->jitterFn)((int)$delay);

        return (int)$delay;
    }
}

/**
 * TransactionJob
 *
 * Value object representing a queued transaction with retry metadata.
 * Immutable by design to ensure job state consistency.
 *
 * @package Blockchain\Operations
 */
class TransactionJob
{
    /**
     * Constructor
     *
     * @param string $id Unique job identifier
     * @param array<string,mixed> $payload Transaction payload data
     * @param array<string,mixed> $metadata Job metadata (non-sensitive)
     * @param int $attempts Number of attempts made
     * @param int $nextAvailableAt Timestamp when job can be dequeued
     * @param string|null $idempotencyToken Optional idempotency token (for TASK-005)
     */
    public function __construct(
        private readonly string $id,
        private readonly array $payload,
        private readonly array $metadata = [],
        private readonly int $attempts = 0,
        private readonly int $nextAvailableAt = 0,
        private readonly ?string $idempotencyToken = null
    ) {
    }

    /**
     * Get job ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get transaction payload
     *
     * **Security Note**: Payload may contain sensitive data.
     * Never log or expose this directly.
     *
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get job metadata
     *
     * @return array<string,mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get attempt count
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Get next available timestamp
     *
     * @return int Unix timestamp
     */
    public function getNextAvailableAt(): int
    {
        return $this->nextAvailableAt;
    }

    /**
     * Get idempotency token (placeholder for TASK-005)
     *
     * @return string|null
     */
    public function getIdempotencyToken(): ?string
    {
        return $this->idempotencyToken;
    }
}

/**
 * LoggerInterface
 *
 * Minimal PSR-3 compatible logger interface for TransactionQueue.
 * This avoids adding an external dependency while maintaining
 * logging capabilities for observability.
 *
 * @package Blockchain\Operations
 */
interface LoggerInterface
{
    /**
     * System is unusable
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Action must be taken immediately
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Critical conditions
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Runtime errors
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Normal but significant events
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Interesting events
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Detailed debug information
     *
     * @param string $message
     * @param array<string,mixed> $context
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void;
}
