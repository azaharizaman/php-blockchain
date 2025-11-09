<?php

declare(strict_types=1);

namespace Blockchain\Tests\Reliability;

use Blockchain\Reliability\CircuitBreaker;
use Blockchain\Reliability\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for CircuitBreaker.
 *
 * Verifies that the circuit breaker:
 * - Implements state machine correctly (closed, open, half-open)
 * - Tracks failures within a sliding window
 * - Opens circuit when threshold reached
 * - Transitions to half-open after cooldown
 * - Closes circuit after successful recovery
 * - Supports forced open for maintenance
 */
class CircuitBreakerTest extends TestCase
{
    /**
     * Test that CircuitBreaker can be instantiated with default parameters.
     */
    public function testCircuitBreakerCanBeInstantiatedWithDefaults(): void
    {
        $breaker = new CircuitBreaker();

        $this->assertInstanceOf(CircuitBreaker::class, $breaker);
        $this->assertSame(5, $breaker->getFailureThreshold());
        $this->assertSame(60, $breaker->getWindowSizeSeconds());
        $this->assertSame(30, $breaker->getCooldownSeconds());
        $this->assertSame(2, $breaker->getSuccessThreshold());
    }

    /**
     * Test that CircuitBreaker can be instantiated with custom parameters.
     */
    public function testCircuitBreakerCanBeInstantiatedWithCustomParameters(): void
    {
        $breaker = new CircuitBreaker(
            failureThreshold: 3,
            windowSizeSeconds: 30,
            cooldownSeconds: 15,
            successThreshold: 1
        );

        $this->assertSame(3, $breaker->getFailureThreshold());
        $this->assertSame(30, $breaker->getWindowSizeSeconds());
        $this->assertSame(15, $breaker->getCooldownSeconds());
        $this->assertSame(1, $breaker->getSuccessThreshold());
    }

    /**
     * Test that CircuitBreaker throws exception for invalid failure threshold.
     */
    public function testCircuitBreakerThrowsExceptionForInvalidFailureThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('failureThreshold must be at least 1');

