<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

/**
 * OperationTracer
 *
 * Default no-op implementation of OperationTracerInterface that provides safe defaults
 * for telemetry hooks. This tracer does nothing by default, making instrumentation
 * optional and zero-cost when not actively monitoring.
 *
 * ## Key Features
 *
 * 1. **No-Op by Default**: All methods are empty implementations, ensuring zero
 *    performance overhead when telemetry is not needed.
 *
 * 2. **Extensible**: Developers can extend this class and override specific hooks
 *    to add custom monitoring logic without implementing all interface methods.
 *
 * 3. **SEC-001 Compliant**: Designed to work only with sanitized, non-sensitive data.
 *    Never logs private keys, full transaction payloads, or credentials.
 *
 * ## Usage Examples
 *
 * ### Using Default No-Op Tracer
 *
 * ```php
 * use Blockchain\Telemetry\OperationTracer;
 * use Blockchain\Operations\TransactionQueue;
 *
 * $tracer = new OperationTracer();
 * $queue = new TransactionQueue(tracer: $tracer);
 *
 * // All tracer calls are no-ops, zero overhead
 * ```
 *
 * ### Creating Custom Tracer
 *
 * ```php
 * class MetricsTracer extends OperationTracer
 * {
 *     private MetricsCollector $metrics;
 *
 *     public function __construct(MetricsCollector $metrics)
 *     {
 *         $this->metrics = $metrics;
 *     }
 *
 *     public function onEnqueued(array $job): void
 *     {
 *         $this->metrics->increment('jobs.enqueued');
 *         $this->metrics->gauge('jobs.queue_depth', $job['queueSize'] ?? 0);
 *     }
 *
 *     public function onBroadcastResult(array $result): void
 *     {
 *         if ($result['success']) {
 *             $this->metrics->increment('transactions.success');
 *         } else {
 *             $this->metrics->increment('transactions.failed');
 *         }
 *
 *         if (isset($result['networkLatencyMs'])) {
 *             $this->metrics->histogram('network.latency', $result['networkLatencyMs']);
 *         }
 *     }
 * }
 * ```
 *
 * ### Using with Logger
 *
 * ```php
 * class LoggingTracer extends OperationTracer
 * {
 *     private LoggerInterface $logger;
 *
 *     public function __construct(LoggerInterface $logger)
 *     {
 *         $this->logger = $logger;
 *     }
 *
 *     public function onEnqueued(array $job): void
 *     {
 *         $this->logger->info('Job enqueued', [
 *             'jobId' => $job['jobId'],
 *             'attempts' => $job['attempts'] ?? 0,
 *         ]);
 *     }
 *
 *     public function traceJobFailure(string $jobId, string $errorMessage): void
 *     {
 *         $this->logger->warning('Job failed', [
 *             'jobId' => $jobId,
 *             'error' => $errorMessage,
 *         ]);
 *     }
 * }
 * ```
 *
 * ## Payload Structure Expectations
 *
 * ### onEnqueued / onDequeued
 * - **jobId** (string): Unique job identifier
 * - **attempts** (int): Number of processing attempts
 * - **nextAvailableAt** (int, optional): Unix timestamp when job can be processed
 * - **timestamp** (int): Unix timestamp of event
 *
 * ### onBatchDispatched
 * - **jobCount** (int): Number of jobs in batch
 * - **timestamp** (int): Unix timestamp of batch start
 * - **batchId** (string, optional): Unique batch identifier
 *
 * ### onBroadcastResult
 * - **success** (bool): Whether broadcast succeeded
 * - **jobId** (string): Associated job identifier
 * - **transactionHash** (string, optional): Network transaction hash
 * - **timestamp** (int): Unix timestamp of broadcast
 * - **errorMessage** (string, optional): Error message if failed
 * - **networkLatencyMs** (int, optional): Network round-trip time in milliseconds
 *
 * ### onTransactionBuilt
 * - **driver** (string): Blockchain driver name (e.g., 'ethereum', 'solana')
 * - **from** (string): Sender address (may be masked as needed)
 * - **timestamp** (int): Unix timestamp of build
 * - **hasSignature** (bool): Whether transaction was signed
 *
 * @package Blockchain\Telemetry
 */
class OperationTracer implements OperationTracerInterface
{
    /**
     * Trace job enqueued event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param array<string,mixed> $job Sanitized job metadata
     *
     * @return void
     */
    public function onEnqueued(array $job): void
    {
        // No-op by default
    }

    /**
     * Trace job dequeued event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param array<string,mixed> $job Sanitized job metadata
     *
     * @return void
     */
    public function onDequeued(array $job): void
    {
        // No-op by default
    }

    /**
     * Trace batch dispatch started event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param array<string,mixed> $batch Sanitized batch metadata
     *
     * @return void
     */
    public function onBatchDispatched(array $batch): void
    {
        // No-op by default
    }

    /**
     * Trace batch start event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param int $jobCount Number of jobs in the batch
     *
     * @return void
     */
    public function traceBatchStart(int $jobCount): void
    {
        // No-op by default
    }

    /**
     * Trace batch completion event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param int $successCount Number of successful jobs
     * @param int $failureCount Number of failed jobs
     *
     * @return void
     */
    public function traceBatchComplete(int $successCount, int $failureCount): void
    {
        // No-op by default
    }

    /**
     * Trace individual job success
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param string $jobId Job identifier
     *
     * @return void
     */
    public function traceJobSuccess(string $jobId): void
    {
        // No-op by default
    }

    /**
     * Trace individual job failure
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param string $jobId Job identifier
     * @param string $errorMessage Sanitized error message
     *
     * @return void
     */
    public function traceJobFailure(string $jobId, string $errorMessage): void
    {
        // No-op by default
    }

    /**
     * Trace broadcast result event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param array<string,mixed> $result Sanitized broadcast result
     *
     * @return void
     */
    public function onBroadcastResult(array $result): void
    {
        // No-op by default
    }

    /**
     * Trace transaction build event
     *
     * Default no-op implementation. Override to add custom monitoring.
     *
     * @param array<string,mixed> $metadata Sanitized transaction metadata
     *
     * @return void
     */
    public function onTransactionBuilt(array $metadata): void
    {
        // No-op by default
    }
}
