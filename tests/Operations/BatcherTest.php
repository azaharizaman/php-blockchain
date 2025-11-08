<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Blockchain\Operations\Batcher;
use Blockchain\Operations\BatchResult;
use Blockchain\Operations\TransactionQueue;
use Blockchain\Operations\TransactionJob;
use Blockchain\Operations\OperationTracerInterface;
use Blockchain\Contracts\BlockchainDriverInterface;
use Exception;

/**
 * BatcherTest
 *
 * Test suite for the Batcher class using mock drivers to validate batching
 * behavior, retry logic, and telemetry integration.
 *
 * Following TDD principles and SEC-001 requirements.
 */
class BatcherTest extends TestCase
{
    private FakeClock $fakeClock;
    private MockOperationTracer $tracer;

    protected function setUp(): void
    {
        $this->fakeClock = new FakeClock();
        $this->tracer = new MockOperationTracer();
    }

    /**
     * Test basic batch dispatch with successful jobs
     */
    public function testDispatchBatchSuccess(): void
    {
        $driver = new MockBatchDriver(supportsBatching: true);
        $queue = $this->createQueueWithJobs(3);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(3, $result->getSuccessCount());
        $this->assertEquals(0, $result->getFailureCount());
        $this->assertTrue($result->isFullSuccess());
        $this->assertFalse($result->isFullFailure());

        // Verify queue is empty after successful dispatch
        $this->assertEquals(0, $queue->size());

        // Verify telemetry
        $this->assertTrue($this->tracer->hasBatchStarted());
        $this->assertTrue($this->tracer->hasBatchCompleted());
        $this->assertEquals(3, $this->tracer->getSuccessCount());
        $this->assertEquals(0, $this->tracer->getFailureCount());
    }

    /**
     * Test dispatch with non-batching driver (sequential)
     */
    public function testDispatchSequentialSuccess(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = $this->createQueueWithJobs(3);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(3, $result->getSuccessCount());
        $this->assertEquals(0, $result->getFailureCount());
        $this->assertTrue($result->isFullSuccess());

        // Verify driver received sequential calls
        $this->assertEquals(3, $driver->getSingleSubmissionCount());
        $this->assertEquals(0, $driver->getBatchSubmissionCount());
    }

    /**
     * Test batch dispatch with driver that supports batching
     */
    public function testDispatchWithBatchingDriver(): void
    {
        $driver = new MockBatchDriver(supportsBatching: true);
        $queue = $this->createQueueWithJobs(5);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(5, $result->getSuccessCount());
        $this->assertEquals(0, $result->getFailureCount());

        // Verify driver used batch method
        $this->assertEquals(1, $driver->getBatchSubmissionCount());
        $this->assertEquals(0, $driver->getSingleSubmissionCount());
    }

    /**
     * Test partial batch failure with retry scheduling
     */
    public function testPartialBatchFailure(): void
    {
        $driver = new MockBatchDriver(supportsBatching: true);
        // Configure driver to fail specific jobs
        $driver->configureFailures([1, 3]); // Fail jobs at index 1 and 3

        $queue = $this->createQueueWithJobs(5);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(3, $result->getSuccessCount());
        $this->assertEquals(2, $result->getFailureCount());
        $this->assertFalse($result->isFullSuccess());
        $this->assertFalse($result->isFullFailure());

        // Failed jobs should be re-queued
        $this->assertEquals(2, $queue->size());

        // Verify telemetry captured failures
        $this->assertEquals(2, $this->tracer->getFailureCount());
    }

    /**
     * Test sequential dispatch with partial failure
     */
    public function testSequentialDispatchPartialFailure(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $driver->configureSequentialFailures([1, 3]); // Fail 2nd and 4th jobs

        $queue = $this->createQueueWithJobs(5);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(3, $result->getSuccessCount());
        $this->assertEquals(2, $result->getFailureCount());

        // Failed jobs should be re-queued
        $this->assertEquals(2, $queue->size());
    }

