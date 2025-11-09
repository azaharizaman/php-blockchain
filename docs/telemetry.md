# Telemetry and Metrics Exporter

This document describes the telemetry exporter system in the PHP Blockchain Integration Layer, which enables pluggable metrics collection and monitoring.

## Overview

The telemetry exporter system provides a flexible, opt-in mechanism for exporting metrics to external monitoring systems. By default, telemetry is **disabled** using a no-operation exporter, ensuring zero overhead unless explicitly enabled.

## Key Concepts

### ExporterInterface

The `ExporterInterface` defines the contract for all telemetry exporters:

```php
namespace Blockchain\Telemetry;

interface ExporterInterface
{
    /**
     * Export metrics to the monitoring backend
     *
     * @param array<string,mixed> $metrics Associative array of metric names to values
     * @return void
     */
    public function export(array $metrics): void;
}
```

### NoopExporter (Default)

The `NoopExporter` is the default implementation that discards all metrics. This ensures:
- **Zero overhead** when monitoring is not needed
- **Opt-in telemetry** - no metrics are sent by default (satisfies CON-001)
- **Safe defaults** - no configuration required to use the SDK

```php
use Blockchain\Telemetry\NoopExporter;

$exporter = new NoopExporter();
$exporter->export($metrics); // Safely discards all metrics
```

## Metrics Structure

Metrics passed to the `export()` method should follow this structure:

```php
$metrics = [
    // Scalar metrics
    'request_latency_ms' => 245,        // Request duration in milliseconds
    'error_count' => 1,                 // Number of errors
    'success_count' => 99,              // Number of successful operations
    'queue_depth' => 15,                // Current queue size
    
    // Timestamp (Unix timestamp)
    'timestamp' => 1699522152,
    
    // Labels for metric categorization
    'labels' => [
        'driver' => 'ethereum',
        'operation' => 'sendTransaction',
        'environment' => 'production',
    ],
    
    // Optional metadata
    'metadata' => [
        'version' => '1.0.0',
        'region' => 'us-east-1',
    ],
];
```

## Common Metric Types

### Counters
Metrics that only increase over time:
- `request_count` - Total number of requests
- `error_count` - Total number of errors
- `transaction_count` - Total transactions processed

### Gauges
Metrics that can go up or down:
- `queue_depth` - Current queue size
- `active_connections` - Number of active connections
- `memory_usage_bytes` - Current memory usage

### Histograms/Timers
Metrics tracking distributions:
- `request_latency_ms` - Request duration
- `transaction_time_ms` - Transaction processing time
- `network_latency_ms` - Network round-trip time

## Enabling Telemetry

To enable telemetry, implement a custom exporter for your monitoring backend.

### Example: Prometheus Exporter

```php
<?php

use Blockchain\Telemetry\ExporterInterface;

class PrometheusExporter implements ExporterInterface
{
    private PrometheusClient $client;
    private string $namespace;

    public function __construct(PrometheusClient $client, string $namespace = 'blockchain')
    {
        $this->client = $client;
        $this->namespace = $namespace;
    }

    public function export(array $metrics): void
    {
        foreach ($metrics as $name => $value) {
            if ($name === 'labels' || $name === 'metadata') {
                continue; // Skip structural elements
            }

            if (is_numeric($value)) {
                $labels = $metrics['labels'] ?? [];
                $this->client->gauge(
                    $this->namespace . '_' . $name,
                    $value,
                    $labels
                );
            }
        }
    }
}
```

### Example: StatsD Exporter

```php
<?php

use Blockchain\Telemetry\ExporterInterface;

class StatsDExporter implements ExporterInterface
{
    private StatsDClient $client;
    private string $prefix;

    public function __construct(string $host, int $port, string $prefix = 'blockchain')
    {
        $this->client = new StatsDClient($host, $port);
        $this->prefix = $prefix;
    }

    public function export(array $metrics): void
    {
        foreach ($metrics as $name => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $metricName = $this->prefix . '.' . $name;
            
            // Determine metric type based on name pattern
            if (str_ends_with($name, '_count')) {
                $this->client->increment($metricName);
            } elseif (str_ends_with($name, '_latency_ms')) {
                $this->client->timing($metricName, (int)$value);
            } else {
                $this->client->gauge($metricName, $value);
            }
        }
    }
}
```

### Example: CloudWatch Exporter

