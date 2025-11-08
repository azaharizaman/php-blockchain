<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

/**
 * OperationTracerInterface
 *
 * Interface for telemetry and observability hooks across the transaction lifecycle.
 * Implementations can emit metrics to monitoring systems without exposing sensitive
 * transaction data (SEC-001 compliance).
 *
 * This interface provides hooks for:
 * - Queue operations (enqueue, dequeue)
 * - Batch operations (batch start, completion)
 * - Transaction building
 * - Individual job outcomes
 * - Broadcast results
 *
 * All methods receive sanitized payloads with no sensitive data such as private keys,
 * full transaction payloads, or secret credentials.
 *
 * @package Blockchain\Telemetry
 */
interface OperationTracerInterface
{
    /**
     * Trace job enqueued event
     *
     * Called when a job is added to the transaction queue.
     *
     * @param array<string,mixed> $job Sanitized job metadata (no sensitive data)
     *   Expected keys: jobId, attempts, nextAvailableAt, timestamp
     *
     * @return void
     */
    public function onEnqueued(array $job): void;

    /**
     * Trace job dequeued event
     *
     * Called when a job is removed from the queue for processing.
     *
     * @param array<string,mixed> $job Sanitized job metadata (no sensitive data)
     *   Expected keys: jobId, attempts, timestamp
     *
     * @return void
     */
    public function onDequeued(array $job): void;

    /**
     * Trace batch dispatch started event
     *
     * Called when a batch of jobs begins processing.
     *
     * @param array<string,mixed> $batch Sanitized batch metadata
     *   Expected keys: jobCount, timestamp, batchId (optional)
     *
     * @return void
     */
    public function onBatchDispatched(array $batch): void;

    /**
     * Trace batch start event
     *
     * Called at the start of batch processing (legacy method for backward compatibility).
     *
     * @param int $jobCount Number of jobs in the batch
     *
     * @return void
     */
    public function traceBatchStart(int $jobCount): void;

    /**
     * Trace batch completion event
     *
     * Called when batch processing completes.
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
     * Called when a single job completes successfully.
     *
     * @param string $jobId Job identifier
     *
     * @return void
     */
    public function traceJobSuccess(string $jobId): void;

    /**
     * Trace individual job failure
     *
     * Called when a single job fails.
     *
     * @param string $jobId Job identifier
     * @param string $errorMessage Sanitized error message (no sensitive data)
     *
     * @return void
     */
    public function traceJobFailure(string $jobId, string $errorMessage): void;

    /**
     * Trace broadcast result event
     *
     * Called after a transaction is broadcast to the network, capturing the outcome.
     *
     * @param array<string,mixed> $result Sanitized broadcast result
     *   Expected keys: success (bool), transactionHash (optional), jobId, timestamp,
     *   errorMessage (if failed), networkLatencyMs (optional)
     *
     * @return void
     */
    public function onBroadcastResult(array $result): void;

    /**
     * Trace transaction build event
     *
     * Called when a transaction is built (assembled and signed).
     *
     * @param array<string,mixed> $metadata Sanitized transaction metadata
     *   Expected keys: driver, from (address), timestamp, hasSignature (bool)
     *
     * @return void
     */
    public function onTransactionBuilt(array $metadata): void;
}
