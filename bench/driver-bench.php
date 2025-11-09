#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Driver Benchmark Script
 *
 * Benchmarks blockchain driver performance by measuring RPC throughput and latency
 * using controlled test scenarios. Supports both mock mode (no network calls) and
 * live mode (against real or local nodes).
 *
 * Usage:
 *   php bench/driver-bench.php --driver=ethereum --endpoint=http://localhost:8545 --iterations=100
 *   php bench/driver-bench.php --driver=ethereum --mock --iterations=1000
 *   php bench/driver-bench.php --driver=solana --workload=getBalance --iterations=500
 *
 * @package Blockchain\Bench
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\BlockchainManager;
use Blockchain\Registry\DriverRegistry;
use Blockchain\Telemetry\MetricCollector;
use Blockchain\Telemetry\NoopExporter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Blockchain\Transport\GuzzleAdapter;

/**
 * Parse command-line arguments
 *
 * @return array<string,mixed> Parsed arguments
 */
function parseArgs(): array
{
    $options = getopt('', [
        'driver:',
        'endpoint::',
        'iterations::',
        'mock',
        'workload::',
        'output-format::',
        'output-file::',
        'help',
    ]);

    if (isset($options['help'])) {
        showHelp();
        exit(0);
    }

    if (!isset($options['driver'])) {
        fwrite(STDERR, "Error: --driver is required\n\n");
        showHelp();
        exit(1);
    }

    $mockMode = isset($options['mock']);
    
    if (!$mockMode && !isset($options['endpoint'])) {
        fwrite(STDERR, "Error: --endpoint is required unless --mock is specified\n\n");
        showHelp();
        exit(1);
    }

    return [
        'driver' => $options['driver'],
        'endpoint' => $options['endpoint'] ?? '',
        'iterations' => (int)($options['iterations'] ?? 100),
        'mock' => $mockMode,
        'workload' => $options['workload'] ?? 'all',
        'output_format' => $options['output-format'] ?? 'json',
        'output_file' => $options['output-file'] ?? null,
    ];
}

/**
 * Show help message
 *
 * @return void
 */
function showHelp(): void
{
    echo <<<HELP
Driver Benchmark Script

Usage:
  php bench/driver-bench.php [options]

Options:
  --driver=NAME              Driver name (ethereum, solana) [required]
  --endpoint=URL             RPC endpoint URL [required unless --mock]
  --iterations=N             Number of operations (default: 100)
  --mock                     Use MockHandler (no network calls)
  --workload=TYPE            Workload type: getBalance, getTransaction, getBlock, all (default: all)
  --output-format=FORMAT     Output format: json, csv (default: json)
  --output-file=PATH         Output file path (stdout if not specified)
  --help                     Show this help message

Examples:
  php bench/driver-bench.php --driver=ethereum --endpoint=http://localhost:8545 --iterations=100
  php bench/driver-bench.php --driver=ethereum --mock --iterations=1000
  php bench/driver-bench.php --driver=solana --workload=getBalance --iterations=500 --output-format=csv

HELP;
}

/**
 * Create mock HTTP client for testing without network calls
 *
 * @param string $driverName Driver name
 * @param int $iterations Number of iterations (responses to generate)
 * @return GuzzleAdapter Mock adapter
 */
function createMockAdapter(string $driverName, int $iterations): GuzzleAdapter
{
    $responses = [];
    
    // Generate mock responses based on driver type
    for ($i = 0; $i < $iterations * 5; $i++) { // 5x to handle multiple workload types
        if ($driverName === 'ethereum') {
            $responses[] = new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x' . dechex(random_int(1000000000000000, 9999999999999999)),
            ]));
        } elseif ($driverName === 'solana') {
            $responses[] = new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'value' => random_int(1000000000, 9999999999),
                ],
            ]));
        }
    }
    
    $mockHandler = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mockHandler);
    $client = new Client(['handler' => $handlerStack]);
    
    return new GuzzleAdapter($client);
}

/**
 * Create driver instance
 *
 * @param string $driverName Driver name
 * @param array<string,mixed> $config Driver configuration
 * @param bool $mockMode Whether to use mock mode
 * @param int $iterations Number of iterations (for mock response generation)
 * @return \Blockchain\Contracts\BlockchainDriverInterface Driver instance
 */
function createDriver(string $driverName, array $config, bool $mockMode, int $iterations): \Blockchain\Contracts\BlockchainDriverInterface
{
    $registry = new DriverRegistry();
    
    if (!$registry->hasDriver($driverName)) {
        throw new \InvalidArgumentException("Unsupported driver: {$driverName}");
    }
    
    $driverClass = $registry->getDriver($driverName);
    
    if ($mockMode) {
        $mockAdapter = createMockAdapter($driverName, $iterations);
        $driver = new $driverClass($mockAdapter);
    } else {
        $driver = new $driverClass();
    }
    
    $driver->connect($config);
    
    return $driver;
}

