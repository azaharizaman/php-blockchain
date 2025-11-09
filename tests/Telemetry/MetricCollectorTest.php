<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use Blockchain\Telemetry\MetricCollector;
use Blockchain\Telemetry\ExporterInterface;

/**
 * MetricCollectorTest
 *
 * Comprehensive test suite for the MetricCollector class.
 * Tests timer accuracy, counter increments, gauge updates, histogram recording,
 * context attachment, and aggregate calculations.
 */
class MetricCollectorTest extends TestCase
{
    private InMemoryExporter $exporter;
    private MetricCollector $collector;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->collector = new MetricCollector($this->exporter);
    }

    /**
     * Test that MetricCollector can be instantiated
     */
    public function testCanBeInstantiated(): void
    {
        $collector = new MetricCollector($this->exporter);
        $this->assertInstanceOf(MetricCollector::class, $collector);
    }

    /**
     * Test starting and stopping a timer
     */
    public function testStartAndStopTimer(): void
    {
        $timerId = $this->collector->startTimer('test.timer');
        $this->assertIsString($timerId);
        $this->assertNotEmpty($timerId);

        // Small delay to ensure measurable time
        usleep(10000); // 10ms

        $elapsed = $this->collector->stopTimer($timerId);
        $this->assertIsFloat($elapsed);
        $this->assertGreaterThan(5, $elapsed); // Should be at least 5ms
    }

    /**
     * Test timer accuracy with controlled delays
     */
    public function testTimerAccuracy(): void
    {
        $timerId = $this->collector->startTimer('accuracy.test');
        usleep(20000); // 20ms delay
        $elapsed = $this->collector->stopTimer($timerId);

        // Allow for some variance in timing
        $this->assertGreaterThan(15, $elapsed);
        $this->assertLessThan(50, $elapsed);
    }

    /**
     * Test that stopping an invalid timer throws exception
     */
    public function testStopInvalidTimerThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timer ID');
        $this->collector->stopTimer('invalid_timer_id');
    }

    /**
     * Test multiple timers can run concurrently
     */
    public function testMultipleConcurrentTimers(): void
    {
        $timer1 = $this->collector->startTimer('timer1');
        usleep(5000);
        $timer2 = $this->collector->startTimer('timer2');
        usleep(5000);

        $elapsed1 = $this->collector->stopTimer($timer1);
        $elapsed2 = $this->collector->stopTimer($timer2);

        $this->assertGreaterThan($elapsed2, $elapsed1);
    }

    /**
     * Test increment counter with default value
     */
    public function testIncrementCounterDefault(): void
    {
        $this->collector->increment('test.counter');
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(1, $metrics['test.counter']);
    }

    /**
     * Test increment counter with custom value
     */
    public function testIncrementCounterCustomValue(): void
    {
        $this->collector->increment('test.counter', 5);
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(5, $metrics['test.counter']);
    }

    /**
     * Test multiple increments accumulate
     */
    public function testMultipleIncrementsAccumulate(): void
    {
        $this->collector->increment('test.counter', 3);
        $this->collector->increment('test.counter', 2);
        $this->collector->increment('test.counter', 5);
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(10, $metrics['test.counter']);
    }

    /**
     * Test gauge sets value
     */
    public function testGaugeSetsValue(): void
    {
        $this->collector->gauge('queue.depth', 42.5);
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(42.5, $metrics['queue.depth']);
    }

    /**
     * Test gauge updates replace previous value
     */
    public function testGaugeUpdatesReplacePreviousValue(): void
    {
        $this->collector->gauge('temperature', 20.0);
        $this->collector->gauge('temperature', 25.5);
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(25.5, $metrics['temperature']);
    }

    /**
     * Test histogram records values
     */
    public function testHistogramRecordsValues(): void
    {
        $this->collector->histogram('response.size', 1024.0);
        $this->collector->histogram('response.size', 2048.0);
        $this->collector->histogram('response.size', 512.0);
        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertIsArray($metrics['response.size']);
        $this->assertEquals(3, $metrics['response.size']['count']);
        $this->assertEquals(3584.0, $metrics['response.size']['sum']);
        $this->assertEquals(512.0, $metrics['response.size']['min']);
        $this->assertEquals(2048.0, $metrics['response.size']['max']);
        $this->assertEquals(1194.666666666667, $metrics['response.size']['avg'], '', 0.001);
    }

    /**
     * Test withContext returns new instance with context
     */
    public function testWithContextReturnsNewInstance(): void
    {
        $original = $this->collector;
        $withContext = $original->withContext(['driver' => 'ethereum']);

        $this->assertNotSame($original, $withContext);
        $this->assertInstanceOf(MetricCollector::class, $withContext);
    }

    /**
     * Test context is included in exported metrics
     */
    public function testContextIncludedInExportedMetrics(): void
    {
        $collector = $this->collector->withContext([
            'driver' => 'ethereum',
            'network' => 'mainnet',
        ]);

        $collector->increment('test.counter');
        $collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertArrayHasKey('context', $metrics);
        $this->assertEquals('ethereum', $metrics['context']['driver']);
        $this->assertEquals('mainnet', $metrics['context']['network']);
    }

    /**
     * Test context merges correctly
     */
    public function testContextMergesCorrectly(): void
    {
        $collector = $this->collector
            ->withContext(['a' => 1, 'b' => 2])
            ->withContext(['b' => 3, 'c' => 4]);

        $collector->increment('test.counter');
        $collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertEquals(1, $metrics['context']['a']);
        $this->assertEquals(3, $metrics['context']['b']); // Should be overwritten
        $this->assertEquals(4, $metrics['context']['c']);
    }

    /**
     * Test flush exports buffered metrics
     */
    public function testFlushExportsMetrics(): void
    {
        $this->collector->increment('test.counter');
        $this->assertEquals(0, $this->exporter->getExportCount());

        $this->collector->flush();
        $this->assertEquals(1, $this->exporter->getExportCount());
    }

    /**
     * Test flush clears buffer
     */
    public function testFlushClearsBuffer(): void
    {
        $this->collector->increment('test.counter');
        $this->collector->flush();

        $metrics1 = $this->exporter->getLastExport();
        $this->assertEquals(1, $metrics1['test.counter']);

        // Add new metric and flush again
        $this->collector->increment('test.counter');
        $this->collector->flush();

        $metrics2 = $this->exporter->getLastExport();
        // Should be 1 again, not 2, proving buffer was cleared
        $this->assertEquals(1, $metrics2['test.counter']);
    }

    /**
     * Test flush with empty buffer does nothing
     */
    public function testFlushWithEmptyBufferDoesNothing(): void
    {
        $this->collector->flush();
        $this->assertEquals(0, $this->exporter->getExportCount());
    }

    /**
     * Test reset clears buffer
     */
    public function testResetClearsBuffer(): void
    {
        $this->collector->increment('test.counter');
        $this->collector->reset();
        $this->collector->flush();

        $this->assertEquals(0, $this->exporter->getExportCount());
    }

    /**
     * Test reset clears active timers
     */
    public function testResetClearsActiveTimers(): void
    {
        $timerId = $this->collector->startTimer('test.timer');
        $this->collector->reset();

        $this->expectException(\InvalidArgumentException::class);
        $this->collector->stopTimer($timerId);
    }

    /**
     * Test auto-flush on destruct
     */
    public function testAutoFlushOnDestruct(): void
    {
        $exporter = new InMemoryExporter();
        $collector = new MetricCollector($exporter);

        $collector->increment('test.counter');
        $this->assertEquals(0, $exporter->getExportCount());

        // Trigger destructor
        unset($collector);

        // Should have auto-flushed
        $this->assertEquals(1, $exporter->getExportCount());
    }

    /**
     * Test auto-flush with empty buffer does nothing
     */
    public function testAutoFlushWithEmptyBufferDoesNothing(): void
    {
        $exporter = new InMemoryExporter();
        $collector = new MetricCollector($exporter);

        // Trigger destructor with empty buffer
        unset($collector);

        $this->assertEquals(0, $exporter->getExportCount());
    }

    /**
     * Test timer aggregates are calculated correctly
     */
    public function testTimerAggregatesCalculatedCorrectly(): void
    {
        // Start and stop multiple timers with the same name
        $timer1 = $this->collector->startTimer('operation.duration');
        usleep(10000); // 10ms
        $elapsed1 = $this->collector->stopTimer($timer1);

        $timer2 = $this->collector->startTimer('operation.duration');
        usleep(20000); // 20ms
        $elapsed2 = $this->collector->stopTimer($timer2);

        $this->collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);
        $this->assertIsArray($metrics['operation.duration']);
        $this->assertEquals(2, $metrics['operation.duration']['count']);
        $this->assertGreaterThan(25, $metrics['operation.duration']['sum']);
        $this->assertLessThan($elapsed2, $metrics['operation.duration']['min']);
        $this->assertGreaterThan($elapsed1, $metrics['operation.duration']['max']);
    }

    /**
     * Test mixed metric types in single flush
     */
    public function testMixedMetricTypesInSingleFlush(): void
    {
        $timerId = $this->collector->startTimer('operation.duration');
        usleep(5000);
        $this->collector->stopTimer($timerId);

        $this->collector->increment('requests.count', 5);
        $this->collector->gauge('queue.depth', 42);
        $this->collector->histogram('response.size', 1024);
        $this->collector->histogram('response.size', 2048);

        $collector = $this->collector->withContext(['service' => 'api']);
        $collector->flush();

        $metrics = $this->exporter->getLastExport();
        $this->assertNotNull($metrics);

        // Check all metric types are present
        $this->assertArrayHasKey('operation.duration', $metrics);
        $this->assertArrayHasKey('requests.count', $metrics);
        $this->assertArrayHasKey('queue.depth', $metrics);
        $this->assertArrayHasKey('response.size', $metrics);
        $this->assertArrayHasKey('context', $metrics);

        // Verify values
        $this->assertEquals(5, $metrics['requests.count']);
        $this->assertEquals(42, $metrics['queue.depth']);
        $this->assertEquals(2, $metrics['response.size']['count']);
        $this->assertEquals('api', $metrics['context']['service']);
    }

    /**
     * Test that collector works with NoopExporter
     */
    public function testWorksWithNoopExporter(): void
    {
        $noopExporter = new \Blockchain\Telemetry\NoopExporter();
        $collector = new MetricCollector($noopExporter);

        $collector->increment('test.counter');
        $collector->gauge('test.gauge', 42);
        $collector->flush();

        // Should not throw any exceptions
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test timer returns elapsed time on stop
     */
    public function testTimerReturnsElapsedTimeOnStop(): void
    {
        $timerId = $this->collector->startTimer('test.timer');
        usleep(10000); // 10ms
        $elapsed = $this->collector->stopTimer($timerId);

        $this->assertIsFloat($elapsed);
        $this->assertGreaterThan(5, $elapsed);
    }

    /**
     * Test context doesn't interfere with original instance
     */
    public function testContextDoesNotInterfereWithOriginalInstance(): void
    {
        $original = $this->collector;
        $withContext = $original->withContext(['added' => 'context']);

        $original->increment('original.counter');
        $withContext->increment('context.counter');

        $original->flush();
        $withContext->flush();

        $exports = $this->exporter->getExportedMetrics();
        $this->assertCount(2, $exports);

        // First export should not have context
        $this->assertArrayNotHasKey('context', $exports[0]);
        $this->assertArrayHasKey('original.counter', $exports[0]);

        // Second export should have context
        $this->assertArrayHasKey('context', $exports[1]);
        $this->assertEquals('context', $exports[1]['context']['added']);
    }
}
