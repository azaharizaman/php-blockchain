<?php

declare(strict_types=1);

namespace Blockchain\Operations;

use Blockchain\Contracts\BlockchainDriverInterface;
use Throwable;

/**
 * Batcher
 *
 * Batching component that groups compatible transactions and submits them through
 * drivers supporting batched RPC calls while falling back to single dispatch when
 * necessary.
 *
 * ## Key Responsibilities
 *
 * 1. **Job Collection**: Pulls ready jobs from TransactionQueue respecting timing constraints
 * 2. **Batch Grouping**: Groups jobs by driver capability or network requirements
 * 3. **Dispatch Handling**: Submits batches via driver or iterates for non-batch drivers
 * 4. **Failure Recovery**: Re-enqueues failed jobs with exponential backoff
 * 5. **Telemetry**: Emits events for batch lifecycle and per-job results
 *
 * ## Usage Examples
 *
 * ### Basic Batch Dispatch
 *
 * ```php
 * use Blockchain\Operations\Batcher;
 * use Blockchain\Operations\TransactionQueue;
 * use Blockchain\Drivers\EthereumDriver;
 *
 * $driver = new EthereumDriver();
 * $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/KEY']);
 *
 * $queue = new TransactionQueue();
 * // ... populate queue with jobs
 *
 * $batcher = new Batcher($driver, $queue);
 * $result = $batcher->dispatch();
 *
 * echo "Successful: {$result->getSuccessCount()}\n";
 * echo "Failed: {$result->getFailureCount()}\n";
 * ```
 *
 * ### With Custom Batch Size and Telemetry
 *
 * ```php
 * $tracer = new CustomOperationTracer();
 *
 * $batcher = new Batcher(
 *     driver: $driver,
 *     queue: $queue,
 *     batchSize: 50,
 *     tracer: $tracer
 * );
 *
 * $result = $batcher->dispatch();
 * ```
 *
 * ## Security Considerations (SEC-001)
 *
 * - Payload data is NEVER logged in exceptions or telemetry
 * - Only job IDs and sanitized metadata are exposed
 * - Addresses may be masked in error messages when necessary
 *
 * @package Blockchain\Operations
 */
class Batcher
{
    /**
     * Maximum number of jobs to collect per batch
     */
    private int $batchSize;

    /**
     * Optional telemetry tracer for observability
     */
    private ?OperationTracerInterface $tracer;

    /**
     * Constructor
     *
     * @param BlockchainDriverInterface $driver The blockchain driver for transaction submission
     * @param TransactionQueue $queue The transaction queue to pull jobs from
     * @param int $batchSize Maximum number of jobs per batch (default: 25)
     * @param OperationTracerInterface|null $tracer Optional telemetry tracer
     */
    public function __construct(
        private readonly BlockchainDriverInterface $driver,
        private readonly TransactionQueue $queue,
        int $batchSize = 25,
        ?OperationTracerInterface $tracer = null
    ) {
        $this->batchSize = $batchSize;
        $this->tracer = $tracer;
    }