```php
<?php

use Blockchain\Telemetry\ExporterInterface;
use Aws\CloudWatch\CloudWatchClient;

class CloudWatchExporter implements ExporterInterface
{
    private CloudWatchClient $client;
    private string $namespace;

    public function __construct(CloudWatchClient $client, string $namespace = 'Blockchain')
    {
        $this->client = $client;
        $this->namespace = $namespace;
    }

    public function export(array $metrics): void
    {
        $metricData = [];

        foreach ($metrics as $name => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $dimensions = [];
            if (isset($metrics['labels'])) {
                foreach ($metrics['labels'] as $labelKey => $labelValue) {
                    $dimensions[] = [
                        'Name' => $labelKey,
                        'Value' => (string)$labelValue,
                    ];
                }
            }

            $metricData[] = [
                'MetricName' => $name,
                'Value' => $value,
                'Unit' => $this->determineUnit($name),
                'Timestamp' => $metrics['timestamp'] ?? time(),
                'Dimensions' => $dimensions,
            ];
        }

        if (!empty($metricData)) {
            $this->client->putMetricData([
                'Namespace' => $this->namespace,
                'MetricData' => $metricData,
            ]);
        }
    }

    private function determineUnit(string $name): string
    {
        if (str_ends_with($name, '_ms')) {
            return 'Milliseconds';
        }
        if (str_ends_with($name, '_bytes')) {
            return 'Bytes';
        }
        if (str_ends_with($name, '_count')) {
            return 'Count';
        }
        return 'None';
    }
}
```

## Using Custom Exporters

### Dependency Injection

```php
<?php

use Blockchain\BlockchainManager;
use Blockchain\Telemetry\ExporterInterface;

class BlockchainService
{
    private BlockchainManager $blockchain;
    private ExporterInterface $exporter;

    public function __construct(
        BlockchainManager $blockchain,
        ExporterInterface $exporter
    ) {
        $this->blockchain = $blockchain;
        $this->exporter = $exporter;
    }

    public function sendTransaction(string $from, string $to, float $amount): string
    {
        $startTime = microtime(true);
        
        try {
            $txHash = $this->blockchain->sendTransaction($from, $to, $amount);
            
            // Export success metrics
            $this->exporter->export([
                'transaction_count' => 1,
                'success_count' => 1,
                'request_latency_ms' => (microtime(true) - $startTime) * 1000,
                'timestamp' => time(),
                'labels' => [
                    'operation' => 'sendTransaction',
                    'status' => 'success',
                ],
            ]);
            
            return $txHash;
        } catch (\Exception $e) {
            // Export error metrics
            $this->exporter->export([
                'transaction_count' => 1,
                'error_count' => 1,
                'request_latency_ms' => (microtime(true) - $startTime) * 1000,
                'timestamp' => time(),
                'labels' => [
                    'operation' => 'sendTransaction',
                    'status' => 'error',
                    'error_type' => get_class($e),
                ],
            ]);
            
            throw $e;
        }
    }
}
```

### Configuration-Based Setup

```php
<?php

use Blockchain\Telemetry\NoopExporter;
use Blockchain\Telemetry\ExporterInterface;

class TelemetryFactory
{
    public static function createExporter(array $config): ExporterInterface
    {
        // Default to noop exporter (telemetry disabled)
        if (!isset($config['telemetry']['enabled']) || !$config['telemetry']['enabled']) {
            return new NoopExporter();
        }

        $type = $config['telemetry']['type'] ?? 'noop';

        return match ($type) {
            'prometheus' => new PrometheusExporter(
                new PrometheusClient($config['telemetry']['prometheus']),
                $config['telemetry']['namespace'] ?? 'blockchain'
            ),
            'statsd' => new StatsDExporter(
                $config['telemetry']['statsd']['host'],
                $config['telemetry']['statsd']['port'],
                $config['telemetry']['namespace'] ?? 'blockchain'
            ),
            'cloudwatch' => new CloudWatchExporter(
                new CloudWatchClient($config['telemetry']['aws']),
                $config['telemetry']['namespace'] ?? 'Blockchain'
            ),
            default => new NoopExporter(),
        };
    }
}
```

## Best Practices

### 1. Use Appropriate Metric Types

- **Counters** for cumulative values that only increase
- **Gauges** for values that can go up or down
- **Histograms** for distributions and latencies

### 2. Include Relevant Labels

Labels help categorize and filter metrics:

```php
$this->exporter->export([
    'request_latency_ms' => 250,
    'labels' => [
        'driver' => 'ethereum',
        'operation' => 'getBalance',
        'network' => 'mainnet',
        'status' => 'success',
    ],
]);
```

### 3. Avoid High-Cardinality Labels

Don't use unique values as labels (e.g., transaction hashes, user IDs):

```php
// ❌ BAD: High cardinality
'labels' => ['tx_hash' => '0xabc123...']

// ✅ GOOD: Low cardinality
'labels' => ['tx_status' => 'pending']
```

### 4. Batch Metrics When Possible

Instead of exporting metrics one at a time, batch them:

```php
// Collect metrics
$metrics = [
    'request_count' => 1,
    'success_count' => 1,
    'request_latency_ms' => 245,
    'queue_depth' => 15,
    'timestamp' => time(),
    'labels' => ['driver' => 'ethereum'],
];

// Export once
$this->exporter->export($metrics);
```

### 5. Handle Errors Gracefully

Exporter errors should not break your application:

```php
try {
    $this->exporter->export($metrics);
} catch (\Exception $e) {
    // Log error but don't fail the operation
    error_log('Failed to export metrics: ' . $e->getMessage());
}
```

