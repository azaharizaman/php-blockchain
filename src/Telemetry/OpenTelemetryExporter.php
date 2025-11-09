<?php

declare(strict_types=1);

namespace Blockchain\Telemetry;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * OpenTelemetryExporter
 *
 * OpenTelemetry-compatible exporter that converts SDK metrics to OT format
 * for integration with observability platforms like Jaeger, Prometheus, and
 * cloud-native monitoring solutions.
 *
 * ## Key Features
 *
 * 1. **OT Protocol Compliance**: Converts metrics to OpenTelemetry format
 * 2. **Batch Export**: Configurable batch sizes for efficient network usage
 * 3. **Dry-Run Mode**: Test metrics without sending to collector
 * 4. **Error Resilience**: Graceful error handling with logging
 * 5. **Flexible Authentication**: Support for headers and bearer tokens
 *
 * ## Configuration
 *
 * Required configuration keys:
 * - `endpoint` (string): The OpenTelemetry collector endpoint URL
 *                        Example: 'http://localhost:4318/v1/metrics'
 *
 * Optional configuration keys:
 * - `headers` (array): Additional HTTP headers for authentication
 *                      Example: ['Authorization' => 'Bearer token123']
 * - `timeout` (int): HTTP timeout in seconds (default: 30)
 * - `batch_size` (int): Maximum metrics per batch (default: 100)
 * - `dry_run` (bool): If true, no network calls are made (default: false)
 * - `resource_attributes` (array): Resource attributes to include with all metrics
 *                                  Example: ['service.name' => 'blockchain-api']
 *
 * Environment variable overrides:
 * - `OTEL_EXPORTER_OTLP_ENDPOINT`: Overrides endpoint configuration
 * - `OTEL_EXPORTER_OTLP_HEADERS`: Comma-separated key=value pairs
 *
 * ## Metric Conversion
 *
 * SDK metrics are converted to OpenTelemetry format:
 * - Timer metrics (arrays with aggregates) → Histogram
 * - Counters (scalar integers that accumulate) → Counter
 * - Gauges (scalar floats that can vary) → Gauge
 * - Context metadata → Resource attributes
 *
 * ## Usage Examples
 *
 * ### Basic Configuration
 *
 * ```php
 * use Blockchain\Telemetry\OpenTelemetryExporter;
 *
 * $exporter = new OpenTelemetryExporter([
 *     'endpoint' => 'http://localhost:4318/v1/metrics',
 * ]);
 *
 * $exporter->export([
 *     'request_latency_ms' => ['count' => 1, 'sum' => 245.5, 'min' => 245.5, 'max' => 245.5, 'avg' => 245.5],
 *     'request_count' => 42,
 *     'active_connections' => 15.5,
 * ]);
 * ```
 *
 * ### With Authentication
 *
 * ```php
 * $exporter = new OpenTelemetryExporter([
 *     'endpoint' => 'https://otlp.example.com/v1/metrics',
 *     'headers' => [
 *         'Authorization' => 'Bearer my-secret-token',
 *         'X-Custom-Header' => 'value',
 *     ],
 * ]);
 * ```
 *
 * ### Dry-Run Mode
 *
 * ```php
 * $exporter = new OpenTelemetryExporter([
 *     'endpoint' => 'http://localhost:4318/v1/metrics',
 *     'dry_run' => true, // No actual network calls
 * ]);
 *
 * // Metrics are validated and converted but not sent
 * $exporter->export($metrics);
 * ```
 *
 * ### With Resource Attributes
 *
 * ```php
 * $exporter = new OpenTelemetryExporter([
 *     'endpoint' => 'http://localhost:4318/v1/metrics',
 *     'resource_attributes' => [
 *         'service.name' => 'blockchain-api',
 *         'service.version' => '1.0.0',
 *         'deployment.environment' => 'production',
 *     ],
 * ]);
 * ```
 *
 * ### With Custom Batch Size
 *
 * ```php
 * $exporter = new OpenTelemetryExporter([
 *     'endpoint' => 'http://localhost:4318/v1/metrics',
 *     'batch_size' => 50, // Flush after 50 metrics
 * ]);
 * ```
 *
 * ## OpenTelemetry Compatibility
 *
 * This exporter implements the OpenTelemetry Metrics Protocol v0.19.0:
 * - Uses OTLP/HTTP JSON encoding
 * - Supports histogram, counter, and gauge metrics
 * - Includes resource attributes and scope
 * - Compatible with OTEL collectors 0.80.0+
 *
 * @package Blockchain\Telemetry
 */