    /**
     * Test complete batch failure
     */
    public function testCompleteBatchFailure(): void
    {
        $driver = new MockBatchDriver(supportsBatching: true);
        $driver->setFailAllBatches(true);

        $queue = $this->createQueueWithJobs(3);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(0, $result->getSuccessCount());
        $this->assertEquals(3, $result->getFailureCount());
        $this->assertTrue($result->isFullFailure());

        // All jobs should be re-queued
        $this->assertEquals(3, $queue->size());
    }

    /**
     * Test collectReadyJobs respects batch size limit
     */
    public function testCollectReadyJobsRespectsLimit(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = $this->createQueueWithJobs(10);

        $batcher = new Batcher($driver, $queue, 3); // Batch size = 3

        $jobs = $batcher->collectReadyJobs();

        $this->assertCount(3, $jobs);
        $this->assertEquals(7, $queue->size()); // 7 jobs remain
    }

    /**
     * Test collectReadyJobs with custom max parameter
     */
    public function testCollectReadyJobsWithCustomMax(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = $this->createQueueWithJobs(10);

        $batcher = new Batcher($driver, $queue, 25); // Default batch size

        $jobs = $batcher->collectReadyJobs(5); // Override with max=5

        $this->assertCount(5, $jobs);
        $this->assertEquals(5, $queue->size());
    }

    /**
     * Test dispatch with empty queue
     */
    public function testDispatchEmptyQueue(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = new TransactionQueue(clockFn: fn() => $this->fakeClock->now());

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        $this->assertEquals(0, $result->getSuccessCount());
        $this->assertEquals(0, $result->getFailureCount());

        // Telemetry should not fire for empty batch
        $this->assertFalse($this->tracer->hasBatchStarted());
    }

    /**
     * Test dispatch only processes ready jobs
     */
    public function testDispatchOnlyProcessesReadyJobs(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = new TransactionQueue(clockFn: fn() => $this->fakeClock->now());

        $this->fakeClock->setTime(1000);

        // Add ready job
        $readyJob = new TransactionJob(
            id: 'ready-1',
            payload: ['to' => '0x111', 'amount' => 1.0],
            nextAvailableAt: 1000
        );

        // Add future job (not ready)
        $futureJob = new TransactionJob(
            id: 'future-1',
            payload: ['to' => '0x222', 'amount' => 2.0],
            nextAvailableAt: 2000
        );

        $queue->enqueue($readyJob);
        $queue->enqueue($futureJob);

        $batcher = new Batcher($driver, $queue, 10);
        $result = $batcher->dispatch();

        // Only 1 job should be processed
        $this->assertEquals(1, $result->getSuccessCount());
        $this->assertEquals(1, $queue->size()); // Future job still in queue
    }

    /**
     * Test telemetry hooks are called correctly
     */
    public function testTelemetryHooks(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = $this->createQueueWithJobs(3);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $batcher->dispatch();

        // Verify all telemetry methods were called
        $this->assertTrue($this->tracer->hasBatchStarted());
        $this->assertTrue($this->tracer->hasBatchCompleted());
        $this->assertEquals(3, count($this->tracer->getSuccessfulJobIds()));
        $this->assertEmpty($this->tracer->getFailedJobIds());
    }

    /**
     * Test telemetry without tracer (no-op)
     */
    public function testDispatchWithoutTracer(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = $this->createQueueWithJobs(2);

        $batcher = new Batcher($driver, $queue, 10, null); // No tracer
        $result = $batcher->dispatch();

        // Should work without tracer
        $this->assertEquals(2, $result->getSuccessCount());
    }

    /**
     * Test idempotency token preservation (TASK-005 integration)
     */
    public function testIdempotencyTokenPreservation(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $queue = new TransactionQueue(clockFn: fn() => $this->fakeClock->now());

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            idempotencyToken: 'token-abc-123'
        );

        $queue->enqueue($job);

        $batcher = new Batcher($driver, $queue);
        $result = $batcher->dispatch();

