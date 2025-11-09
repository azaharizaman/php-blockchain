<?php

declare(strict_types=1);

namespace Blockchain\Tests\Reliability;

use Blockchain\Reliability\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for RateLimiter.
 *
 * Verifies that the rate limiter:
 * - Implements token-bucket algorithm correctly
 * - Enforces rate limits properly
 * - Refills tokens at the correct rate
 * - Handles burst capacity correctly
 * - Provides blocking and non-blocking acquire methods
 */
class RateLimiterTest extends TestCase
{
    /**
     * Test that RateLimiter can be instantiated with default parameters.
     */
    public function testRateLimiterCanBeInstantiatedWithDefaults(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0);

        $this->assertInstanceOf(RateLimiter::class, $limiter);
        $this->assertSame(10.0, $limiter->getRequestsPerSecond());
        $this->assertSame(10, $limiter->getBucketCapacity());
    }

    /**
     * Test that RateLimiter can be instantiated with custom capacity.
     */
    public function testRateLimiterCanBeInstantiatedWithCustomCapacity(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 20);

        $this->assertSame(10.0, $limiter->getRequestsPerSecond());
        $this->assertSame(20, $limiter->getBucketCapacity());
    }

    /**
     * Test that RateLimiter throws exception for invalid rate.
     */
    public function testRateLimiterThrowsExceptionForInvalidRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requestsPerSecond must be greater than 0');

        new RateLimiter(requestsPerSecond: 0);
    }

    /**
     * Test that RateLimiter throws exception for negative rate.
     */
    public function testRateLimiterThrowsExceptionForNegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requestsPerSecond must be greater than 0');

        new RateLimiter(requestsPerSecond: -1);
    }

    /**
     * Test that RateLimiter starts with a full bucket.
     */
    public function testRateLimiterStartsWithFullBucket(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 20);

        $this->assertSame(20.0, $limiter->getAvailableTokens());
    }

    /**
     * Test that tryAcquire consumes tokens successfully.
     */
    public function testTryAcquireConsumesTokens(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 5);

        // Initial state: 5 tokens
        $this->assertSame(5.0, $limiter->getAvailableTokens());

        // Acquire 1 token
        $this->assertTrue($limiter->tryAcquire(1));
        $this->assertSame(4.0, $limiter->getAvailableTokens());

        // Acquire 2 more tokens
        $this->assertTrue($limiter->tryAcquire(2));
        $this->assertSame(2.0, $limiter->getAvailableTokens());
    }

    /**
     * Test that tryAcquire fails when insufficient tokens.
     */
    public function testTryAcquireFailsWhenInsufficientTokens(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 5);

        // Consume all tokens
        $this->assertTrue($limiter->tryAcquire(5));
        $this->assertSame(0.0, $limiter->getAvailableTokens());

        // Try to acquire more - should fail
        $this->assertFalse($limiter->tryAcquire(1));
        $this->assertSame(0.0, $limiter->getAvailableTokens());
    }

    /**
     * Test that tryAcquire validates token count.
     */
    public function testTryAcquireValidatesTokenCount(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tokens must be at least 1');

        $limiter->tryAcquire(0);
    }

    /**
     * Test that acquire validates token count.
     */
    public function testAcquireValidatesTokenCount(): void
    {
        $limiter = new TestableRateLimiter(requestsPerSecond: 10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tokens must be at least 1');

        $limiter->acquire(0);
    }

    /**
     * Test that reset refills bucket to capacity.
     */
    public function testResetRefillsBucketToCapacity(): void
    {
        $limiter = new RateLimiter(requestsPerSecond: 10.0, bucketCapacity: 5);

        // Consume some tokens
        $limiter->tryAcquire(3);
        $this->assertSame(2.0, $limiter->getAvailableTokens());

        // Reset
        $limiter->reset();
        $this->assertSame(5.0, $limiter->getAvailableTokens());
    }

    /**
     * Test token refill over time using a testable version.
     */
    public function testTokenRefillOverTime(): void
    {
        $limiter = new TestableRateLimiter(requestsPerSecond: 10.0, bucketCapacity: 10);

        // Start with full bucket
        $this->assertSame(10.0, $limiter->getAvailableTokens());

        // Consume all tokens
        $this->assertTrue($limiter->tryAcquire(10));
        $this->assertSame(0.0, $limiter->getAvailableTokens());

        // Simulate 0.5 second passing (should refill 5 tokens)
        $limiter->advanceTime(500_000); // 0.5 seconds in microseconds
        $this->assertEqualsWithDelta(5.0, $limiter->getAvailableTokens(), 0.01);

        // Simulate another 0.5 second (should refill to capacity of 10)
        $limiter->advanceTime(500_000);
        $this->assertSame(10.0, $limiter->getAvailableTokens());
    }

    /**
     * Test that tokens don't exceed bucket capacity.
     */
    public function testTokensDoNotExceedCapacity(): void
    {
        $limiter = new TestableRateLimiter(requestsPerSecond: 10.0, bucketCapacity: 5);

        // Start with full bucket
        $this->assertSame(5.0, $limiter->getAvailableTokens());

        // Simulate 10 seconds passing (would add 100 tokens, but cap is 5)
        $limiter->advanceTime(10_000_000);
        $this->assertSame(5.0, $limiter->getAvailableTokens());
    }

    /**
     * Test fractional token refill.
     */
    public function testFractionalTokenRefill(): void
    {
        $limiter = new TestableRateLimiter(requestsPerSecond: 10.0, bucketCapacity: 10);

        // Consume all tokens
        $limiter->tryAcquire(10);
        $this->assertSame(0.0, $limiter->getAvailableTokens());

        // Simulate 0.05 seconds (should add 0.5 tokens)
        $limiter->advanceTime(50_000); // 0.05 seconds
        $this->assertEqualsWithDelta(0.5, $limiter->getAvailableTokens(), 0.01);

        // Still can't acquire a full token
        $this->assertFalse($limiter->tryAcquire(1));
    }

    /**
     * Test rate limiting with fractional rates.
     */
    public function testRateLimitingWithFractionalRates(): void
    {
        // 0.5 requests per second = 1 request every 2 seconds
        $limiter = new TestableRateLimiter(requestsPerSecond: 0.5, bucketCapacity: 1);

        // Start with 1 token
        $this->assertSame(1.0, $limiter->getAvailableTokens());

        // Consume it
        $this->assertTrue($limiter->tryAcquire(1));
        $this->assertSame(0.0, $limiter->getAvailableTokens());

        // After 1 second, should have 0.5 tokens (can't acquire yet)
        $limiter->advanceTime(1_000_000);
        $this->assertEqualsWithDelta(0.5, $limiter->getAvailableTokens(), 0.01);
        $this->assertFalse($limiter->tryAcquire(1));

        // After 2 seconds total, should have 1 token (can acquire)
        $limiter->advanceTime(1_000_000);
        $this->assertEqualsWithDelta(1.0, $limiter->getAvailableTokens(), 0.01);
        $this->assertTrue($limiter->tryAcquire(1));
    }

    /**
     * Test burst capacity allows temporary spike.
     */
    public function testBurstCapacityAllowsTemporarySpike(): void
    {
        // 1 request/sec sustained, but burst of 10
        $limiter = new TestableRateLimiter(requestsPerSecond: 1.0, bucketCapacity: 10);

        // Can make 10 requests immediately (burst)
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->tryAcquire(1), "Request $i should succeed");
        }

        // 11th request fails
        $this->assertFalse($limiter->tryAcquire(1));

        // After 1 second, can make 1 more request
        $limiter->advanceTime(1_000_000);
        $this->assertTrue($limiter->tryAcquire(1));
    }

    /**
     * Test that acquire with multiple tokens works.
     */
    public function testAcquireWithMultipleTokens(): void
    {
        $limiter = new TestableRateLimiter(requestsPerSecond: 10.0, bucketCapacity: 10);

        // Acquire 5 tokens at once
        $limiter->acquire(5);
        $this->assertSame(5.0, $limiter->getAvailableTokens());

        // Acquire 3 more
        $limiter->acquire(3);
        $this->assertSame(2.0, $limiter->getAvailableTokens());
    }

    /**
     * Test high rate limiting scenario.
     */
    public function testHighRateLimiting(): void
    {
        // 1000 requests per second
        $limiter = new TestableRateLimiter(requestsPerSecond: 1000.0, bucketCapacity: 1000);

        // Should be able to make 1000 requests immediately
        $this->assertTrue($limiter->tryAcquire(1000));
        $this->assertSame(0.0, $limiter->getAvailableTokens());

        // After 0.1 seconds, should have 100 tokens
        $limiter->advanceTime(100_000); // 0.1 seconds
        $this->assertEqualsWithDelta(100.0, $limiter->getAvailableTokens(), 0.1);
    }
}

/**
 * Testable version of RateLimiter with controllable time.
 *
 * This class allows tests to control time progression without actually waiting,
 * making tests fast and deterministic.
 */
class TestableRateLimiter extends RateLimiter
{
    /**
     * Current simulated time in microseconds.
     */
    private float $currentTime = 0.0;

    /**
     * Create a testable rate limiter starting at time 0.
     */
    public function __construct(float $requestsPerSecond, ?int $bucketCapacity = null)
    {
        parent::__construct($requestsPerSecond, $bucketCapacity);
        $this->currentTime = 0.0;
    }

    /**
     * Get the current simulated time.
     *
     * @return float Current time in microseconds
     */
    protected function getCurrentTime(): float
    {
        return $this->currentTime;
    }

    /**
     * Don't actually sleep in tests.
     *
     * @param int $milliseconds Delay duration in milliseconds
     * @return void
     */
    protected function delay(int $milliseconds): void
    {
        // Don't actually sleep in tests
        // Time progression is controlled via advanceTime()
    }

    /**
     * Advance the simulated time.
     *
     * @param float $microseconds Time to advance in microseconds
     * @return void
     */
    public function advanceTime(float $microseconds): void
    {
        $this->currentTime += $microseconds;
    }
}
