<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

/**
 * ExporterInterface
 *
 * Interface for exporting telemetry metrics to external monitoring systems.
 * Implementations can send metrics to various backends such as Prometheus,
 * StatsD, CloudWatch, or custom monitoring solutions.
 *
 * This interface enables pluggable telemetry exporters, allowing users to
 * choose their preferred monitoring backend without changing application code.
 *
 * ## Key Features
 *
 * 1. **Pluggable Architecture**: Swap exporters without code changes
 * 2. **Opt-In Telemetry**: Use NoopExporter by default for zero overhead
 * 3. **Flexible Metrics**: Support for various metric types (counters, gauges, histograms)
 *
 * ## Metrics Structure
 *
 * The metrics array passed to export() should contain telemetry data with the
 * following structure:
 *
 * ```php
 * $metrics = [
 *     'request_latency_ms' => 245,
 *     'error_count' => 1,
 *     'success_count' => 99,
 *     'queue_depth' => 15,
 *     'timestamp' => 1699522152,
 *     'labels' => [
 *         'driver' => 'ethereum',
 *         'operation' => 'sendTransaction',
 *     ],
 * ];
 * ```
 *
 * ## Usage Examples
 *
 * ### Using Default NoopExporter
 *
 * ```php
 * use Blockchain\Telemetry\NoopExporter;
 *
 * $exporter = new NoopExporter();
 * $exporter->export($metrics); // No-op, zero overhead
 * ```
 *
 * ### Implementing Custom Exporter
 *
 * ```php
 * use Blockchain\Telemetry\ExporterInterface;
 *
 * class PrometheusExporter implements ExporterInterface
 * {
 *     private PrometheusClient $client;
 *
 *     public function export(array $metrics): void
 *     {
 *         foreach ($metrics as $name => $value) {
 *             if (is_numeric($value)) {
 *                 $this->client->gauge($name, $value);
 *             }
 *         }
 *     }
 * }
 * ```
 *
 * @package Blockchain\Telemetry
 */
interface ExporterInterface
{
    /**
     * Export metrics to the monitoring backend
     *
     * This method receives an array of metrics and is responsible for
     * sending them to the appropriate monitoring system. The implementation
     * should handle any necessary formatting, batching, or buffering.
     *
     * @param array<string,mixed> $metrics Associative array of metric names to values
     *                                      May include nested arrays for labels or metadata
     *
     * @return void
     */
    public function export(array $metrics): void;
}