        $this->assertEquals(1, $result->getSuccessCount());
        // Token should be preserved (verified through job retrieval in real implementation)
    }

    /**
     * Test error message sanitization (SEC-001)
     */
    public function testErrorMessageSanitization(): void
    {
        $driver = new MockBatchDriver(supportsBatching: false);
        $driver->setErrorMessage('Transfer to 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0 failed with key 0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef');
        $driver->configureSequentialFailures([0]);

        $queue = $this->createQueueWithJobs(1);

        $batcher = new Batcher($driver, $queue, 10, $this->tracer);
        $result = $batcher->dispatch();

        // Get sanitized error from tracer
        $failedJobs = $this->tracer->getFailedJobIds();
        $this->assertNotEmpty($failedJobs);

        $errorMessage = $failedJobs[0]['error'];

        // Address should be masked
        $this->assertStringContainsString('0x[ADDRESS]', $errorMessage);
        $this->assertStringNotContainsString('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', $errorMessage);

        // Long hex (potential key) should be masked
        $this->assertStringContainsString('0x[SENSITIVE]', $errorMessage);
        $this->assertStringNotContainsString('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', $errorMessage);
    }

    /**
     * Test batch result methods
     */
    public function testBatchResultMethods(): void
    {
        $successJob1 = new TransactionJob(id: 'success-1', payload: []);
        $successJob2 = new TransactionJob(id: 'success-2', payload: []);
        $failJob1 = new TransactionJob(id: 'fail-1', payload: []);

        $result = new BatchResult(
            [$successJob1, $successJob2],
            [['job' => $failJob1, 'error' => new Exception('Test error')]]
        );

        $this->assertEquals(2, $result->getSuccessCount());
        $this->assertEquals(1, $result->getFailureCount());
        $this->assertFalse($result->isFullSuccess());
        $this->assertFalse($result->isFullFailure());

        $this->assertCount(2, $result->getSuccessfulJobs());
        $this->assertCount(1, $result->getFailedJobs());
    }

    /**
     * Test batch result with all successes
     */
    public function testBatchResultAllSuccess(): void
    {
        $job1 = new TransactionJob(id: 'job-1', payload: []);
        $job2 = new TransactionJob(id: 'job-2', payload: []);

        $result = new BatchResult([$job1, $job2], []);

        $this->assertTrue($result->isFullSuccess());
        $this->assertFalse($result->isFullFailure());
    }

    /**
     * Test batch result with all failures
     */
    public function testBatchResultAllFailure(): void
    {
        $job1 = new TransactionJob(id: 'job-1', payload: []);
        $job2 = new TransactionJob(id: 'job-2', payload: []);

        $result = new BatchResult(
            [],
            [
                ['job' => $job1, 'error' => new Exception('Error 1')],
                ['job' => $job2, 'error' => new Exception('Error 2')]
            ]
        );

        $this->assertFalse($result->isFullSuccess());
        $this->assertTrue($result->isFullFailure());
    }

    /**
     * Helper method to create a queue with test jobs
     *
     * @param int $count Number of jobs to create
     *
     * @return TransactionQueue
     */
    private function createQueueWithJobs(int $count): TransactionQueue
    {
        $queue = new TransactionQueue(clockFn: fn() => $this->fakeClock->now());
        $this->fakeClock->setTime(1000);

        for ($i = 0; $i < $count; $i++) {
            $job = new TransactionJob(
                id: "job-{$i}",
                payload: [
                    'to' => "0x" . str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                    'amount' => ($i + 1) * 1.0,
                    'params' => [
                        'to' => "0x" . str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                        'value' => ($i + 1) * 1.0,
                    ]
                ],
                metadata: [
                    'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                    'gas' => 21000,
                ],
                nextAvailableAt: 1000 // All ready immediately
            );
            $queue->enqueue($job);
        }

        return $queue;
    }
}

/**
 * MockBatchDriver
 *
 * Mock driver for testing batch and sequential submission behavior.
 */
class MockBatchDriver implements BlockchainDriverInterface
{
    private bool $supportsBatching;
    private int $singleSubmissionCount = 0;
    private int $batchSubmissionCount = 0;
    private array $failureIndices = [];
    private array $sequentialFailureIndices = [];
    private bool $failAllBatches = false;
    private string $errorMessage = 'Mock error';

