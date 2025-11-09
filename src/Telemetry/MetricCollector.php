<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

/**
 * MetricCollector
 *
 * Helper class that drivers can use to record timing, counters, and context
 * for telemetry export. Provides timer, counter, gauge, and histogram methods
 * with context attachment support.
 *
 * ## Key Features
 *
 * 1. **Timer Support**: Start/stop timers to measure operation duration
 * 2. **Counters**: Increment counters for event tracking
 * 3. **Gauges**: Record point-in-time values
 * 4. **Histograms**: Record distributions of values
 * 5. **Context Attachment**: Add metadata to all metrics
 * 6. **Auto-Flush**: Automatically export metrics on destruct
 *
 * ## Usage Examples
 *
 * ### Basic Timer Usage
 *
 * ```php
 * $collector = new MetricCollector($exporter);
 * $timerId = $collector->startTimer('database.query');
 * // ... perform operation ...
 * $elapsed = $collector->stopTimer($timerId);
 * echo "Query took {$elapsed}ms";
 * ```
 *
 * ### Counter and Gauge
 *
 * ```php
 * $collector->increment('requests.total');
 * $collector->increment('errors.count', 1);
 * $collector->gauge('queue.depth', 42);
 * ```
 *
 * ### With Context
 *
 * ```php
 * $collector = (new MetricCollector($exporter))
 *     ->withContext(['driver' => 'ethereum', 'network' => 'mainnet']);
 *
 * $collector->increment('transactions.sent');
 * // Context is automatically included in exported metrics
 * ```
 *
 * ### Histogram
 *
 * ```php
 * $collector->histogram('response.size', 1024.5);
 * $collector->histogram('response.size', 2048.0);
 * // Values are aggregated in the exporter
 * ```
 *
 * @package Blockchain\Telemetry
 */
class MetricCollector
{
    private ExporterInterface $exporter;

    /**
     * @var array<string,mixed> Context metadata attached to metrics
     */
    private array $context = [];

    /**
     * @var array<string,mixed> Buffer for metrics before export
     */
    private array $buffer = [];

    /**
     * @var array<string,array{start: float, name: string}> Active timers
     */
    private array $timers = [];

    /**
     * Constructor
     *
     * @param ExporterInterface $exporter Exporter for sending metrics
     */
    public function __construct(ExporterInterface $exporter)
    {
        $this->exporter = $exporter;
    }

    /**
     * Start a timer for measuring operation duration
     *
     * Returns a unique timer ID that must be passed to stopTimer() to
     * complete the measurement.
     *
     * @param string $name Name of the metric to measure
     *
     * @return string Timer ID for use with stopTimer()
     */
    public function startTimer(string $name): string
    {
        $timerId = uniqid('timer_', true);
        $this->timers[$timerId] = [
            'start' => microtime(true),
            'name' => $name,
        ];

        return $timerId;
    }

    /**
     * Stop a timer and record the elapsed time
     *
     * Calculates the elapsed time in milliseconds and stores it in the buffer.
     * Returns the elapsed time for immediate use if needed.
     *
     * @param string $timerId Timer ID from startTimer()
     *
     * @return float Elapsed time in milliseconds
     * @throws \InvalidArgumentException If timer ID is invalid
     */
    public function stopTimer(string $timerId): float
    {
        if (!isset($this->timers[$timerId])) {
            throw new \InvalidArgumentException("Invalid timer ID: {$timerId}");
        }

        $timer = $this->timers[$timerId];
        $elapsed = (microtime(true) - $timer['start']) * 1000; // Convert to milliseconds

        // Store timing in buffer
        $metricName = $timer['name'];
        if (!isset($this->buffer[$metricName])) {
            $this->buffer[$metricName] = [];
        } elseif (!is_array($this->buffer[$metricName])) {
            // Convert scalar to array if needed
            $this->buffer[$metricName] = [$this->buffer[$metricName]];
        }
        $this->buffer[$metricName][] = $elapsed;

        unset($this->timers[$timerId]);

        return $elapsed;
    }

    /**
     * Increment a counter metric
     *
     * @param string $metric Counter metric name
     * @param int $value Amount to increment (default: 1)
     *
     * @return void
     */
    public function increment(string $metric, int $value = 1): void
    {
        if (!isset($this->buffer[$metric])) {
            $this->buffer[$metric] = 0;
        } elseif (is_array($this->buffer[$metric])) {
            // If it's an array (from timer/histogram), sum it first
            $this->buffer[$metric] = array_sum($this->buffer[$metric]);
        }
        $this->buffer[$metric] += $value;
    }

    /**
     * Set a gauge metric to a specific value
     *
     * Gauges represent point-in-time values that can go up or down.
     *
     * @param string $metric Gauge metric name
     * @param float $value Value to set
     *
     * @return void
     */
    public function gauge(string $metric, float $value): void
    {
        $this->buffer[$metric] = $value;
    }

    /**
     * Record a histogram value
     *
     * Histograms track distributions of values. Multiple values for the
     * same metric are stored as an array for aggregation by the exporter.
     *
     * @param string $metric Histogram metric name
     * @param float $value Value to record
     *
     * @return void
     */
    public function histogram(string $metric, float $value): void
    {
        if (!isset($this->buffer[$metric])) {
            $this->buffer[$metric] = [];
        }
        if (!is_array($this->buffer[$metric])) {
            $this->buffer[$metric] = [$this->buffer[$metric]];
        }
        $this->buffer[$metric][] = $value;
    }

    /**
     * Create a new collector instance with additional context
     *
     * Returns a new instance with merged context. The context is included
     * in all exported metrics. This method is immutable - it returns a new
     * instance rather than modifying the current one.
     *
     * @param array<string,mixed> $context Context metadata to attach
     *
     * @return self New collector instance with context
     */
    public function withContext(array $context): self
    {
        $new = clone $this;
        $new->context = array_merge($this->context, $context);
        return $new;
    }

    /**
     * Export all buffered metrics to the exporter
     *
     * Calculates aggregates for timing and histogram data, merges context,
     * and sends everything to the exporter. Clears the buffer after export.
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $metrics = $this->calculateAggregates($this->buffer);

        // Merge context into metrics
        if (!empty($this->context)) {
            $metrics['context'] = $this->context;
        }

        $this->exporter->export($metrics);
        $this->buffer = [];
    }

    /**
     * Clear the metric buffer without exporting
     *
     * @return void
     */
    public function reset(): void
    {
        $this->buffer = [];
        $this->timers = [];
    }

    /**
     * Calculate aggregates for histogram and timing data
     *
     * @param array<string,mixed> $data Raw metric data
     *
     * @return array<string,mixed> Metrics with aggregates
     */
    private function calculateAggregates(array $data): array
    {
        $metrics = [];

        foreach ($data as $name => $value) {
            if (is_array($value)) {
                // Calculate aggregates for array values (timers/histograms)
                $metrics[$name] = [
                    'count' => count($value),
                    'sum' => array_sum($value),
                    'min' => min($value),
                    'max' => max($value),
                    'avg' => array_sum($value) / count($value),
                ];
            } else {
                // Simple scalar value (counter/gauge)
                $metrics[$name] = $value;
            }
        }

        return $metrics;
    }

    /**
     * Auto-flush on destruct if buffer is not empty
     */
    public function __destruct()
    {
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }
}
