<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use Blockchain\Telemetry\OperationTracer;
use Blockchain\Telemetry\OperationTracerInterface;

/**
 * OperationTracerTest
 *
 * Test suite for the OperationTracer class verifying that the default
 * implementation provides no-op behavior for all interface methods.
 *
 * Following TDD principles, these tests ensure that the tracer can be
 * safely used without any configuration and has zero overhead.
 */
class OperationTracerTest extends TestCase
{
    private OperationTracer $tracer;

    protected function setUp(): void
    {
        $this->tracer = new OperationTracer();
    }

    /**
     * Test that OperationTracer implements OperationTracerInterface
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(OperationTracerInterface::class, $this->tracer);
    }

    /**
     * Test that onEnqueued is a no-op
     */
    public function testOnEnqueuedIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->onEnqueued([
            'jobId' => 'test-123',
            'attempts' => 0,
            'nextAvailableAt' => time(),
            'timestamp' => time(),
        ]);

        // If we reach here without errors, the no-op works
        $this->assertTrue(true);
    }

    /**
     * Test that onDequeued is a no-op
     */
    public function testOnDequeuedIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->onDequeued([
            'jobId' => 'test-456',
            'attempts' => 1,
            'timestamp' => time(),
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test that onBatchDispatched is a no-op
     */
    public function testOnBatchDispatchedIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->onBatchDispatched([
            'jobCount' => 10,
            'timestamp' => time(),
            'batchId' => 'batch-789',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test that traceBatchStart is a no-op
     */
    public function testTraceBatchStartIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->traceBatchStart(5);

        $this->assertTrue(true);
    }

    /**
     * Test that traceBatchComplete is a no-op
     */
    public function testTraceBatchCompleteIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->traceBatchComplete(4, 1);

        $this->assertTrue(true);
    }

    /**
     * Test that traceJobSuccess is a no-op
     */
    public function testTraceJobSuccessIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->traceJobSuccess('job-abc');

        $this->assertTrue(true);
    }

    /**
     * Test that traceJobFailure is a no-op
     */
    public function testTraceJobFailureIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->traceJobFailure('job-def', 'Network timeout');

        $this->assertTrue(true);
    }

    /**
     * Test that onBroadcastResult is a no-op
     */
    public function testOnBroadcastResultIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->onBroadcastResult([
            'success' => true,
            'transactionHash' => '0xabc123',
            'jobId' => 'job-ghi',
            'timestamp' => time(),
            'networkLatencyMs' => 250,
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test that onTransactionBuilt is a no-op
     */
    public function testOnTransactionBuiltIsNoOp(): void
    {
        // Should not throw any exceptions
        $this->tracer->onTransactionBuilt([
            'driver' => 'ethereum',
            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            'timestamp' => time(),
            'hasSignature' => true,
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test that all methods can be called in sequence without errors
     */
    public function testAllMethodsWorkInSequence(): void
    {
        $this->tracer->onEnqueued(['jobId' => 'seq-1', 'attempts' => 0, 'timestamp' => time()]);
        $this->tracer->onDequeued(['jobId' => 'seq-1', 'attempts' => 0, 'timestamp' => time()]);
        $this->tracer->onTransactionBuilt(['driver' => 'ethereum', 'from' => '0x123', 'timestamp' => time(), 'hasSignature' => true]);
        $this->tracer->onBatchDispatched(['jobCount' => 1, 'timestamp' => time()]);
        $this->tracer->traceBatchStart(1);
        $this->tracer->traceJobSuccess('seq-1');
        $this->tracer->traceBatchComplete(1, 0);
        $this->tracer->onBroadcastResult(['success' => true, 'jobId' => 'seq-1', 'timestamp' => time()]);

        $this->assertTrue(true);
    }

    /**
     * Test that tracer can be extended
     */
    public function testTracerCanBeExtended(): void
    {
        $customTracer = new class extends OperationTracer {
            public int $enqueueCount = 0;
            public int $dequeueCount = 0;

            public function onEnqueued(array $job): void
            {
                $this->enqueueCount++;
            }

            public function onDequeued(array $job): void
            {
                $this->dequeueCount++;
            }
        };

        $customTracer->onEnqueued(['jobId' => 'custom-1', 'attempts' => 0, 'timestamp' => time()]);
        $customTracer->onEnqueued(['jobId' => 'custom-2', 'attempts' => 0, 'timestamp' => time()]);
        $customTracer->onDequeued(['jobId' => 'custom-1', 'attempts' => 0, 'timestamp' => time()]);

        $this->assertEquals(2, $customTracer->enqueueCount);
        $this->assertEquals(1, $customTracer->dequeueCount);
    }
}