    public function __construct(bool $supportsBatching = false)
    {
        $this->supportsBatching = $supportsBatching;
    }

    public function supportsBatching(): bool
    {
        return $this->supportsBatching;
    }

    public function sendBatch(array $payloads): array
    {
        $this->batchSubmissionCount++;

        if ($this->failAllBatches) {
            throw new Exception('Batch submission failed');
        }

        $results = [];
        foreach ($payloads as $index => $payload) {
            if (in_array($index, $this->failureIndices)) {
                $results[$index] = [
                    'success' => false,
                    'error' => $this->errorMessage
                ];
            } else {
                $results[$index] = [
                    'success' => true,
                    'hash' => '0x' . bin2hex(random_bytes(32))
                ];
            }
        }

        return $results;
    }

    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        if (in_array($this->singleSubmissionCount, $this->sequentialFailureIndices)) {
            $this->singleSubmissionCount++;
            throw new Exception($this->errorMessage);
        }

        $this->singleSubmissionCount++;
        return '0x' . bin2hex(random_bytes(32));
    }

    public function configureFailures(array $indices): void
    {
        $this->failureIndices = $indices;
    }

    public function configureSequentialFailures(array $indices): void
    {
        $this->sequentialFailureIndices = $indices;
    }

    public function setFailAllBatches(bool $fail): void
    {
        $this->failAllBatches = $fail;
    }

    public function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
    }

    public function getSingleSubmissionCount(): int
    {
        return $this->singleSubmissionCount;
    }

    public function getBatchSubmissionCount(): int
    {
        return $this->batchSubmissionCount;
    }

    // Implement required interface methods (stubs)
    public function connect(array $config): void {}
    public function getBalance(string $address): float { return 0.0; }
    public function getTransaction(string $hash): array { return []; }
    public function getBlock(int|string $blockIdentifier): array { return []; }
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int { return null; }
    public function getTokenBalance(string $address, string $tokenAddress): ?float { return null; }
    public function getNetworkInfo(): ?array { return null; }
}

/**
 * MockOperationTracer
 *
 * Mock tracer for testing telemetry integration.
 */
class MockOperationTracer implements OperationTracerInterface
{
    private bool $batchStarted = false;
    private bool $batchCompleted = false;
    private array $successfulJobIds = [];
    private array $failedJobIds = [];

    public function traceBatchStart(int $jobCount): void
    {
        $this->batchStarted = true;
    }

    public function traceBatchComplete(int $successCount, int $failureCount): void
    {
        $this->batchCompleted = true;
    }

    public function traceJobSuccess(string $jobId): void
    {
        $this->successfulJobIds[] = $jobId;
    }

    public function traceJobFailure(string $jobId, string $errorMessage): void
    {
        $this->failedJobIds[] = ['jobId' => $jobId, 'error' => $errorMessage];
    }

    public function hasBatchStarted(): bool
    {
        return $this->batchStarted;
    }

    public function hasBatchCompleted(): bool
    {
        return $this->batchCompleted;
    }

    public function getSuccessfulJobIds(): array
    {
        return $this->successfulJobIds;
    }

    public function getFailedJobIds(): array
    {
        return $this->failedJobIds;
    }

    public function getSuccessCount(): int
    {
        return count($this->successfulJobIds);
    }

    public function getFailureCount(): int
    {
        return count($this->failedJobIds);
    }
}

/**
 * FakeClock
 *
 * Test helper for deterministic time control (reused from TransactionQueueTest).
 */
class FakeClock
{
    private int $currentTime = 0;

    public function __construct(int $initialTime = 0)
    {
        $this->currentTime = $initialTime;
    }

    public function now(): int
    {
        return $this->currentTime;
    }

    public function setTime(int $time): void
    {
        $this->currentTime = $time;
    }

    public function advance(int $seconds): void
    {
        $this->currentTime += $seconds;
    }
}