## Performance Considerations

### No-Op Performance

The `NoopExporter` has **zero overhead**:
- No I/O operations
- No processing or serialization
- Metrics are immediately discarded

### Custom Exporter Performance

When implementing custom exporters:
1. **Use async/buffered exports** to avoid blocking the main thread
2. **Batch metrics** to reduce network overhead
3. **Implement retry logic** with exponential backoff
4. **Set reasonable timeouts** to prevent hanging
5. **Use connection pooling** for network clients

### Buffered Exporter Example

```php
<?php

use Blockchain\Telemetry\ExporterInterface;

class BufferedExporter implements ExporterInterface
{
    private ExporterInterface $backend;
    private array $buffer = [];
    private int $maxBatchSize;

    public function __construct(ExporterInterface $backend, int $maxBatchSize = 100)
    {
        $this->backend = $backend;
        $this->maxBatchSize = $maxBatchSize;
    }

    public function export(array $metrics): void
    {
        $this->buffer[] = $metrics;

        if (count($this->buffer) >= $this->maxBatchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // Combine all buffered metrics
            $combined = $this->combineMetrics($this->buffer);
            $this->backend->export($combined);
            $this->buffer = [];
        } catch (\Exception $e) {
            error_log('Failed to flush metrics: ' . $e->getMessage());
        }
    }

    private function combineMetrics(array $metricsArray): array
    {
        // Implementation depends on your metrics aggregation strategy
        // This is a simple example
        $combined = [];
        foreach ($metricsArray as $metrics) {
            foreach ($metrics as $key => $value) {
                if (is_numeric($value)) {
                    $combined[$key] = ($combined[$key] ?? 0) + $value;
                }
            }
        }
        return $combined;
    }

    public function __destruct()
    {
        $this->flush();
    }
}
```

## Testing

### Testing with NoopExporter

```php
public function testOperationExportsMetrics(): void
{
    $exporter = new NoopExporter();
    $service = new BlockchainService($blockchain, $exporter);
    
    // Verify operation completes without error
    $result = $service->sendTransaction($from, $to, $amount);
    $this->assertNotEmpty($result);
}
```

### Testing with Mock Exporter

```php
use PHPUnit\Framework\TestCase;
use Blockchain\Telemetry\ExporterInterface;

class MockExporter implements ExporterInterface
{
    public array $exportedMetrics = [];

    public function export(array $metrics): void
    {
        $this->exportedMetrics[] = $metrics;
    }
}

class BlockchainServiceTest extends TestCase
{
    public function testExportsSuccessMetrics(): void
    {
        $mockExporter = new MockExporter();
        $service = new BlockchainService($blockchain, $mockExporter);
        
        $service->sendTransaction($from, $to, $amount);
        
        $this->assertCount(1, $mockExporter->exportedMetrics);
        $this->assertEquals(1, $mockExporter->exportedMetrics[0]['success_count']);
    }
}
```

## Security Considerations

### Do Not Export Sensitive Data

Never include sensitive information in metrics:

```php
// ❌ BAD: Exposes sensitive data
$this->exporter->export([
    'labels' => [
        'private_key' => $privateKey,  // Never do this!
        'password' => $password,        // Never do this!
    ],
]);

// ✅ GOOD: Only metadata
$this->exporter->export([
    'labels' => [
        'driver' => 'ethereum',
        'operation' => 'sendTransaction',
    ],
]);
```

### Sanitize Error Messages

Don't expose stack traces or internal details:

```php
// ❌ BAD: Exposes internals
'error_message' => $exception->getMessage() . "\n" . $exception->getTraceAsString()

// ✅ GOOD: Generic error type
'error_type' => get_class($exception)
```

## Troubleshooting

### Metrics Not Appearing

1. **Check if telemetry is enabled**: Verify you're not using `NoopExporter`
2. **Check exporter configuration**: Ensure backend connection details are correct
3. **Check for errors**: Look for exceptions in logs
4. **Verify metric format**: Ensure metrics follow expected structure

### High Memory Usage

1. **Implement buffering**: Don't export on every operation
2. **Use fixed-size buffers**: Prevent unbounded memory growth
3. **Flush periodically**: Set up time-based flushing

### Performance Impact

1. **Use async exporters**: Don't block on network I/O
2. **Increase batch size**: Reduce network overhead
3. **Consider sampling**: Export only a percentage of metrics for high-volume operations

## Related Documentation

- [OperationTracer](../src/Telemetry/OperationTracerInterface.php) - Operation-level telemetry hooks
- [Logging and Audit](LOGGING-AND-AUDIT.md) - Logging and audit trail documentation
- [Testing Guide](../TESTING.md) - General testing guidelines

## Requirements Satisfied

- ✅ **REQ-001**: Export metrics via ExporterInterface (request latencies, error rates, counts)
- ✅ **CON-001**: Telemetry must be opt-in and not enabled by default (NoopExporter is default)
