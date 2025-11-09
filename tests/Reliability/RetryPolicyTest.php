<?php

declare(strict_types=1);

namespace Blockchain\Tests\Reliability;

use Blockchain\Reliability\RetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for RetryPolicy.
 *
 * Verifies that the retry policy:
 * - Implements exponential backoff correctly
 * - Applies jitter to delays
 * - Respects maximum delay caps
 * - Handles retryable and non-retryable exceptions
 * - Executes operations the correct number of times
 */
class RetryPolicyTest extends TestCase
{
    /**
     * Test that RetryPolicy can be instantiated with default parameters.
     */
    public function testRetryPolicyCanBeInstantiatedWithDefaults(): void
    {
        $policy = new RetryPolicy();

        $this->assertInstanceOf(RetryPolicy::class, $policy);
        $this->assertSame(3, $policy->getMaxAttempts());
        $this->assertSame(100, $policy->getBaseDelayMs());
        $this->assertSame(2.0, $policy->getBackoffMultiplier());
        $this->assertSame(0, $policy->getJitterMs());
        $this->assertSame(30000, $policy->getMaxDelayMs());
    }

    /**
     * Test that RetryPolicy can be instantiated with custom parameters.
     */
    public function testRetryPolicyCanBeInstantiatedWithCustomParameters(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 5,
            baseDelayMs: 200,
            backoffMultiplier: 3.0,
            jitterMs: 50,
            maxDelayMs: 60000
        );