    /**
     * Collect ready jobs from the queue
     *
     * Pulls jobs from the queue that are ready for processing (i.e., their
     * nextAvailableAt timestamp has passed). Respects the maximum batch size.
     *
     * @param int|null $max Maximum number of jobs to collect (null = use batchSize)
     *
     * @return array<int,TransactionJob> Array of ready jobs
     */
    public function collectReadyJobs(?int $max = null): array
    {
        $limit = $max ?? $this->batchSize;
        $jobs = [];

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->queue->dequeue();
            if ($job === null) {
                break;
            }
            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * Dispatch available batches
     *
     * Processes ready jobs from the queue, grouping them into batches based on
     * driver capabilities. For drivers supporting batch RPC, makes a single request
     * carrying multiple payloads. For drivers lacking batch support, iterates
     * sequentially but returns aggregated results.
     *
     * Handles partial failures by re-enqueueing failed jobs with backoff according
     * to TransactionQueue's retry policy.
     *
     * @return BatchResult Aggregated results with success/failure counts
     */
    public function dispatch(): BatchResult
    {
        $jobs = $this->collectReadyJobs();

        if (empty($jobs)) {
            return new BatchResult([], []);
        }

        $this->tracer?->traceBatchStart(count($jobs));

        $successfulJobs = [];
        $failedJobs = [];

        // Check if driver supports batching
        if ($this->supportsBatching()) {
            // Batch submission
            try {
                $results = $this->submitBatch($jobs);
                $successfulJobs = $results['successful'];
                $failedJobs = $results['failed'];
            } catch (Throwable $e) {
                // If entire batch fails, mark all jobs as failed
                foreach ($jobs as $job) {
                    $this->handleJobFailure($job, $e);
                    $failedJobs[] = ['job' => $job, 'error' => $e];
                }
            }
        } else {
            // Sequential dispatch
            foreach ($jobs as $job) {
                try {
                    $this->submitSingleJob($job);
                    $successfulJobs[] = $job;
                    $this->queue->acknowledge($job);
                    $this->tracer?->traceJobSuccess($job->getId());
                } catch (Throwable $e) {
                    $this->handleJobFailure($job, $e);
                    $failedJobs[] = ['job' => $job, 'error' => $e];
                    $this->tracer?->traceJobFailure($job->getId(), $this->sanitizeErrorMessage($e));
                }
            }
        }

        $this->tracer?->traceBatchComplete(count($successfulJobs), count($failedJobs));

        return new BatchResult($successfulJobs, $failedJobs);
    }

    /**
     * Check if driver supports batch RPC operations
     *
     * Attempts to determine if the driver supports batch submission by checking
     * for a supportsBatching() method. Falls back to false if method doesn't exist.
     *
     * @return bool True if driver supports batching, false otherwise
     */
    private function supportsBatching(): bool
    {
        // Check if driver has a supportsBatching method
        if (method_exists($this->driver, 'supportsBatching')) {
            return $this->driver->supportsBatching();
        }

        // Default to false for safety
        return false;
    }

    /**
     * Submit a batch of jobs via driver batch RPC
     *
     * Attempts to submit multiple jobs in a single RPC call. The driver must
     * support batch operations (checked via supportsBatching()).
     *
     * @param array<int,TransactionJob> $jobs Jobs to submit in batch
     *
     * @return array{successful: array<int,TransactionJob>, failed: array<int,array{job: TransactionJob, error: Throwable}>}
     *
     * @throws Throwable If batch submission fails entirely
     */
    private function submitBatch(array $jobs): array
    {
        $successful = [];
        $failed = [];

        // Check if driver has sendBatch method
        if (!method_exists($this->driver, 'sendBatch')) {
            throw new \RuntimeException(sprintf(
                'Driver %s claims to support batching but sendBatch method not found',
                get_class($this->driver)
            ));
        }

        // Prepare batch payloads
        $payloads = [];
        foreach ($jobs as $index => $job) {
            $payload = $job->getPayload();
            $payloads[$index] = $payload;
        }

        // Submit batch
        try {
            $results = $this->driver->sendBatch($payloads);

            // Process results
            foreach ($jobs as $index => $job) {
                if (isset($results[$index]) && $results[$index]['success'] ?? false) {
                    $successful[] = $job;
                    $this->queue->acknowledge($job);
                    $this->tracer?->traceJobSuccess($job->getId());
                } else {
                    $error = $results[$index]['error'] ?? 'Unknown error';
                    $exception = new \Exception($error);
                    $this->handleJobFailure($job, $exception);
                    $failed[] = ['job' => $job, 'error' => $exception];
                    $this->tracer?->traceJobFailure($job->getId(), $this->sanitizeErrorMessage($exception));
                }
            }
        } catch (Throwable $e) {
            // Re-throw to be handled by caller
            throw $e;
        }

        return ['successful' => $successful, 'failed' => $failed];
    }

    /**
     * Submit a single job via driver
     *
     * Submits an individual transaction job using the driver's standard
     * sendTransaction method.
     *
     * @param TransactionJob $job Job to submit
     *
     * @return void
     *
     * @throws Throwable If submission fails
     */
    private function submitSingleJob(TransactionJob $job): void
    {
        $payload = $job->getPayload();
        $metadata = $job->getMetadata();

        // Extract transaction parameters
        $from = $metadata['from'] ?? '';
        $to = $payload['to'] ?? $payload['params']['to'] ?? '';
        $amount = $payload['amount'] ?? $payload['params']['value'] ?? 0.0;

        // Prepare options
        $options = [];
        if (isset($metadata['gas'])) {
            $options['gas'] = $metadata['gas'];
        }
        if (isset($metadata['nonce'])) {
            $options['nonce'] = $metadata['nonce'];
        }
        if (isset($metadata['memo'])) {
            $options['memo'] = $metadata['memo'];
        }

        // Submit transaction
        $this->driver->sendTransaction($from, $to, (float)$amount, $options);
    }

    /**
     * Handle job failure by re-enqueueing with backoff
     *
     * Records the failure in the queue's retry system, which will automatically
     * re-enqueue with exponential backoff if max attempts haven't been exhausted.
     *
     * @param TransactionJob $job Failed job
     * @param Throwable $error Error that caused failure
     *
     * @return void
     */
    private function handleJobFailure(TransactionJob $job, Throwable $error): void
    {
        $this->queue->recordFailure($job, $error);
    }

    /**
     * Sanitize error message to prevent sensitive data exposure
     *
     * Removes potentially sensitive information from error messages before
     * logging or emitting to telemetry. Addresses may be masked when necessary.
     *
     * @param Throwable $error Original error
     *
     * @return string Sanitized error message
     */
    private function sanitizeErrorMessage(Throwable $error): string
    {
        $message = $error->getMessage();

        // Remove potential private keys or long hex strings (must come first to match longer strings)
        $message = preg_replace('/0x[a-fA-F0-9]{64,}/', '0x[SENSITIVE]', $message);

        // Remove potential addresses (0x... format with exactly 40 chars)
        $message = preg_replace('/0x[a-fA-F0-9]{40}/', '0x[ADDRESS]', $message);

        return $message;
    }
}

/**
 * BatchResult
 *
 * Value object representing the results of a batch dispatch operation.
 * Contains successful jobs and failed jobs with error information.
 *
 * @package Blockchain\Operations
 */
class BatchResult
{
    /**
     * Constructor
     *
     * @param array<int,TransactionJob> $successfulJobs Jobs that completed successfully
     * @param array<int,array{job: TransactionJob, error: Throwable}> $failedJobs Jobs that failed with error info
     */
    public function __construct(
        private readonly array $successfulJobs,
        private readonly array $failedJobs
    ) {
    }

