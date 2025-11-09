<?php

/**
 * OpenTelemetry Exporter Usage Example
 *
 * This script demonstrates how to use the OpenTelemetryExporter
 * to send blockchain metrics to an OpenTelemetry collector.
 *
 * Requirements:
 * - OpenTelemetry Collector running at http://localhost:4318
 * - PHP 8.2+
 * - Composer dependencies installed
 *
 * Usage:
 *   php examples/opentelemetry-exporter-example.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\Telemetry\OpenTelemetryExporter;
use Blockchain\Telemetry\MetricCollector;
use Blockchain\Telemetry\NoopExporter;

echo "====================================\n";
echo "OpenTelemetry Exporter Example\n";
echo "====================================\n\n";

// Example 1: Basic Setup with Dry-Run Mode
echo "1. Basic Setup (Dry-Run Mode)\n";
echo "------------------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'dry_run' => true, // No actual network calls
]);

$exporter->export([
    'request_count' => 42,
    'active_connections' => 15.5,
]);

echo "✓ Metrics exported in dry-run mode (no network calls)\n\n";

// Example 2: With Authentication
echo "2. With Authentication Headers\n";
echo "-------------------------------\n";

$authenticatedExporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'headers' => [
        'Authorization' => 'Bearer demo-token-123',
        'X-API-Key' => 'demo-api-key',
    ],
    'dry_run' => true,
]);

$authenticatedExporter->export([
    'api_request_count' => 100,
]);

echo "✓ Exporter configured with authentication headers\n\n";

// Example 3: With Resource Attributes
echo "3. With Resource Attributes\n";
echo "---------------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'resource_attributes' => [
        'service.name' => 'blockchain-demo',
        'service.version' => '1.0.0',
        'deployment.environment' => 'development',
        'host.name' => gethostname(),
    ],
    'dry_run' => true,
]);

$exporter->export([
    'service_uptime_seconds' => 3600,
]);

echo "✓ Resource attributes included with metrics\n\n";

// Example 4: Mixed Metric Types
echo "4. Mixed Metric Types\n";
echo "---------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'dry_run' => true,
]);

$exporter->export([
    // Counter (integer)
    'blockchain.transactions.total' => 1000,
    
    // Gauge (float)
    'blockchain.gas_price.current' => 25.5,
    
    // Histogram (aggregated timer)
    'blockchain.transaction.duration_ms' => [
        'count' => 100,
        'sum' => 24500.0,
        'min' => 150.0,
        'max' => 500.0,
        'avg' => 245.0,
    ],
    
    // Context
    'context' => [
        'chain' => 'ethereum',
        'network' => 'mainnet',
    ],
]);

echo "✓ Counter: blockchain.transactions.total = 1000\n";
echo "✓ Gauge: blockchain.gas_price.current = 25.5\n";
echo "✓ Histogram: blockchain.transaction.duration_ms (100 samples)\n";
echo "✓ Context: chain=ethereum, network=mainnet\n\n";

// Example 5: Using MetricCollector
echo "5. Using MetricCollector\n";
echo "------------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'batch_size' => 10,
    'dry_run' => true,
]);

$collector = new MetricCollector($exporter);

// Add context
$collector = $collector->withContext([
    'driver' => 'ethereum',
    'operation' => 'getBalance',
]);

// Start timer
$timerId = $collector->startTimer('operation.duration');

// Simulate operation
usleep(50000); // 50ms

// Stop timer
$elapsed = $collector->stopTimer($timerId);

// Add counter
$collector->increment('operation.count');

// Add gauge
$collector->gauge('connection.pool.size', 10);

// Flush metrics
$collector->flush();

echo "✓ Timer: operation.duration = {$elapsed}ms\n";
echo "✓ Counter: operation.count = 1\n";
echo "✓ Gauge: connection.pool.size = 10\n";
echo "✓ Context: driver=ethereum, operation=getBalance\n\n";

// Example 6: Batch Export
echo "6. Batch Export (with auto-flush)\n";
echo "----------------------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'batch_size' => 3, // Flush after 3 metrics
    'dry_run' => true,
]);

echo "Exporting 5 metrics (should trigger 2 flushes)...\n";

for ($i = 1; $i <= 5; $i++) {
    $exporter->export([
        'metric_' . $i => $i * 10,
    ]);
    echo "  Exported metric_{$i}\n";
}

echo "✓ Batch export completed\n\n";

// Example 7: Environment Variable Configuration
echo "7. Environment Variable Configuration\n";
echo "-------------------------------------\n";

// Set environment variables (for demo purposes)
putenv('OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318/v1/metrics');
putenv('OTEL_EXPORTER_OTLP_HEADERS=Authorization=Bearer env-token,X-Region=us-east-1');

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics', // Will be overridden by env var
    'dry_run' => true,
]);

// Clean up
putenv('OTEL_EXPORTER_OTLP_ENDPOINT');
putenv('OTEL_EXPORTER_OTLP_HEADERS');

echo "✓ Configuration overridden by environment variables\n";
echo "  OTEL_EXPORTER_OTLP_ENDPOINT\n";
echo "  OTEL_EXPORTER_OTLP_HEADERS\n\n";

// Example 8: Error Handling
echo "8. Error Handling\n";
echo "-----------------\n";

try {
    // This will fail - missing endpoint
    $invalidExporter = new OpenTelemetryExporter([]);
} catch (\Blockchain\Exceptions\ConfigurationException $e) {
    echo "✓ Configuration validation works: {$e->getMessage()}\n";
}

try {
    // This will fail - invalid URL
    $invalidExporter = new OpenTelemetryExporter([
        'endpoint' => 'not-a-valid-url',
    ]);
} catch (\Blockchain\Exceptions\ConfigurationException $e) {
    echo "✓ URL validation works: {$e->getMessage()}\n";
}

echo "\n";

// Example 9: Real-World Blockchain Metrics
echo "9. Real-World Blockchain Metrics\n";
echo "--------------------------------\n";

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://localhost:4318/v1/metrics',
    'resource_attributes' => [
        'service.name' => 'ethereum-node-monitor',
        'blockchain.network' => 'mainnet',
    ],
    'dry_run' => true,
]);

// Simulate collecting blockchain metrics
$blockchainMetrics = [
    // Transaction metrics
    'blockchain.transactions.pending' => 1250,
    'blockchain.transactions.confirmed' => 150000,
    'blockchain.transactions.failed' => 25,
    
    // Block metrics
    'blockchain.block.height' => 18500000,
    'blockchain.block.time_ms' => 12000,
    'blockchain.block.gas_used' => 15000000,
    'blockchain.block.gas_limit' => 30000000,
    
    // Network metrics
    'blockchain.peers.connected' => 50,
    'blockchain.peers.syncing' => 5,
    
    // Performance metrics
    'blockchain.rpc.latency_ms' => [
        'count' => 1000,
        'sum' => 125000.0,
        'min' => 50.0,
        'max' => 500.0,
        'avg' => 125.0,
    ],
    
    'context' => [
        'chain' => 'ethereum',
        'client' => 'geth',
        'version' => '1.13.0',
    ],
];

$exporter->export($blockchainMetrics);

echo "✓ Exported comprehensive blockchain metrics:\n";
echo "  - Transaction counters (pending, confirmed, failed)\n";
echo "  - Block information (height, time, gas)\n";
echo "  - Network status (peers, sync)\n";
echo "  - RPC performance (latency histogram)\n\n";

// Example 10: Production Setup (commented for safety)
echo "10. Production Setup Example\n";
echo "----------------------------\n";

echo "For production, configure like this:\n\n";
echo "<?php\n";
echo "\$exporter = new OpenTelemetryExporter([\n";
echo "    'endpoint' => getenv('OTEL_EXPORTER_OTLP_ENDPOINT'),\n";
echo "    'headers' => [\n";
echo "        'Authorization' => 'Bearer ' . getenv('OTEL_API_TOKEN'),\n";
echo "    ],\n";
echo "    'timeout' => 30,\n";
echo "    'batch_size' => 100,\n";
echo "    'resource_attributes' => [\n";
echo "        'service.name' => getenv('SERVICE_NAME'),\n";
echo "        'service.version' => getenv('APP_VERSION'),\n";
echo "        'deployment.environment' => getenv('ENVIRONMENT'),\n";
echo "    ],\n";
echo "]);\n\n";

echo "✓ Use environment variables for configuration\n";
echo "✓ Set appropriate timeout and batch size\n";
echo "✓ Include service identification in resource attributes\n\n";

// Summary
echo "====================================\n";
echo "Summary\n";
echo "====================================\n\n";

echo "✅ All examples completed successfully!\n\n";

echo "Key Features Demonstrated:\n";
echo "  1. Dry-run mode for testing\n";
echo "  2. Authentication with custom headers\n";
echo "  3. Resource attributes for service identification\n";
echo "  4. Mixed metric types (counter, gauge, histogram)\n";
echo "  5. Integration with MetricCollector\n";
echo "  6. Batch export with auto-flush\n";
echo "  7. Environment variable configuration\n";
echo "  8. Configuration validation and error handling\n";
echo "  9. Real-world blockchain metrics\n";
echo " 10. Production configuration best practices\n\n";

echo "Next Steps:\n";
echo "  1. Start an OpenTelemetry Collector: docker run -p 4318:4318 otel/opentelemetry-collector\n";
echo "  2. Remove 'dry_run' => true from configuration\n";
echo "  3. Export real metrics to your collector\n";
echo "  4. View metrics in your observability platform\n\n";

echo "Documentation: docs/telemetry.md\n";