        $this->assertSame(5, $policy->getMaxAttempts());
        $this->assertSame(200, $policy->getBaseDelayMs());
        $this->assertSame(3.0, $policy->getBackoffMultiplier());
        $this->assertSame(50, $policy->getJitterMs());
        $this->assertSame(60000, $policy->getMaxDelayMs());
    }

    /**
     * Test that RetryPolicy throws exception for invalid maxAttempts.
     */
    public function testRetryPolicyThrowsExceptionForInvalidMaxAttempts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxAttempts must be at least 1');

        new RetryPolicy(maxAttempts: 0);
    }

    /**
     * Test that RetryPolicy throws exception for negative base delay.
     */
    public function testRetryPolicyThrowsExceptionForNegativeBaseDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('baseDelayMs must be non-negative');

        new RetryPolicy(baseDelayMs: -1);
    }

    /**
     * Test that RetryPolicy throws exception for invalid backoff multiplier.
     */
    public function testRetryPolicyThrowsExceptionForInvalidBackoffMultiplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('backoffMultiplier must be at least 1.0');

        new RetryPolicy(backoffMultiplier: 0.5);
    }

    /**
     * Test that RetryPolicy throws exception for negative jitter.
     */
    public function testRetryPolicyThrowsExceptionForNegativeJitter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('jitterMs must be non-negative');

        new RetryPolicy(jitterMs: -1);
    }

    /**
     * Test that RetryPolicy throws exception for invalid max delay.
     */
    public function testRetryPolicyThrowsExceptionForInvalidMaxDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDelayMs must be >= baseDelayMs');

        new RetryPolicy(baseDelayMs: 1000, maxDelayMs: 500);
    }

    /**
     * Test that successful operation on first attempt returns result.
     */
    public function testSuccessfulOperationOnFirstAttempt(): void
    {
        $policy = new RetryPolicy();
        $callCount = 0;

        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame(1, $callCount);
    }

    /**
     * Test that operation is retried on retryable exception.
     */
    public function testOperationRetriesOnRetryableException(): void
    {
        $policy = new TestableRetryPolicy(maxAttempts: 3);
        $callCount = 0;

        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new \RuntimeException('Temporary failure');
            }
            return 'success';
        }, [\RuntimeException::class]);

        $this->assertSame('success', $result);
        $this->assertSame(3, $callCount);
    }

    /**
     * Test that operation fails after max attempts.
     */
    public function testOperationFailsAfterMaxAttempts(): void
    {
        $policy = new TestableRetryPolicy(maxAttempts: 3);
        $callCount = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Always fails');

        try {
            $policy->execute(function () use (&$callCount) {
                $callCount++;
                throw new \RuntimeException('Always fails');
            }, [\RuntimeException::class]);
        } catch (\RuntimeException $e) {
            $this->assertSame(3, $callCount);
            throw $e;
        }
    }

    /**
     * Test that non-retryable exceptions are not retried.
     */
    public function testNonRetryableExceptionsAreNotRetried(): void
    {
        $policy = new TestableRetryPolicy(maxAttempts: 3);
        $callCount = 0;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-retryable error');

        try {
            $policy->execute(function () use (&$callCount) {
                $callCount++;
                throw new \InvalidArgumentException('Non-retryable error');
            }, [\RuntimeException::class]); // Only RuntimeException is retryable
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(1, $callCount);
            throw $e;
        }
    }

    /**
     * Test exponential backoff calculation without jitter.
     */
    public function testExponentialBackoffCalculationWithoutJitter(): void
    {
        $policy = new RetryPolicy(
            baseDelayMs: 100,
            backoffMultiplier: 2.0,
            jitterMs: 0
        );

        // First retry: 100 * 2^0 = 100ms
        $this->assertSame(100, $policy->calculateDelay(1));

        // Second retry: 100 * 2^1 = 200ms
        $this->assertSame(200, $policy->calculateDelay(2));

        // Third retry: 100 * 2^2 = 400ms
        $this->assertSame(400, $policy->calculateDelay(3));

        // Fourth retry: 100 * 2^3 = 800ms
        $this->assertSame(800, $policy->calculateDelay(4));
    }

    /**
     * Test that delay is capped at maxDelayMs.
     */
    public function testDelayIsCappedAtMaxDelay(): void
    {
        $policy = new RetryPolicy(
            baseDelayMs: 100,
            backoffMultiplier: 2.0,
            jitterMs: 0,
            maxDelayMs: 500
        );

        // First retry: 100ms (under cap)
        $this->assertSame(100, $policy->calculateDelay(1));

        // Second retry: 200ms (under cap)
        $this->assertSame(200, $policy->calculateDelay(2));

        // Third retry: should be 400ms, under cap
        $this->assertSame(400, $policy->calculateDelay(3));

        // Fourth retry: should be 800ms, but capped at 500ms
        $this->assertSame(500, $policy->calculateDelay(4));

        // Fifth retry: should be 1600ms, but capped at 500ms
        $this->assertSame(500, $policy->calculateDelay(5));
    }

    /**
     * Test that jitter adds randomness to delays.
     */
    public function testJitterAddsRandomnessToDelays(): void
    {
        $policy = new RetryPolicy(
            baseDelayMs: 100,
            backoffMultiplier: 1.0, // No exponential growth
            jitterMs: 50
        );

        // Calculate delays multiple times and check they vary
        $delays = [];
        for ($i = 0; $i < 10; $i++) {
            $delays[] = $policy->calculateDelay(1);
        }

        // All delays should be in range [100, 150]
        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(100, $delay);
            $this->assertLessThanOrEqual(150, $delay);
        }

        // With 10 samples, we should have some variation (not all the same)
        // This test might rarely fail due to randomness, but it's very unlikely
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should produce varied delays');
    }

    /**
     * Test that execute works with different exception hierarchies.
     */
    public function testExecuteWorksWithExceptionHierarchies(): void
    {
        $policy = new TestableRetryPolicy(maxAttempts: 2);
        $callCount = 0;

        // \Exception is parent of \RuntimeException
        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                throw new \RuntimeException('Child exception');
            }
            return 'success';
        }, [\Exception::class]); // Parent class is retryable

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }

    /**
     * Test that operation with zero base delay works.
     */
    public function testOperationWithZeroBaseDelay(): void
    {
        $policy = new TestableRetryPolicy(
            maxAttempts: 2,
            baseDelayMs: 0
        );
        $callCount = 0;

        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                throw new \RuntimeException('Fail once');
            }
            return 'success';
        }, [\RuntimeException::class]);

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }
}

/**
 * Testable version of RetryPolicy that doesn't actually sleep.
 *
 * This class overrides the delay() method to avoid sleeping during tests,
 * which would make tests slow and unreliable.
 */
class TestableRetryPolicy extends RetryPolicy
{
    /**
     * Track delays for testing purposes without actually sleeping.
     *
     * @param int $milliseconds Delay duration in milliseconds
     * @return void
     */
    protected function delay(int $milliseconds): void
    {
        // Don't actually sleep in tests
        // Delays are tested separately via calculateDelay()
    }
}
