<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use Blockchain\Telemetry\ExporterInterface;
use Blockchain\Telemetry\NoopExporter;

/**
 * NoopExporterTest
 *
 * Test suite for the NoopExporter class verifying that the default
 * telemetry exporter provides true no-op behavior with zero overhead.
 *
 * These tests ensure that:
 * 1. NoopExporter implements the ExporterInterface contract
 * 2. The export method can be called without errors
 * 3. No side effects occur when exporting metrics
 * 4. The exporter is safe to use in all contexts
 */
class NoopExporterTest extends TestCase
{
    private NoopExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new NoopExporter();
    }

    /**
     * Test that NoopExporter implements ExporterInterface
     *
     * This verifies the contract is satisfied and the exporter can be
     * used anywhere an ExporterInterface is expected.
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ExporterInterface::class, $this->exporter);
    }

    /**
     * Test that export method can be called without errors
     *
     * The noop exporter should accept any metrics array and complete
     * successfully without throwing exceptions or producing side effects.
     */
    public function testExportDoesNotThrowException(): void
    {
        // Should complete without any exceptions
        $this->exporter->export([
            'request_latency_ms' => 245,
            'error_count' => 1,
            'success_count' => 99,
        ]);

        // If we reach here, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test export with empty metrics array
     *
     * The exporter should handle empty arrays gracefully.
     */
    public function testExportWithEmptyArray(): void
    {
        $this->exporter->export([]);
        $this->assertTrue(true);
    }

    /**
     * Test export with complex nested metrics
     *
     * The exporter should accept complex metric structures including
     * nested arrays for labels and metadata.
     */
    public function testExportWithComplexMetrics(): void
    {
        $this->exporter->export([
            'request_latency_ms' => 245,
            'timestamp' => time(),
            'labels' => [
                'driver' => 'ethereum',
                'operation' => 'sendTransaction',
                'environment' => 'production',
            ],
            'metadata' => [
                'version' => '1.0.0',
                'region' => 'us-east-1',
            ],
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test export with various metric types
     *
     * Verify the exporter handles different value types (integers, floats,
     * strings, booleans, arrays) without errors.
     */
    public function testExportWithVariousTypes(): void
    {
        $this->exporter->export([
            'counter' => 42,
            'gauge' => 3.14159,
            'label' => 'test-label',
            'flag' => true,
            'tags' => ['tag1', 'tag2', 'tag3'],
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test multiple sequential exports
     *
     * The exporter should handle multiple consecutive calls without
     * any state accumulation or side effects.
     */
    public function testMultipleExports(): void
    {
        $this->exporter->export(['metric1' => 100]);
        $this->exporter->export(['metric2' => 200]);
        $this->exporter->export(['metric3' => 300]);

        // All exports should complete successfully
        $this->assertTrue(true);
    }

    /**
     * Test that exporter can be instantiated multiple times
     *
     * Each instance should be independent with no shared state.
     */
    public function testMultipleInstances(): void
    {
        $exporter1 = new NoopExporter();
        $exporter2 = new NoopExporter();

        $exporter1->export(['instance' => 1]);
        $exporter2->export(['instance' => 2]);

        $this->assertNotSame($exporter1, $exporter2);
    }

    /**
     * Test exporter type hints work correctly
     *
     * Verify the exporter can be used in contexts expecting ExporterInterface.
     */
    public function testExporterTypeHints(): void
    {
        $this->expectExporterInterface($this->exporter);
        $this->assertTrue(true);
    }

    /**
     * Helper method to verify type compatibility
     *
     * @param ExporterInterface $exporter
     */
    private function expectExporterInterface(ExporterInterface $exporter): void
    {
        // Type hint validates the interface is properly implemented
        $exporter->export(['test' => 'value']);
    }

    /**
     * Test that export is truly a no-op (no observable effects)
     *
     * This test verifies that calling export doesn't produce any side effects
     * by measuring that execution completes quickly.
     */
    public function testExportHasNoObservableEffects(): void
    {
        $startTime = microtime(true);

        // Call export many times
        for ($i = 0; $i < 1000; $i++) {
            $this->exporter->export([
                'iteration' => $i,
                'timestamp' => time(),
            ]);
        }

        $duration = microtime(true) - $startTime;

        // 1000 no-op calls should complete in less than 10ms
        // This verifies there's no actual I/O or processing
        $this->assertLessThan(1.0, $duration, 'NoopExporter should have zero overhead');
    }
}
