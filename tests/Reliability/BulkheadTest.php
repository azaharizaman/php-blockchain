<?php

declare(strict_types=1);

namespace Blockchain\Tests\Reliability;

use Blockchain\Reliability\Bulkhead;
use Blockchain\Reliability\BulkheadFullException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Bulkhead.
 *
 * Verifies that the bulkhead:
 * - Limits concurrent operations correctly
 * - Tracks active operation count
 * - Throws exception when limit exceeded
 * - Releases slots properly after completion
 * - Provides accurate statistics
 */
class BulkheadTest extends TestCase
{
    /**
     * Test that Bulkhead can be instantiated with default parameters.
     */
    public function testBulkheadCanBeInstantiatedWithDefaults(): void
    {
        $bulkhead = new Bulkhead();

        $this->assertInstanceOf(Bulkhead::class, $bulkhead);
        $this->assertSame(10, $bulkhead->getMaxConcurrent());
        $this->assertSame(0, $bulkhead->getMaxQueueSize());
        $this->assertSame(30, $bulkhead->getQueueTimeoutSeconds());
    }

    /**
     * Test that Bulkhead can be instantiated with custom parameters.
     */
    public function testBulkheadCanBeInstantiatedWithCustomParameters(): void
    {
        $bulkhead = new Bulkhead(
            maxConcurrent: 5,
            maxQueueSize: 10,
            queueTimeoutSeconds: 60
        );

        $this->assertSame(5, $bulkhead->getMaxConcurrent());
        $this->assertSame(10, $bulkhead->getMaxQueueSize());
        $this->assertSame(60, $bulkhead->getQueueTimeoutSeconds());
    }

    /**
     * Test that Bulkhead throws exception for invalid max concurrent.
     */
    public function testBulkheadThrowsExceptionForInvalidMaxConcurrent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxConcurrent must be at least 1');