/**
 * Run getBalance workload
 *
 * @param \Blockchain\Contracts\BlockchainDriverInterface $driver Driver instance
 * @param int $iterations Number of iterations
 * @param MetricCollector $collector Metric collector
 * @return array<string,mixed> Workload results
 */
function runGetBalanceWorkload($driver, int $iterations, MetricCollector $collector): array
{
    $errors = 0;
    $latencies = [];
    
    // Generate test addresses based on driver type
    $addresses = [];
    $driverClass = get_class($driver);
    
    if (str_contains($driverClass, 'Ethereum')) {
        // Ethereum addresses
        for ($i = 0; $i < $iterations; $i++) {
            $addresses[] = '0x' . str_pad(dechex($i), 40, '0', STR_PAD_LEFT);
        }
    } elseif (str_contains($driverClass, 'Solana')) {
        // Solana addresses (base58, simplified)
        for ($i = 0; $i < $iterations; $i++) {
            $addresses[] = base64_encode(random_bytes(32));
        }
    }
    
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $timerId = $collector->startTimer('getBalance.latency');
        
        try {
            $driver->getBalance($addresses[$i % count($addresses)]);
        } catch (\Exception $e) {
            $errors++;
        }
        
        $latencies[] = $collector->stopTimer($timerId);
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    return [
        'workload' => 'getBalance',
        'duration' => $duration,
        'latencies' => $latencies,
        'errors' => $errors,
    ];
}

/**
 * Run getTransaction workload
 *
 * @param \Blockchain\Contracts\BlockchainDriverInterface $driver Driver instance
 * @param int $iterations Number of iterations
 * @param MetricCollector $collector Metric collector
 * @return array<string,mixed> Workload results
 */
function runGetTransactionWorkload($driver, int $iterations, MetricCollector $collector): array
{
    $errors = 0;
    $latencies = [];
    
    // Generate test transaction hashes
    $txHashes = [];
    for ($i = 0; $i < $iterations; $i++) {
        $txHashes[] = '0x' . bin2hex(random_bytes(32));
    }
    
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $timerId = $collector->startTimer('getTransaction.latency');
        
        try {
            $driver->getTransaction($txHashes[$i % count($txHashes)]);
        } catch (\Exception $e) {
            $errors++;
        }
        
        $latencies[] = $collector->stopTimer($timerId);
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    return [
        'workload' => 'getTransaction',
        'duration' => $duration,
        'latencies' => $latencies,
        'errors' => $errors,
    ];
}

/**
 * Run getBlock workload
 *
 * @param \Blockchain\Contracts\BlockchainDriverInterface $driver Driver instance
 * @param int $iterations Number of iterations
 * @param MetricCollector $collector Metric collector
 * @return array<string,mixed> Workload results
 */
function runGetBlockWorkload($driver, int $iterations, MetricCollector $collector): array
{
    $errors = 0;
    $latencies = [];
    
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $timerId = $collector->startTimer('getBlock.latency');
        
        try {
            // Use sequential block numbers
            $driver->getBlock($i + 1);
        } catch (\Exception $e) {
            $errors++;
        }
        
        $latencies[] = $collector->stopTimer($timerId);
    }
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    return [
        'workload' => 'getBlock',
        'duration' => $duration,
        'latencies' => $latencies,
        'errors' => $errors,
    ];
}

/**
 * Calculate percentiles from latency array
 *
 * @param array<float> $latencies Array of latency values in milliseconds
 * @return array<string,float> Percentile values
 */
function calculatePercentiles(array $latencies): array
{
    if (empty($latencies)) {
        return [
            'min' => 0.0,
            'max' => 0.0,
            'p50' => 0.0,
            'p90' => 0.0,
            'p95' => 0.0,
            'p99' => 0.0,
            'avg' => 0.0,
        ];
    }
    
    sort($latencies);
    $count = count($latencies);
    
    return [
        'min' => $latencies[0],
        'max' => $latencies[$count - 1],
        'p50' => $latencies[(int)($count * 0.50)],
        'p90' => $latencies[(int)($count * 0.90)],
        'p95' => $latencies[(int)($count * 0.95)],
        'p99' => $latencies[(int)($count * 0.99)],
        'avg' => array_sum($latencies) / $count,
    ];
}

/**
 * Format results as JSON
 *
 * @param array<string,mixed> $results Results array
 * @return string JSON string
 */
function formatJson(array $results): string
{
    return json_encode($results, JSON_PRETTY_PRINT) . "\n";
}

/**
 * Format results as CSV
 *
 * @param array<string,mixed> $results Results array
 * @return string CSV string
 */
