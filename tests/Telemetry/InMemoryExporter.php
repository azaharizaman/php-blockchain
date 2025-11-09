<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use Blockchain\Telemetry\ExporterInterface;

/**
 * InMemoryExporter
 *
 * Test implementation of ExporterInterface that stores metrics in memory
 * for verification in unit tests. This exporter captures all exported metrics
 * and provides methods to retrieve them for assertions.
 *
 * ## Usage in Tests
 *
 * ```php
 * $exporter = new InMemoryExporter();
 * $collector = new MetricCollector($exporter);
 *
 * $collector->increment('test.counter');
 * $collector->flush();
 *
 * $metrics = $exporter->getExportedMetrics();
 * $this->assertEquals(1, $metrics[0]['test.counter']);
 * ```
 *
 * @package Tests\Telemetry
 */
class InMemoryExporter implements ExporterInterface
{
    /**
     * @var array<int,array<string,mixed>> All exported metric batches
     */
    private array $exportedMetrics = [];

    /**
     * Export metrics to memory storage
     *
     * @param array<string,mixed> $metrics Metrics to store
     *
     * @return void
     */
    public function export(array $metrics): void
    {
        $this->exportedMetrics[] = $metrics;
    }

    /**
     * Get all exported metric batches
     *
     * @return array<int,array<string,mixed>>
     */
    public function getExportedMetrics(): array
    {
        return $this->exportedMetrics;
    }

    /**
     * Get the last exported metric batch
     *
     * @return array<string,mixed>|null
     */
    public function getLastExport(): ?array
    {
        if (empty($this->exportedMetrics)) {
            return null;
        }

        return end($this->exportedMetrics);
    }

    /**
     * Get the number of times export was called
     *
     * @return int
     */
    public function getExportCount(): int
    {
        return count($this->exportedMetrics);
    }

    /**
     * Clear all stored metrics
     *
     * @return void
     */
    public function clear(): void
    {
        $this->exportedMetrics = [];
    }
}
