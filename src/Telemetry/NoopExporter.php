<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

/**
 * NoopExporter
 *
 * Default no-operation implementation of ExporterInterface that provides zero-cost
 * telemetry when monitoring is not needed. This exporter discards all metrics,
 * making telemetry completely opt-in.
 *
 * ## Key Features
 *
 * 1. **Zero Overhead**: All metrics are discarded with no processing or I/O
 * 2. **Opt-In Telemetry**: Makes instrumentation optional by default (CON-001)
 * 3. **Safe Default**: Can be used without configuration or setup
 * 4. **Null Object Pattern**: Eliminates need for null checks in calling code
 *
 * ## When to Use
 *
 * - **Default Configuration**: Use as the default exporter in SDK configuration
 * - **Development**: When you don't need telemetry during local development
 * - **Testing**: When you want to verify instrumentation without actual export
 * - **Disabled Monitoring**: When monitoring is temporarily disabled
 *
 * ## Compliance
 *
 * This implementation satisfies requirement CON-001: "Telemetry must be opt-in
 * and not enabled by default." By using NoopExporter as the default, no metrics
 * are sent unless the user explicitly configures a real exporter.
 *
 * ## Usage Examples
 *
 * ### As Default Exporter
 *
 * ```php
 * use Blockchain\Telemetry\NoopExporter;
 * use Blockchain\BlockchainManager;
 *
 * // Telemetry is disabled by default
 * $blockchain = new BlockchainManager('ethereum', $config);
 * // No metrics exported
 * ```
 *
 * ### Explicit Instantiation
 *
 * ```php
 * use Blockchain\Telemetry\NoopExporter;
 *
 * $exporter = new NoopExporter();
 * $exporter->export([
 *     'request_latency_ms' => 250,
 *     'error_count' => 0,
 * ]);
 * // Metrics are safely discarded
 * ```
 *
 * ### Conditional Telemetry
 *
 * ```php
 * use Blockchain\Telemetry\ExporterInterface;
 * use Blockchain\Telemetry\NoopExporter;
 *
 * class TelemetryService
 * {
 *     private ExporterInterface $exporter;
 *
 *     public function __construct(?ExporterInterface $exporter = null)
 *     {
 *         // Default to noop if no exporter provided
 *         $this->exporter = $exporter ?? new NoopExporter();
 *     }
 *
 *     public function recordMetric(string $name, mixed $value): void
 *     {
 *         $this->exporter->export([$name => $value]);
 *     }
 * }
 * ```
 *
 * ## Design Rationale
 *
 * The NoopExporter implements the Null Object pattern, which:
 * - Eliminates conditional logic in calling code
 * - Ensures consistent interface across enabled/disabled states
 * - Provides compile-time type safety
 * - Simplifies testing by providing a safe default
 *
 * @package Blockchain\Telemetry
 */
class NoopExporter implements ExporterInterface
{
    /**
     * Export metrics (no-op implementation)
     *
     * This method intentionally does nothing. All metrics passed to it are
     * discarded without any processing, logging, or I/O operations. This
     * ensures zero performance overhead when telemetry is not needed.
     *
     * @param array<string,mixed> $metrics Metrics to discard (unused)
     *
     * @return void
     */
    public function export(array $metrics): void
    {
        // Intentionally empty - no-op implementation
        // All metrics are discarded to ensure zero overhead
    }
}