class OpenTelemetryExporter implements ExporterInterface
{
    /**
     * Configuration array
     *
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * HTTP client adapter for making requests
     */
    private GuzzleAdapter $adapter;

    /**
     * Logger for error reporting
     */
    private LoggerInterface $logger;

    /**
     * Buffer for batching metrics
     *
     * @var array<int,array<string,mixed>>
     */
    private array $buffer = [];

    /**
     * Create a new OpenTelemetryExporter instance
     *
     * @param array<string,mixed> $config Configuration array
     * @param LoggerInterface|null $logger Optional logger for error reporting
     *
     * @throws ConfigurationException If endpoint is missing or invalid
     *
     * @example
     * ```php
     * $exporter = new OpenTelemetryExporter([
     *     'endpoint' => 'http://localhost:4318/v1/metrics',
     *     'headers' => ['Authorization' => 'Bearer token'],
     *     'batch_size' => 100,
     *     'dry_run' => false,
     * ]);
     * ```
     */
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        // Apply environment variable overrides
        $config = $this->applyEnvironmentOverrides($config);

        // Validate required configuration
        $this->validateConfiguration($config);

        // Set defaults
        $this->config = array_merge([
            'timeout' => 30,
            'batch_size' => 100,
            'dry_run' => false,
            'headers' => [],
            'resource_attributes' => [],
        ], $config);

        $this->logger = $logger ?? new NullLogger();

        // Initialize HTTP client
        $clientConfig = [
            'timeout' => $this->config['timeout'],
            'headers' => array_merge(
                ['Content-Type' => 'application/json'],
                $this->config['headers']
            ),
        ];

