<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Blockchain\Operations\TransactionQueue;
use Blockchain\Operations\TransactionJob;
use Blockchain\Operations\LoggerInterface;
use Exception;

/**
 * TransactionQueueTest
 *
 * Test suite for the TransactionQueue class using fake clock for deterministic
 * timing tests and mock logger for observability validation.
 *
 * Following TDD principles and SEC-001 requirements.
 */
class TransactionQueueTest extends TestCase
{
    private FakeClock $fakeClock;
    private MockLogger $mockLogger;

    protected function setUp(): void
    {
        $this->fakeClock = new FakeClock();
        $this->mockLogger = new MockLogger();
    }

    /**
     * Test basic enqueue and dequeue operation
     */
    public function testEnqueueAndDequeue(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456']
        );

        $queue->enqueue($job);
        $this->assertEquals(1, $queue->size());

        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);
        $this->assertEquals('job-1', $dequeued->getId());
        $this->assertEquals(0, $queue->size());
    }

    /**
     * Test dequeue returns null when queue is empty
     */
    public function testDequeueEmptyQueue(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        $dequeued = $queue->dequeue();
        $this->assertNull($dequeued);
    }

    /**
     * Test dequeue respects nextAvailableAt timing
     */
    public function testDequeueRespectsNextAvailableAt(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        // Current time: 1000
        $this->fakeClock->setTime(1000);

        // Job available at 1100 (100 seconds in future)
        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123'],
            nextAvailableAt: 1100
        );

        $queue->enqueue($job);
        $this->assertEquals(1, $queue->size());

        // Try to dequeue at current time (1000) - should return null
        $dequeued = $queue->dequeue();
        $this->assertNull($dequeued);
        $this->assertEquals(1, $queue->size()); // Job still in queue

        // Advance clock to 1050 - still not ready
        $this->fakeClock->setTime(1050);
        $dequeued = $queue->dequeue();
        $this->assertNull($dequeued);
        $this->assertEquals(1, $queue->size());

        // Advance clock to 1100 - now ready
        $this->fakeClock->setTime(1100);
        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);
        $this->assertEquals('job-1', $dequeued->getId());
        $this->assertEquals(0, $queue->size());
    }

    /**
     * Test recordFailure increments attempts and schedules backoff
     */
    public function testRecordFailureIncrementsAttempts(): void
    {
        $queue = new TransactionQueue(
            options: ['maxAttempts' => 5, 'baseBackoffSeconds' => 2],
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123'],
            attempts: 0
        );

        $queue->enqueue($job);
        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);

        // Record failure
        $exception = new Exception('Network timeout');
        $queue->recordFailure($dequeued, $exception);

        // Job should be re-enqueued with incremented attempts
        $this->assertEquals(1, $queue->size());

        // Job should not be available yet due to backoff
        $retried = $queue->dequeue();
        $this->assertNull($retried);

        // Advance time by backoff period (2^1 * 2 = 4 seconds)
        $this->fakeClock->setTime(1004);
        $retried = $queue->dequeue();
        $this->assertNotNull($retried);
        $this->assertEquals(1, $retried->getAttempts());
    }

    /**
     * Test exponential backoff calculation
     */
    public function testExponentialBackoff(): void
    {
        $queue = new TransactionQueue(
            options: ['maxAttempts' => 10, 'baseBackoffSeconds' => 2],
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123']
        );

        $queue->enqueue($job);

        // Attempt 1: backoff = 2 * 2^0 = 2 seconds
        $dequeued = $queue->dequeue();
        $queue->recordFailure($dequeued, new Exception('fail'));
        $this->fakeClock->setTime(1001);
        $this->assertNull($queue->dequeue()); // Not ready yet
        $this->fakeClock->setTime(1002);
        $retry1 = $queue->dequeue();
        $this->assertNotNull($retry1);
        $this->assertEquals(1, $retry1->getAttempts());

        // Attempt 2: backoff = 2 * 2^1 = 4 seconds
        $queue->recordFailure($retry1, new Exception('fail'));
        $this->fakeClock->setTime(1005);
        $this->assertNull($queue->dequeue()); // Not ready yet
        $this->fakeClock->setTime(1006);
        $retry2 = $queue->dequeue();
        $this->assertNotNull($retry2);
        $this->assertEquals(2, $retry2->getAttempts());

        // Attempt 3: backoff = 2 * 2^2 = 8 seconds
        $queue->recordFailure($retry2, new Exception('fail'));
        $this->fakeClock->setTime(1013);
        $this->assertNull($queue->dequeue()); // Not ready yet
        $this->fakeClock->setTime(1014);
        $retry3 = $queue->dequeue();
        $this->assertNotNull($retry3);
        $this->assertEquals(3, $retry3->getAttempts());
    }

    /**
     * Test max attempts exhaustion
     */
    public function testMaxAttemptsExhaustion(): void
    {
        $queue = new TransactionQueue(
            options: ['maxAttempts' => 3, 'baseBackoffSeconds' => 1],
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123']
        );

        $queue->enqueue($job);

        // Fail 3 times to exhaust max attempts
        for ($i = 0; $i < 3; $i++) {
            $dequeued = $queue->dequeue();
            $this->assertNotNull($dequeued, "Attempt $i");
            $this->assertEquals($i, $dequeued->getAttempts());

            $queue->recordFailure($dequeued, new Exception("Attempt $i failed"));

            // Advance time to make retry available
            $this->fakeClock->advance(100);
        }

        // After max attempts, job should not be re-enqueued
        $this->assertEquals(0, $queue->size());
        $this->assertNull($queue->dequeue());
    }

    /**
     * Test acknowledge method (API completeness)
     */
    public function testAcknowledge(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123']
        );

        $queue->enqueue($job);
        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);

        // Acknowledge should not throw
        $queue->acknowledge($dequeued);
        $this->assertEquals(0, $queue->size());
    }

    /**
     * Test jitter function integration
     */
    public function testJitterFunction(): void
    {
        $jitterCalls = [];
        $jitterFn = function (int $delay) use (&$jitterCalls): int {
            $jitterCalls[] = $delay;
            return $delay + 1; // Add 1 second jitter
        };

        $queue = new TransactionQueue(
            options: ['baseBackoffSeconds' => 2],
            clockFn: fn() => $this->fakeClock->now(),
            jitterFn: $jitterFn
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123']
        );

        $queue->enqueue($job);
        $dequeued = $queue->dequeue();
        $queue->recordFailure($dequeued, new Exception('fail'));

        // Verify jitter was called with backoff delay
        $this->assertCount(1, $jitterCalls);
        $this->assertEquals(2, $jitterCalls[0]); // Base backoff for attempt 1

        // Job should be available after 3 seconds (2 + 1 jitter)
        $this->fakeClock->setTime(1002);
        $this->assertNull($queue->dequeue());
        $this->fakeClock->setTime(1003);
        $this->assertNotNull($queue->dequeue());
    }

    /**
     * Test max backoff cap
     */
    public function testMaxBackoffCap(): void
    {
        $queue = new TransactionQueue(
            options: [
                'maxAttempts' => 20,
                'baseBackoffSeconds' => 2,
                'maxBackoffSeconds' => 60, // Cap at 60 seconds
            ],
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123'],
            attempts: 10 // High attempt count to test cap
        );

        $queue->enqueue($job);
        $dequeued = $queue->dequeue();
        $queue->recordFailure($dequeued, new Exception('fail'));

        // Without cap, backoff would be 2 * 2^10 = 2048 seconds
        // With cap at 60, should be available after 60 seconds
        $this->fakeClock->setTime(1059);
        $this->assertNull($queue->dequeue());
        $this->fakeClock->setTime(1060);
        $this->assertNotNull($queue->dequeue());
    }

    /**
     * Test logging without payload exposure (SEC-001)
     */
    public function testLoggingWithoutPayloadExposure(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now(),
            logger: $this->mockLogger
        );

        $sensitivePayload = [
            'privateKey' => 'secret123',
            'to' => '0x123',
            'amount' => 1.0,
        ];

        $job = new TransactionJob(
            id: 'job-1',
            payload: $sensitivePayload,
            metadata: ['from' => '0x456']
        );

        // Enqueue
        $queue->enqueue($job);
        $this->assertStringContainsString('job-1', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('secret123', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('privateKey', $this->mockLogger->getLastMessage());

        // Dequeue
        $dequeued = $queue->dequeue();
        $this->assertStringContainsString('job-1', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('secret123', $this->mockLogger->getLastMessage());

        // Record failure
        $queue->recordFailure($dequeued, new Exception('Network error'));
        $this->assertStringContainsString('job-1', $this->mockLogger->getLastMessage());
        $this->assertStringContainsString('Network error', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('secret123', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('privateKey', $this->mockLogger->getLastMessage());

        // Acknowledge
        $this->fakeClock->advance(10);
        $retried = $queue->dequeue();
        $queue->acknowledge($retried);
        $this->assertStringContainsString('job-1', $this->mockLogger->getLastMessage());
        $this->assertStringNotContainsString('secret123', $this->mockLogger->getLastMessage());
    }

    /**
     * Test TransactionJob immutability and getters
     */
    public function testTransactionJobGetters(): void
    {
        $payload = ['to' => '0x123', 'amount' => 1.0];
        $metadata = ['from' => '0x456', 'nonce' => 1];

        $job = new TransactionJob(
            id: 'job-1',
            payload: $payload,
            metadata: $metadata,
            attempts: 3,
            nextAvailableAt: 1234567890,
            idempotencyToken: 'token-123'
        );

        $this->assertEquals('job-1', $job->getId());
        $this->assertEquals($payload, $job->getPayload());
        $this->assertEquals($metadata, $job->getMetadata());
        $this->assertEquals(3, $job->getAttempts());
        $this->assertEquals(1234567890, $job->getNextAvailableAt());
        $this->assertEquals('token-123', $job->getIdempotencyToken());
    }

    /**
     * Test TransactionJob with default values
     */
    public function testTransactionJobDefaults(): void
    {
        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123']
        );

        $this->assertEquals('job-1', $job->getId());
        $this->assertEquals(['to' => '0x123'], $job->getPayload());
        $this->assertEquals([], $job->getMetadata());
        $this->assertEquals(0, $job->getAttempts());
        $this->assertEquals(0, $job->getNextAvailableAt());
        $this->assertNull($job->getIdempotencyToken());
    }

    /**
     * Test multiple jobs with different timing
     */
    public function testMultipleJobsWithDifferentTiming(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        // Job 1: available immediately
        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x111'],
            nextAvailableAt: 1000
        );

        // Job 2: available at 1050
        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x222'],
            nextAvailableAt: 1050
        );

        // Job 3: available at 1100
        $job3 = new TransactionJob(
            id: 'job-3',
            payload: ['to' => '0x333'],
            nextAvailableAt: 1100
        );

        $queue->enqueue($job2);
        $queue->enqueue($job1);
        $queue->enqueue($job3);

        $this->assertEquals(3, $queue->size());

        // At 1000, only job1 should be available
        $dequeued1 = $queue->dequeue();
        $this->assertNotNull($dequeued1);
        $this->assertEquals('job-1', $dequeued1->getId());
        $this->assertEquals(2, $queue->size());

        // At 1000, job2 and job3 are not ready
        $this->assertNull($queue->dequeue());

        // At 1050, job2 should be available
        $this->fakeClock->setTime(1050);
        $dequeued2 = $queue->dequeue();
        $this->assertNotNull($dequeued2);
        $this->assertEquals('job-2', $dequeued2->getId());
        $this->assertEquals(1, $queue->size());

        // At 1050, job3 is not ready yet
        $this->assertNull($queue->dequeue());

        // At 1100, job3 should be available
        $this->fakeClock->setTime(1100);
        $dequeued3 = $queue->dequeue();
        $this->assertNotNull($dequeued3);
        $this->assertEquals('job-3', $dequeued3->getId());
        $this->assertEquals(0, $queue->size());
    }

    /**
     * Test queue preserves jobs not ready for dequeue
     */
    public function testQueuePreservesNotReadyJobs(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
        );

        $this->fakeClock->setTime(1000);

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123'],
            nextAvailableAt: 2000
        );

        $queue->enqueue($job);
        $this->assertEquals(1, $queue->size());

        // Try to dequeue multiple times before ready
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($queue->dequeue());
            $this->assertEquals(1, $queue->size(), "Job should still be in queue after attempt $i");
        }

        // Advance to ready time
        $this->fakeClock->setTime(2000);
        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);
        $this->assertEquals('job-1', $dequeued->getId());
        $this->assertEquals(0, $queue->size());
    }

    /**
     * Test queue with idempotency store prevents duplicates (TASK-005)
     */
    public function testQueueWithIdempotencyStorePreventsDuplicates(): void
    {
        $store = new \Blockchain\Storage\InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now(),
            idempotencyStore: $store
        );

        $token = 'duplicate-token-123';

        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token // Same token
        );

        // Enqueue first job
        $queue->enqueue($job1);
        $this->assertEquals(1, $queue->size());

        // Attempt to enqueue duplicate - should be skipped
        $queue->enqueue($job2);
        $this->assertEquals(1, $queue->size());

        // Verify only first job is in queue
        $dequeued = $queue->dequeue();
        $this->assertEquals('job-1', $dequeued->getId());
    }

    /**
     * Test queue without idempotency store allows all jobs (TASK-005)
     */
    public function testQueueWithoutIdempotencyStoreAllowsAllJobs(): void
    {
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now()
            // No idempotency store
        );

        $token = 'duplicate-token-123';

        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token // Same token
        );

        // Without store, both jobs should be enqueued
        $queue->enqueue($job1);
        $queue->enqueue($job2);
        $this->assertEquals(2, $queue->size());
    }

    /**
     * Test queue with different tokens allows all jobs (TASK-005)
     */
    public function testQueueWithDifferentTokensAllowsAllJobs(): void
    {
        $store = new \Blockchain\Storage\InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            clockFn: fn() => $this->fakeClock->now(),
            idempotencyStore: $store
        );

        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: 'token-1'
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x789', 'amount' => 2.0],
            metadata: ['from' => '0xabc'],
            idempotencyToken: 'token-2'
        );

        // Different tokens should both be enqueued
        $queue->enqueue($job1);
        $queue->enqueue($job2);
        $this->assertEquals(2, $queue->size());
    }
}

/**
 * FakeClock
 *
 * Test helper for deterministic time control in tests.
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

/**
 * MockLogger
 *
 * Test helper for verifying logging behavior without exposing sensitive data.
 */
class MockLogger implements LoggerInterface
{
    private array $logs = [];

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function getLastMessage(): string
    {
        if (empty($this->logs)) {
            return '';
        }

        $last = end($this->logs);
        return $last['message'] . ' ' . json_encode($last['context']);
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function clear(): void
    {
        $this->logs = [];
    }
}
