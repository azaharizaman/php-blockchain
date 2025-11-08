<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Blockchain\Operations\TransactionQueue;
use Blockchain\Operations\TransactionJob;
use Blockchain\Operations\TransactionBuilder;
use Blockchain\Operations\Batcher;
use Blockchain\Telemetry\OperationTracer;
use Blockchain\Telemetry\OperationTracerInterface;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Wallet\WalletInterface;

/**
 * OperationLifecycleTest
 *
 * Integration test suite verifying that telemetry hooks fire in the correct
 * order throughout the transaction lifecycle: build -> enqueue -> dequeue ->
 * batch -> broadcast.
 *
 * Uses a spy tracer to capture hook invocations and verify their order and
 * payloads without exposing sensitive data (SEC-001 compliance).
 */
class OperationLifecycleTest extends TestCase
{
    /**
     * Test complete lifecycle with spy tracer
     */
    public function testCompleteLifecycleWithSpyTracer(): void
    {
        $spy = new SpyTracer();

        // 1. Build a transaction with tracer
        $driver = new MockDriver();
        $wallet = new MockWallet();
        $builder = (new TransactionBuilder($driver, $wallet))->withTracer($spy);

        $transaction = $builder->buildTransfer('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 1.0);

        // Assert transaction built event was emitted
        $this->assertCount(1, $spy->transactionBuiltEvents);
        $this->assertEquals('ethereum', $spy->transactionBuiltEvents[0]['driver']);
        $this->assertTrue($spy->transactionBuiltEvents[0]['hasSignature']);

        // 2. Enqueue the transaction job
        $queue = new TransactionQueue(tracer: $spy);
        $job = new TransactionJob(
            id: 'tx-test-001',
            payload: $transaction['payload'],
            metadata: $transaction['metadata']
        );

        $queue->enqueue($job);

        // Assert enqueued event was emitted
        $this->assertCount(1, $spy->enqueuedEvents);
        $this->assertEquals('tx-test-001', $spy->enqueuedEvents[0]['jobId']);
        $this->assertEquals(0, $spy->enqueuedEvents[0]['attempts']);

        // 3. Dequeue the job
        $dequeuedJob = $queue->dequeue();

        // Assert dequeued event was emitted
        $this->assertNotNull($dequeuedJob);
        $this->assertCount(1, $spy->dequeuedEvents);
        $this->assertEquals('tx-test-001', $spy->dequeuedEvents[0]['jobId']);

        // 4. Process through batcher
        $queue->enqueue($dequeuedJob); // Re-enqueue for batch processing
        $batcher = new Batcher($driver, $queue, tracer: $spy);

        $result = $batcher->dispatch();

        // Assert batch events were emitted
        $this->assertCount(1, $spy->batchStartEvents);
        $this->assertEquals(1, $spy->batchStartEvents[0]);
        $this->assertCount(1, $spy->batchCompleteEvents);
        $this->assertEquals(1, $spy->batchCompleteEvents[0]['successCount']);
        $this->assertEquals(0, $spy->batchCompleteEvents[0]['failureCount']);

        // Assert job success was tracked
        $this->assertCount(1, $spy->jobSuccessEvents);
        $this->assertEquals('tx-test-001', $spy->jobSuccessEvents[0]);

        // Verify event order
        $expectedOrder = [
            'transactionBuilt',
            'enqueued',
            'dequeued',
            'enqueued', // Re-enqueued
            'dequeued', // Dequeued by batcher
            'batchStart',
            'jobSuccess',
            'batchComplete',
        ];

        $this->assertEquals($expectedOrder, $spy->eventOrder);
    }

    /**
     * Test lifecycle with failed job
     */
    public function testLifecycleWithFailedJob(): void
    {
        $spy = new SpyTracer();

        // Create a failing driver
        $driver = new MockDriver(shouldFail: true);
        $queue = new TransactionQueue(
            options: ['maxAttempts' => 3],
            tracer: $spy
        );

        $job = new TransactionJob(
            id: 'tx-fail-001',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456']
        );

        $queue->enqueue($job);
        $this->assertCount(1, $spy->enqueuedEvents);

        $batcher = new Batcher($driver, $queue, tracer: $spy);
        $result = $batcher->dispatch();

        // Assert failure was tracked
        $this->assertCount(1, $spy->jobFailureEvents);
        $this->assertEquals('tx-fail-001', $spy->jobFailureEvents[0]['jobId']);
        $this->assertStringContainsString('Mock failure', $spy->jobFailureEvents[0]['error']);

        // Assert batch reported failure
        $this->assertEquals(0, $spy->batchCompleteEvents[0]['successCount']);
        $this->assertEquals(1, $spy->batchCompleteEvents[0]['failureCount']);
    }

    /**
     * Test that tracer receives sanitized data (SEC-001)
     */
    public function testTracerReceivesSanitizedData(): void
    {
        $spy = new SpyTracer();

        $driver = new MockDriver();
        $wallet = new MockWallet();
        $builder = (new TransactionBuilder($driver, $wallet))->withTracer($spy);

        $transaction = $builder->buildTransfer('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 1.0);

        // Verify transaction built event does not contain payload
        $event = $spy->transactionBuiltEvents[0];
        $this->assertArrayHasKey('driver', $event);
        $this->assertArrayHasKey('from', $event);
        $this->assertArrayHasKey('timestamp', $event);
        $this->assertArrayHasKey('hasSignature', $event);
        $this->assertArrayNotHasKey('payload', $event);
        $this->assertArrayNotHasKey('signatures', $event);
    }

    /**
     * Test multiple jobs in batch
     */
    public function testMultipleJobsInBatch(): void
    {
        $spy = new SpyTracer();
        $driver = new MockDriver();
        $queue = new TransactionQueue(tracer: $spy);

        // Enqueue multiple jobs
        for ($i = 1; $i <= 5; $i++) {
            $job = new TransactionJob(
                id: "tx-batch-{$i}",
                payload: ['to' => '0x123', 'amount' => $i],
                metadata: ['from' => '0x456']
            );
            $queue->enqueue($job);
        }

        $this->assertCount(5, $spy->enqueuedEvents);

        $batcher = new Batcher($driver, $queue, batchSize: 10, tracer: $spy);
        $result = $batcher->dispatch();

        // Assert all jobs were processed
        $this->assertCount(5, $spy->dequeuedEvents);
        $this->assertCount(1, $spy->batchStartEvents);
        $this->assertEquals(5, $spy->batchStartEvents[0]);
        $this->assertCount(5, $spy->jobSuccessEvents);
        $this->assertCount(1, $spy->batchCompleteEvents);
        $this->assertEquals(5, $spy->batchCompleteEvents[0]['successCount']);
    }
}

/**
 * SpyTracer
 *
 * Test double that records all tracer invocations for verification.
 * Extends OperationTracer to capture hook calls without affecting behavior.
 */
class SpyTracer extends OperationTracer
{
    /** @var array<int,array<string,mixed>> */
    public array $enqueuedEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $dequeuedEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $batchDispatchedEvents = [];

    /** @var array<int,int> */
    public array $batchStartEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $batchCompleteEvents = [];

    /** @var array<int,string> */
    public array $jobSuccessEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $jobFailureEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $broadcastResultEvents = [];

    /** @var array<int,array<string,mixed>> */
    public array $transactionBuiltEvents = [];

    /** @var array<int,string> */
    public array $eventOrder = [];

    public function onEnqueued(array $job): void
    {
        $this->enqueuedEvents[] = $job;
        $this->eventOrder[] = 'enqueued';
    }

    public function onDequeued(array $job): void
    {
        $this->dequeuedEvents[] = $job;
        $this->eventOrder[] = 'dequeued';
    }

    public function onBatchDispatched(array $batch): void
    {
        $this->batchDispatchedEvents[] = $batch;
        $this->eventOrder[] = 'batchDispatched';
    }

    public function traceBatchStart(int $jobCount): void
    {
        $this->batchStartEvents[] = $jobCount;
        $this->eventOrder[] = 'batchStart';
    }

    public function traceBatchComplete(int $successCount, int $failureCount): void
    {
        $this->batchCompleteEvents[] = [
            'successCount' => $successCount,
            'failureCount' => $failureCount,
        ];
        $this->eventOrder[] = 'batchComplete';
    }

    public function traceJobSuccess(string $jobId): void
    {
        $this->jobSuccessEvents[] = $jobId;
        $this->eventOrder[] = 'jobSuccess';
    }

    public function traceJobFailure(string $jobId, string $errorMessage): void
    {
        $this->jobFailureEvents[] = [
            'jobId' => $jobId,
            'error' => $errorMessage,
        ];
        $this->eventOrder[] = 'jobFailure';
    }

    public function onBroadcastResult(array $result): void
    {
        $this->broadcastResultEvents[] = $result;
        $this->eventOrder[] = 'broadcastResult';
    }

    public function onTransactionBuilt(array $metadata): void
    {
        $this->transactionBuiltEvents[] = $metadata;
        $this->eventOrder[] = 'transactionBuilt';
    }
}

/**
 * MockDriver
 *
 * Test double for BlockchainDriverInterface with controllable failure behavior.
 */
class MockDriver implements BlockchainDriverInterface
{
    public function __construct(private bool $shouldFail = false)
    {
    }

    public function connect(array $config): void
    {
    }

    public function getBalance(string $address): float
    {
        return 1.0;
    }

    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        if ($this->shouldFail) {
            throw new \Exception('Mock failure: Network timeout');
        }
        return '0x' . bin2hex(random_bytes(32));
    }

    public function getTransaction(string $hash): array
    {
        return [];
    }

    public function getBlock(int|string $blockIdentifier): array
    {
        return [];
    }

    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        return 21000;
    }

    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        return null;
    }

    public function getNetworkInfo(): ?array
    {
        return ['name' => 'ethereum', 'chainId' => 1];
    }
}

/**
 * MockWallet
 *
 * Test double for WalletInterface (SEC-001 compliant).
 */
class MockWallet implements WalletInterface
{
    public function getPublicKey(): string
    {
        return '0x04abc...';
    }

    public function sign(string $payload): string
    {
        return '0xsigned_' . hash('sha256', $payload);
    }

    public function getAddress(): string
    {
        return '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';
    }
}