        $this->adapter = new GuzzleAdapter(new Client(), $clientConfig);
    }

    /**
     * Export metrics to OpenTelemetry collector
     *
     * Converts SDK metrics to OpenTelemetry format and sends them to the
     * configured collector endpoint. Metrics are batched according to the
     * batch_size configuration.
     *
     * @param array<string,mixed> $metrics Associative array of metric names to values
     *
     * @return void
     */
    public function export(array $metrics): void
    {
        if (empty($metrics)) {
            return;
        }

        // Add to buffer
        $this->buffer[] = $metrics;

        // Flush if batch size reached
        if (count($this->buffer) >= $this->config['batch_size']) {
            $this->flush();
        }
    }

    /**
     * Flush buffered metrics to the collector
     *
     * Converts all buffered metrics to OpenTelemetry format and sends them
     * to the collector. Clears the buffer after successful export.
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // Convert metrics to OT format
            $otlpPayload = $this->convertToOtlpFormat($this->buffer);

            // In dry-run mode, just log and return
            if ($this->config['dry_run']) {
                $this->logger->info('Dry-run mode: Would export metrics', [
                    'metric_count' => count($this->buffer),
                    'payload_size' => strlen(json_encode($otlpPayload)),
                ]);
                $this->buffer = [];
                return;
            }

            // Send to collector
            $this->adapter->post(
                $this->config['endpoint'],
                $otlpPayload
            );

            $this->logger->debug('Successfully exported metrics to OpenTelemetry collector', [
                'metric_count' => count($this->buffer),
                'endpoint' => $this->config['endpoint'],
            ]);

            // Clear buffer on success
            $this->buffer = [];
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to export metrics to OpenTelemetry collector', [
                'error' => $e->getMessage(),
                'endpoint' => $this->config['endpoint'],
            ]);

            // Clear buffer to prevent memory buildup
            $this->buffer = [];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during metric export', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            // Clear buffer to prevent memory buildup
            $this->buffer = [];
        }
    }

    /**
     * Apply environment variable overrides to configuration
     *
     * @param array<string,mixed> $config Configuration array
     *
     * @return array<string,mixed> Configuration with environment overrides applied
     */
    private function applyEnvironmentOverrides(array $config): array
    {
        // Override endpoint from environment
        $envEndpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if ($envEndpoint !== false && $envEndpoint !== '') {
            $config['endpoint'] = $envEndpoint;
        }

        // Override headers from environment
        $envHeaders = getenv('OTEL_EXPORTER_OTLP_HEADERS');
        if ($envHeaders !== false && $envHeaders !== '') {
            $headers = [];
            $pairs = explode(',', $envHeaders);
            foreach ($pairs as $pair) {
                $parts = explode('=', $pair, 2);
                if (count($parts) === 2) {
                    $headers[trim($parts[0])] = trim($parts[1]);
                }
            }
            $config['headers'] = array_merge($config['headers'] ?? [], $headers);
        }

        return $config;
    }

    /**
     * Validate configuration
     *
     * @param array<string,mixed> $config Configuration to validate
     *
     * @throws ConfigurationException If configuration is invalid
     *
     * @return void
     */
    private function validateConfiguration(array $config): void
    {
        // Endpoint is required
        if (!isset($config['endpoint']) || !is_string($config['endpoint']) || $config['endpoint'] === '') {
            throw new ConfigurationException(
                'OpenTelemetry exporter requires "endpoint" configuration. ' .
                'Example: http://localhost:4318/v1/metrics'
            );
        }

        // Validate endpoint is a valid URL
        if (filter_var($config['endpoint'], FILTER_VALIDATE_URL) === false) {
            throw new ConfigurationException(
                'OpenTelemetry exporter "endpoint" must be a valid URL. ' .
                'Got: ' . $config['endpoint']
            );
        }
    }

    /**
     * Convert SDK metrics to OpenTelemetry OTLP format
     *
     * Transforms metrics from the SDK format to the OpenTelemetry Protocol
     * (OTLP) JSON format for transmission to collectors.
     *
     * @param array<int,array<string,mixed>> $metricsBuffer Buffer of metrics to convert
     *
     * @return array<string,mixed> OTLP-formatted payload
     */
    private function convertToOtlpFormat(array $metricsBuffer): array
    {
        $timestamp = (int) (microtime(true) * 1_000_000_000); // Nanoseconds
        $metrics = [];

        foreach ($metricsBuffer as $metricBatch) {
            // Extract context if present
            $context = $metricBatch['context'] ?? [];
            unset($metricBatch['context']);

            foreach ($metricBatch as $name => $value) {
                if ($name === 'timestamp' || $name === 'labels' || $name === 'metadata') {
                    continue; // Skip structural elements
                }

                // Determine metric type and convert
                if (is_array($value) && isset($value['count'], $value['sum'])) {
                    // Timer/Histogram metric
                    $metrics[] = $this->createHistogramMetric($name, $value, $context, $timestamp);
                } elseif (is_int($value)) {
                    // Counter metric
                    $metrics[] = $this->createCounterMetric($name, $value, $context, $timestamp);
                } elseif (is_float($value) || is_numeric($value)) {
                    // Gauge metric
                    $metrics[] = $this->createGaugeMetric($name, (float) $value, $context, $timestamp);
                }
            }
        }

        // Build OTLP payload
        return [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => $this->buildResourceAttributes(),
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => [
                                'name' => 'php-blockchain-sdk',
                                'version' => '1.0.0',
                            ],
                            'metrics' => $metrics,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a histogram metric in OT format
     *
     * @param string $name Metric name
     * @param array<string,mixed> $value Metric value with aggregates
     * @param array<string,mixed> $context Context attributes
     * @param int $timestamp Timestamp in nanoseconds
     *
     * @return array<string,mixed> OT histogram metric
     */
    private function createHistogramMetric(string $name, array $value, array $context, int $timestamp): array
    {
        return [
            'name' => $name,
            'unit' => $this->determineUnit($name),
            'histogram' => [
                'dataPoints' => [
                    [
                        'startTimeUnixNano' => $timestamp,
                        'timeUnixNano' => $timestamp,
                        'count' => (int) $value['count'],
                        'sum' => (float) $value['sum'],
                        'min' => (float) ($value['min'] ?? 0),
                        'max' => (float) ($value['max'] ?? 0),
                        'attributes' => $this->buildAttributes($context),
                    ],
                ],
                'aggregationTemporality' => 2, // CUMULATIVE
            ],
        ];
    }

    /**
     * Create a counter metric in OT format
     *
     * @param string $name Metric name
     * @param int $value Metric value
     * @param array<string,mixed> $context Context attributes
     * @param int $timestamp Timestamp in nanoseconds
     *
     * @return array<string,mixed> OT counter metric
     */
    private function createCounterMetric(string $name, int $value, array $context, int $timestamp): array
    {
        return [
            'name' => $name,
            'unit' => $this->determineUnit($name),
            'sum' => [
                'dataPoints' => [
                    [
                        'startTimeUnixNano' => $timestamp,
                        'timeUnixNano' => $timestamp,
                        'asInt' => $value,
                        'attributes' => $this->buildAttributes($context),
                    ],
                ],
                'aggregationTemporality' => 2, // CUMULATIVE
                'isMonotonic' => true,
            ],
        ];
    }

    /**
     * Create a gauge metric in OT format
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array<string,mixed> $context Context attributes
     * @param int $timestamp Timestamp in nanoseconds
     *
     * @return array<string,mixed> OT gauge metric
     */
    private function createGaugeMetric(string $name, float $value, array $context, int $timestamp): array
    {
        return [
            'name' => $name,
            'unit' => $this->determineUnit($name),
            'gauge' => [
                'dataPoints' => [
                    [
                        'timeUnixNano' => $timestamp,
                        'asDouble' => $value,
                        'attributes' => $this->buildAttributes($context),
                    ],
                ],
            ],
        ];
    }

    /**
     * Build resource attributes from configuration and defaults
     *
     * @return array<int,array<string,mixed>> Resource attributes in OT format
     */
    private function buildResourceAttributes(): array
    {
        $attributes = array_merge(
            [
                'service.name' => 'php-blockchain',
                'telemetry.sdk.name' => 'php-blockchain-telemetry',
                'telemetry.sdk.language' => 'php',
                'telemetry.sdk.version' => '1.0.0',
            ],
            $this->config['resource_attributes']
        );

        return $this->buildAttributes($attributes);
    }

    /**
     * Build OT attributes array from key-value pairs
     *
     * @param array<string,mixed> $attributes Attributes as key-value pairs
     *
     * @return array<int,array<string,mixed>> Attributes in OT format
     */
    private function buildAttributes(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue; // Skip non-string keys
            }

            $attribute = ['key' => $key];

            if (is_string($value)) {
                $attribute['value'] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $attribute['value'] = ['intValue' => $value];
            } elseif (is_float($value)) {
                $attribute['value'] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $attribute['value'] = ['boolValue' => $value];
            } else {
                // Convert other types to string
                $attribute['value'] = ['stringValue' => (string) $value];
            }

            $result[] = $attribute;
        }

        return $result;
    }

    /**
     * Determine unit for metric based on name
     *
     * @param string $name Metric name
     *
     * @return string Unit designation
     */
    private function determineUnit(string $name): string
    {
        if (str_ends_with($name, '_ms') || str_contains($name, 'latency') || str_contains($name, 'duration')) {
            return 'ms';
        }
        if (str_ends_with($name, '_bytes') || str_contains($name, 'size')) {
            return 'By';
        }
        if (str_ends_with($name, '_count') || str_contains($name, 'count')) {
            return '1';
        }
        if (str_ends_with($name, '_percent') || str_contains($name, 'ratio')) {
            return '%';
        }
        return '1'; // Dimensionless
    }

    /**
     * Flush buffered metrics on destruct
     */
    public function __destruct()
    {
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }
}