    /**
     * Get count of successful jobs
     *
     * @return int
     */
    public function getSuccessCount(): int
    {
        return count($this->successfulJobs);
    }

    /**
     * Get count of failed jobs
     *
     * @return int
     */
    public function getFailureCount(): int
    {
        return count($this->failedJobs);
    }

    /**
     * Get successful jobs
     *
     * @return array<int,TransactionJob>
     */
    public function getSuccessfulJobs(): array
    {
        return $this->successfulJobs;
    }

    /**
     * Get failed jobs with error information
     *
     * @return array<int,array{job: TransactionJob, error: Throwable}>
     */
    public function getFailedJobs(): array
    {
        return $this->failedJobs;
    }

    /**
     * Check if all jobs were successful
     *
     * @return bool
     */
    public function isFullSuccess(): bool
    {
        return empty($this->failedJobs);
    }

    /**
     * Check if all jobs failed
     *
     * @return bool
     */
    public function isFullFailure(): bool
    {
        return empty($this->successfulJobs);
    }
}

/**
 * OperationTracerInterface
 *
 * Interface for telemetry and observability hooks in batch operations.
 * Implementations can emit metrics to monitoring systems without exposing
 * sensitive transaction data (SEC-001 compliance).
 *
 * @package Blockchain\Operations
 */
interface OperationTracerInterface
{
    /**
     * Trace batch start event
     *
     * @param int $jobCount Number of jobs in the batch
     *
     * @return void
     */
    public function traceBatchStart(int $jobCount): void;

    /**
     * Trace batch completion event
     *
     * @param int $successCount Number of successful jobs
     * @param int $failureCount Number of failed jobs
     *
     * @return void
     */
    public function traceBatchComplete(int $successCount, int $failureCount): void;

    /**
     * Trace individual job success
     *
     * @param string $jobId Job identifier
     *
     * @return void
     */
    public function traceJobSuccess(string $jobId): void;

    /**
     * Trace individual job failure
     *
     * @param string $jobId Job identifier
     * @param string $errorMessage Sanitized error message (no sensitive data)
     *
     * @return void
     */
    public function traceJobFailure(string $jobId, string $errorMessage): void;
}