function formatCsv(array $results): string
{
    $csv = "metric,value,unit\n";
    $csv .= "driver," . $results['driver'] . ",\n";
    $csv .= "workload," . $results['workload'] . ",\n";
    $csv .= "iterations," . $results['iterations'] . ",\n";
    $csv .= "mode," . $results['mode'] . ",\n";
    $csv .= "duration," . number_format($results['metrics']['duration_seconds'], 3) . ",seconds\n";
    $csv .= "throughput," . number_format($results['metrics']['throughput_ops_per_sec'], 2) . ",ops/sec\n";
    
    $latency = $results['metrics']['latency_ms'];
    $csv .= "latency_min," . number_format($latency['min'], 2) . ",ms\n";
    $csv .= "latency_max," . number_format($latency['max'], 2) . ",ms\n";
    $csv .= "latency_p50," . number_format($latency['p50'], 2) . ",ms\n";
    $csv .= "latency_p90," . number_format($latency['p90'], 2) . ",ms\n";
    $csv .= "latency_p95," . number_format($latency['p95'], 2) . ",ms\n";
    $csv .= "latency_p99," . number_format($latency['p99'], 2) . ",ms\n";
    $csv .= "latency_avg," . number_format($latency['avg'], 2) . ",ms\n";
    
    $errors = $results['metrics']['errors'];
    $csv .= "error_count," . $errors['count'] . ",\n";
    $csv .= "error_rate," . number_format($errors['rate_percent'], 2) . ",percent\n";
    
    return $csv;
}

/**
 * Main benchmark execution
 */
function main(): void
{
    $args = parseArgs();
    
    // Create metric collector
    $exporter = new NoopExporter();
    $collector = new MetricCollector($exporter);
    
    // Create driver
    $config = [];
    if (!$args['mock']) {
        $config['endpoint'] = $args['endpoint'];
    } else {
        // Mock mode needs a placeholder endpoint
        $config['endpoint'] = 'http://mock.local';
    }
    
    try {
        $driver = createDriver($args['driver'], $config, $args['mock'], $args['iterations']);
    } catch (\Exception $e) {
        fwrite(STDERR, "Error creating driver: " . $e->getMessage() . "\n");
        exit(1);
    }
    
    // Run workload(s)
    $workloadResults = [];
    
    if ($args['workload'] === 'all' || $args['workload'] === 'getBalance') {
        fwrite(STDERR, "Running getBalance workload...\n");
        $workloadResults[] = runGetBalanceWorkload($driver, $args['iterations'], $collector);
    }
    
    if ($args['workload'] === 'all' || $args['workload'] === 'getTransaction') {
        fwrite(STDERR, "Running getTransaction workload...\n");
        $workloadResults[] = runGetTransactionWorkload($driver, $args['iterations'], $collector);
    }
    
    if ($args['workload'] === 'all' || $args['workload'] === 'getBlock') {
        fwrite(STDERR, "Running getBlock workload...\n");
        $workloadResults[] = runGetBlockWorkload($driver, $args['iterations'], $collector);
    }
    
    // Aggregate results
    $totalLatencies = [];
    $totalErrors = 0;
    $totalDuration = 0.0;
    
    foreach ($workloadResults as $result) {
        $totalLatencies = array_merge($totalLatencies, $result['latencies']);
        $totalErrors += $result['errors'];
        $totalDuration += $result['duration'];
    }
    
    // Calculate metrics
    $percentiles = calculatePercentiles($totalLatencies);
    $totalOps = count($totalLatencies);
    $throughput = $totalDuration > 0 ? $totalOps / $totalDuration : 0.0;
    $errorRate = $totalOps > 0 ? ($totalErrors / $totalOps) * 100 : 0.0;
    
    // Build results structure
    $results = [
        'driver' => $args['driver'],
        'workload' => $args['workload'],
        'iterations' => $args['iterations'],
        'mode' => $args['mock'] ? 'mock' : 'live',
        'metrics' => [
            'duration_seconds' => round($totalDuration, 3),
            'throughput_ops_per_sec' => round($throughput, 2),
            'latency_ms' => [
                'min' => round($percentiles['min'], 2),
                'max' => round($percentiles['max'], 2),
                'p50' => round($percentiles['p50'], 2),
                'p90' => round($percentiles['p90'], 2),
                'p95' => round($percentiles['p95'], 2),
                'p99' => round($percentiles['p99'], 2),
                'avg' => round($percentiles['avg'], 2),
            ],
            'errors' => [
                'count' => $totalErrors,
                'rate_percent' => round($errorRate, 2),
            ],
        ],
    ];
    
    // Format output
    $output = '';
    if ($args['output_format'] === 'json') {
        $output = formatJson($results);
    } elseif ($args['output_format'] === 'csv') {
        $output = formatCsv($results);
    } else {
        fwrite(STDERR, "Error: Unsupported output format: " . $args['output_format'] . "\n");
        exit(1);
    }
    
    // Write output
    if ($args['output_file']) {
        file_put_contents($args['output_file'], $output);
        fwrite(STDERR, "Results written to: " . $args['output_file'] . "\n");
    } else {
        echo $output;
    }
    
    // Exit with error code if there were errors
    if ($totalErrors > 0) {
        fwrite(STDERR, "Warning: {$totalErrors} errors occurred during benchmark\n");
        exit(1);
    }
}

// Run the benchmark
main();