        new CircuitBreaker(failureThreshold: 0);
    }

    /**
     * Test that CircuitBreaker throws exception for invalid window size.
     */
    public function testCircuitBreakerThrowsExceptionForInvalidWindowSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('windowSizeSeconds must be greater than 0');

        new CircuitBreaker(windowSizeSeconds: 0);
    }

    /**
     * Test that CircuitBreaker throws exception for invalid cooldown.
     */
    public function testCircuitBreakerThrowsExceptionForInvalidCooldown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cooldownSeconds must be greater than 0');

        new CircuitBreaker(cooldownSeconds: 0);
    }

    /**
     * Test that CircuitBreaker throws exception for invalid success threshold.
     */
    public function testCircuitBreakerThrowsExceptionForInvalidSuccessThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('successThreshold must be at least 1');

        new CircuitBreaker(successThreshold: 0);
    }

    /**
     * Test that circuit breaker starts in closed state.
     */
    public function testCircuitBreakerStartsInClosedState(): void
    {
        $breaker = new CircuitBreaker();

        $this->assertTrue($breaker->isClosed());
        $this->assertFalse($breaker->isOpen());
        $this->assertFalse($breaker->isHalfOpen());
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $breaker->getState());
    }

    /**
     * Test successful operation execution in closed state.
     */
    public function testSuccessfulOperationInClosedState(): void
    {
        $breaker = new CircuitBreaker();

        $result = $breaker->call(fn() => 'success');

        $this->assertSame('success', $result);
        $this->assertTrue($breaker->isClosed());
        $this->assertSame(0, $breaker->getFailureCount());
    }

    /**
     * Test that failures are tracked.
     */
    public function testFailuresAreTracked(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 5);

        // Record 3 failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertSame(3, $breaker->getFailureCount());
        $this->assertTrue($breaker->isClosed()); // Still closed, under threshold
    }

    /**
     * Test that circuit opens when failure threshold exceeded.
     */
    public function testCircuitOpensWhenThresholdExceeded(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3);

        // Record 3 failures to exceed threshold
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertTrue($breaker->isOpen());
        $this->assertFalse($breaker->isClosed());
        $this->assertSame(CircuitBreaker::STATE_OPEN, $breaker->getState());
    }

    /**
     * Test that open circuit fails fast.
     */
    public function testOpenCircuitFailsFast(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 1);

        // Open the circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertTrue($breaker->isOpen());

        // Next call should fail fast
        $this->expectException(CircuitBreakerOpenException::class);
        $this->expectExceptionMessage('Circuit breaker is open. Service is unavailable.');

        $breaker->call(fn() => 'should not execute');
    }

    /**
     * Test circuit transitions to half-open after cooldown.
     */
    public function testCircuitTransitionsToHalfOpenAfterCooldown(): void
    {
        $breaker = new TestableCircuitBreaker(
            failureThreshold: 1,
            cooldownSeconds: 10
        );

        // Open the circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertTrue($breaker->isOpen());

        // Advance time past cooldown
        $breaker->advanceTime(11);

        // Next call should transition to half-open and execute
        $result = $breaker->call(fn() => 'success');

        $this->assertSame('success', $result);
        $this->assertTrue($breaker->isHalfOpen());
    }

    /**
     * Test circuit closes from half-open after success threshold.
     */
    public function testCircuitClosesFromHalfOpenAfterSuccesses(): void
    {
        $breaker = new TestableCircuitBreaker(
            failureThreshold: 1,
            cooldownSeconds: 10,
            successThreshold: 2
        );

        // Open the circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Advance past cooldown to half-open
        $breaker->advanceTime(11);
        $breaker->call(fn() => 'first success');
        $this->assertTrue($breaker->isHalfOpen());

        // Second success should close circuit
        $breaker->call(fn() => 'second success');
        $this->assertTrue($breaker->isClosed());
    }

    /**
     * Test failure in half-open state reopens circuit.
     */
    public function testFailureInHalfOpenReopensCircuit(): void
    {
        $breaker = new TestableCircuitBreaker(
            failureThreshold: 1,
            cooldownSeconds: 10
        );

        // Open the circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Advance past cooldown to half-open
        $breaker->advanceTime(11);
        $breaker->call(fn() => 'success'); // Transitions to half-open
        $this->assertTrue($breaker->isHalfOpen());

        // Failure should reopen
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertTrue($breaker->isOpen());
    }

    /**
     * Test force open functionality.
     */
    public function testForceOpen(): void
    {
        $breaker = new CircuitBreaker();

        $this->assertTrue($breaker->isClosed());

        $breaker->forceOpen();

        $this->assertTrue($breaker->isOpen());
        $this->assertTrue($breaker->isForcedOpen());
    }

    /**
     * Test forced open circuit does not transition to half-open.
     */
    public function testForcedOpenDoesNotTransitionToHalfOpen(): void
    {
        $breaker = new TestableCircuitBreaker(cooldownSeconds: 10);

        $breaker->forceOpen();
        $this->assertTrue($breaker->isOpen());

        // Advance time past cooldown
        $breaker->advanceTime(15);

        // Should still be open
        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->call(fn() => 'should not execute');
    }

    /**
     * Test manual close resets state.
     */
    public function testManualCloseResetsState(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 1);

        // Open the circuit
        try {
            $breaker->call(function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertTrue($breaker->isOpen());
        $this->assertSame(1, $breaker->getFailureCount());

        // Manually close
        $breaker->close();

        $this->assertTrue($breaker->isClosed());
        $this->assertSame(0, $breaker->getFailureCount());
        $this->assertFalse($breaker->isForcedOpen());
    }

    /**
     * Test manual close works even when forced open.
     */
    public function testManualCloseWorksWhenForcedOpen(): void
    {
        $breaker = new CircuitBreaker();

        $breaker->forceOpen();
        $this->assertTrue($breaker->isForcedOpen());

        $breaker->close();

        $this->assertTrue($breaker->isClosed());
        $this->assertFalse($breaker->isForcedOpen());
    }

    /**
     * Test sliding window removes old failures.
     */
    public function testSlidingWindowRemovesOldFailures(): void
    {
        $breaker = new TestableCircuitBreaker(
            failureThreshold: 5,
            windowSizeSeconds: 60
        );

        // Record 3 failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertSame(3, $breaker->getFailureCount());

        // Advance time past window
        $breaker->advanceTime(61);

        // Old failures should be removed
        $this->assertSame(0, $breaker->getFailureCount());
        $this->assertTrue($breaker->isClosed());
    }

    /**
     * Test success resets failures in closed state.
     */
    public function testSuccessResetsFailuresInClosedState(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 5);

        // Record some failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertSame(3, $breaker->getFailureCount());

        // Success should reset
        $breaker->call(fn() => 'success');

        $this->assertSame(0, $breaker->getFailureCount());
    }

    /**
     * Test that callable return value is preserved.
     */
    public function testCallableReturnValueIsPreserved(): void
    {
        $breaker = new CircuitBreaker();

        $result = $breaker->call(fn() => ['data' => 'test', 'count' => 42]);

        $this->assertSame(['data' => 'test', 'count' => 42], $result);
    }

    /**
     * Test that exceptions are propagated.
     */
    public function testExceptionsArePropagated(): void
    {
        $breaker = new CircuitBreaker();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Custom error message');

        $breaker->call(function () {
            throw new \RuntimeException('Custom error message');
        });
    }

    /**
     * Test concurrent failure tracking.
     */
    public function testConcurrentFailureTracking(): void
    {
        $breaker = new CircuitBreaker(
            failureThreshold: 3,
            windowSizeSeconds: 60
        );

        // Simulate multiple failures
        $failureCount = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                $breaker->call(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                $failureCount++;
            } catch (CircuitBreakerOpenException $e) {
                // Circuit opened after threshold
                break;
            }
        }

        $this->assertSame(3, $failureCount);
        $this->assertTrue($breaker->isOpen());
    }
}

/**
 * Testable version of CircuitBreaker with controllable time.
 *
 * This class allows tests to control time progression without actually waiting.
 */
class TestableCircuitBreaker extends CircuitBreaker
{
    /**
     * Current simulated time in seconds.
     */
    private float $currentTime = 0.0;

    /**
     * Get the current simulated time.
     *
     * @return float Current time in seconds
     */
    protected function getCurrentTime(): float
    {
        return $this->currentTime;
    }

    /**
     * Advance the simulated time.
     *
     * @param float $seconds Time to advance in seconds
     * @return void
     */
    public function advanceTime(float $seconds): void
    {
        $this->currentTime += $seconds;
    }
}