        new Bulkhead(maxConcurrent: 0);
    }

    /**
     * Test that Bulkhead throws exception for negative queue size.
     */
    public function testBulkheadThrowsExceptionForNegativeQueueSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxQueueSize must be non-negative');

        new Bulkhead(maxQueueSize: -1);
    }

    /**
     * Test that Bulkhead throws exception for invalid timeout.
     */
    public function testBulkheadThrowsExceptionForInvalidTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('queueTimeoutSeconds must be greater than 0');

        new Bulkhead(queueTimeoutSeconds: 0);
    }

    /**
     * Test that bulkhead starts with zero active operations.
     */
    public function testBulkheadStartsWithZeroActive(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 5);

        $this->assertSame(0, $bulkhead->getActiveCount());
        $this->assertSame(5, $bulkhead->getAvailableSlots());
        $this->assertTrue($bulkhead->hasCapacity());
    }

    /**
     * Test successful operation execution.
     */
    public function testSuccessfulOperationExecution(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 5);

        $result = $bulkhead->execute(fn() => 'success');

        $this->assertSame('success', $result);
        $this->assertSame(0, $bulkhead->getActiveCount()); // Released after completion
    }

    /**
     * Test that active count increases during execution.
     */
    public function testActiveCountIncreasesDuringExecution(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 5);
        $executionCount = 0;

        $bulkhead->execute(function () use ($bulkhead, &$executionCount) {
            $executionCount++;
            // During execution, active count should be 1
            $this->assertSame(1, $bulkhead->getActiveCount());
            return 'result';
        });

        // After completion, should be released
        $this->assertSame(1, $executionCount);
        $this->assertSame(0, $bulkhead->getActiveCount());
    }

    /**
     * Test concurrent operations are limited.
     */
    public function testConcurrentOperationsAreLimited(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 3);

        // Manually acquire slots
        $this->assertTrue($bulkhead->tryAcquire());
        $this->assertSame(1, $bulkhead->getActiveCount());

        $this->assertTrue($bulkhead->tryAcquire());
        $this->assertSame(2, $bulkhead->getActiveCount());

        $this->assertTrue($bulkhead->tryAcquire());
        $this->assertSame(3, $bulkhead->getActiveCount());

        // Fourth attempt should fail
        $this->assertFalse($bulkhead->tryAcquire());
        $this->assertSame(3, $bulkhead->getActiveCount());
    }

    /**
     * Test exception thrown when bulkhead is full.
     */
    public function testExceptionThrownWhenBulkheadFull(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 1);

        // First operation acquires the slot
        $bulkhead->tryAcquire();
        $this->assertSame(1, $bulkhead->getActiveCount());

        // Second operation should throw
        $this->expectException(BulkheadFullException::class);
        $this->expectExceptionMessage('Bulkhead is full');

        $bulkhead->execute(fn() => 'should not execute');
    }

    /**
     * Test slots are released after operation completes.
     */
    public function testSlotsReleasedAfterCompletion(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2);

        $this->assertSame(2, $bulkhead->getAvailableSlots());

        $bulkhead->execute(fn() => 'operation 1');

        // Should be back to full capacity
        $this->assertSame(2, $bulkhead->getAvailableSlots());
        $this->assertSame(0, $bulkhead->getActiveCount());
    }

    /**
     * Test slots are released even when operation throws exception.
     */
    public function testSlotsReleasedOnException(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2);

        try {
            $bulkhead->execute(function () {
                throw new \RuntimeException('Operation failed');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Slot should be released
        $this->assertSame(0, $bulkhead->getActiveCount());
        $this->assertSame(2, $bulkhead->getAvailableSlots());
    }

    /**
     * Test manual acquire and release.
     */
    public function testManualAcquireAndRelease(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 3);

        $this->assertTrue($bulkhead->hasCapacity());
        $this->assertSame(3, $bulkhead->getAvailableSlots());

        // Acquire
        $this->assertTrue($bulkhead->tryAcquire());
        $this->assertSame(1, $bulkhead->getActiveCount());
        $this->assertSame(2, $bulkhead->getAvailableSlots());

        // Release
        $bulkhead->release();
        $this->assertSame(0, $bulkhead->getActiveCount());
        $this->assertSame(3, $bulkhead->getAvailableSlots());
    }

    /**
     * Test hasCapacity method.
     */
    public function testHasCapacity(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2);

        $this->assertTrue($bulkhead->hasCapacity());

        $bulkhead->tryAcquire();
        $this->assertTrue($bulkhead->hasCapacity());

        $bulkhead->tryAcquire();
        $this->assertFalse($bulkhead->hasCapacity());

        $bulkhead->release();
        $this->assertTrue($bulkhead->hasCapacity());
    }

    /**
     * Test reset functionality.
     */
    public function testReset(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 3);

        // Acquire some slots
        $bulkhead->tryAcquire();
        $bulkhead->tryAcquire();
        $this->assertSame(2, $bulkhead->getActiveCount());

        // Reset
        $bulkhead->reset();

        $this->assertSame(0, $bulkhead->getActiveCount());
        $this->assertSame(3, $bulkhead->getAvailableSlots());
        $this->assertTrue($bulkhead->hasCapacity());
    }

    /**
     * Test statistics.
     */
    public function testStatistics(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 10, maxQueueSize: 5);

        $stats = $bulkhead->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('maxConcurrent', $stats);
        $this->assertArrayHasKey('available', $stats);
        $this->assertArrayHasKey('queueSize', $stats);
        $this->assertArrayHasKey('maxQueueSize', $stats);
        $this->assertArrayHasKey('utilizationPercent', $stats);

        $this->assertSame(0, $stats['active']);
        $this->assertSame(10, $stats['maxConcurrent']);
        $this->assertSame(10, $stats['available']);
        $this->assertSame(0, $stats['queueSize']);
        $this->assertSame(5, $stats['maxQueueSize']);
        $this->assertSame(0.0, $stats['utilizationPercent']);
    }

    /**
     * Test utilization percentage calculation.
     */
    public function testUtilizationPercentage(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 10);

        // 0% utilization
        $stats = $bulkhead->getStats();
        $this->assertSame(0.0, $stats['utilizationPercent']);

        // 50% utilization
        for ($i = 0; $i < 5; $i++) {
            $bulkhead->tryAcquire();
        }
        $stats = $bulkhead->getStats();
        $this->assertSame(50.0, $stats['utilizationPercent']);

        // 100% utilization
        for ($i = 0; $i < 5; $i++) {
            $bulkhead->tryAcquire();
        }
        $stats = $bulkhead->getStats();
        $this->assertSame(100.0, $stats['utilizationPercent']);
    }

    /**
     * Test multiple sequential operations.
     */
    public function testMultipleSequentialOperations(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2);
        $results = [];

        for ($i = 1; $i <= 5; $i++) {
            $result = $bulkhead->execute(fn() => "operation $i");
            $results[] = $result;
        }

        $this->assertCount(5, $results);
        $this->assertSame('operation 1', $results[0]);
        $this->assertSame('operation 5', $results[4]);
        $this->assertSame(0, $bulkhead->getActiveCount());
    }

    /**
     * Test callable return value is preserved.
     */
    public function testCallableReturnValueIsPreserved(): void
    {
        $bulkhead = new Bulkhead();

        $result = $bulkhead->execute(fn() => ['data' => 'test', 'value' => 123]);

        $this->assertSame(['data' => 'test', 'value' => 123], $result);
    }

    /**
     * Test exceptions are propagated.
     */
    public function testExceptionsArePropagated(): void
    {
        $bulkhead = new Bulkhead();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation error');

        $bulkhead->execute(function () {
            throw new \RuntimeException('Operation error');
        });
    }

    /**
     * Test release is idempotent.
     */
    public function testReleaseIsIdempotent(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 5);

        $this->assertSame(0, $bulkhead->getActiveCount());

        // Release when no active operations
        $bulkhead->release();

        $this->assertSame(0, $bulkhead->getActiveCount());

        // Acquire and release multiple times
        $bulkhead->tryAcquire();
        $bulkhead->release();
        $bulkhead->release(); // Extra release should be safe

        $this->assertSame(0, $bulkhead->getActiveCount());
    }

    /**
     * Test queue size tracking.
     */
    public function testQueueSizeTracking(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2, maxQueueSize: 5);

        $this->assertSame(0, $bulkhead->getQueueSize());
        $this->assertSame(5, $bulkhead->getMaxQueueSize());
    }

    /**
     * Test available slots calculation.
     */
    public function testAvailableSlotsCalculation(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 5);

        $this->assertSame(5, $bulkhead->getAvailableSlots());

        $bulkhead->tryAcquire();
        $this->assertSame(4, $bulkhead->getAvailableSlots());

        $bulkhead->tryAcquire();
        $bulkhead->tryAcquire();
        $this->assertSame(2, $bulkhead->getAvailableSlots());

        $bulkhead->release();
        $this->assertSame(3, $bulkhead->getAvailableSlots());
    }

    /**
     * Test that available slots never go negative.
     */
    public function testAvailableSlotsNeverNegative(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 2);

        // Fill all slots
        $bulkhead->tryAcquire();
        $bulkhead->tryAcquire();

        $this->assertSame(0, $bulkhead->getAvailableSlots());

        // Try to acquire more (should fail)
        $this->assertFalse($bulkhead->tryAcquire());

        // Should still be 0, not negative
        $this->assertSame(0, $bulkhead->getAvailableSlots());
    }

    /**
     * Test single concurrent operation limit.
     */
    public function testSingleConcurrentOperationLimit(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 1);

        $this->assertTrue($bulkhead->tryAcquire());
        $this->assertFalse($bulkhead->tryAcquire());

        $bulkhead->release();

        $this->assertTrue($bulkhead->tryAcquire());
    }

    /**
     * Test high concurrency limit.
     */
    public function testHighConcurrencyLimit(): void
    {
        $bulkhead = new Bulkhead(maxConcurrent: 100);

        // Acquire many slots
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($bulkhead->tryAcquire());
        }

        $this->assertSame(50, $bulkhead->getActiveCount());
        $this->assertSame(50, $bulkhead->getAvailableSlots());
        $this->assertTrue($bulkhead->hasCapacity());

        // Fill remaining slots
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($bulkhead->tryAcquire());
        }

        $this->assertSame(100, $bulkhead->getActiveCount());
        $this->assertSame(0, $bulkhead->getAvailableSlots());
        $this->assertFalse($bulkhead->hasCapacity());
    }
}
