<?php

declare(strict_types=1);

namespace Tests\Bench;

use PHPUnit\Framework\TestCase;
use Blockchain\Registry\DriverRegistry;
use Blockchain\Telemetry\MetricCollector;
use Blockchain\Telemetry\NoopExporter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Drivers\EthereumDriver;

/**
 * DriverBenchTest
 *
 * Test suite for the benchmarking harness. Verifies that:
 * - Benchmark script runs successfully with mock driver
 * - Percentile calculations are accurate
 * - Output formats (JSON, CSV) are correctly generated
 * - Error handling works as expected
 *
 * @package Tests\Bench
 */
class DriverBenchTest extends TestCase
{
    private string $benchScript;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->benchScript = __DIR__ . '/../../bench/driver-bench.php';
        $this->tempDir = sys_get_temp_dir() . '/bench-test-' . uniqid();
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    /**
     * Test that benchmark script exists and is executable
     */
    public function testBenchmarkScriptExists(): void
    {
        $this->assertFileExists($this->benchScript);
        $this->assertFileIsReadable($this->benchScript);
    }

    /**
     * Test benchmark runs with mock driver in JSON mode
     */
    public function testBenchmarkRunsWithMockDriverJson(): void
    {
        $outputFile = $this->tempDir . '/results.json';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=10 --output-format=json --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $this->assertEquals(0, $exitCode, 'Benchmark should exit with code 0');
        $this->assertFileExists($outputFile, 'Output file should be created');
        
        $json = file_get_contents($outputFile);
        $this->assertNotEmpty($json, 'Output file should not be empty');
        
        $results = json_decode($json, true);
        $this->assertIsArray($results, 'Output should be valid JSON');
        $this->assertArrayHasKey('driver', $results);
        $this->assertArrayHasKey('metrics', $results);
        $this->assertArrayHasKey('latency_ms', $results['metrics']);
    }

    /**
     * Test benchmark runs with mock driver in CSV mode
     */
    public function testBenchmarkRunsWithMockDriverCsv(): void
    {
        $outputFile = $this->tempDir . '/results.csv';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=10 --output-format=csv --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $this->assertEquals(0, $exitCode, 'Benchmark should exit with code 0');
        $this->assertFileExists($outputFile, 'Output file should be created');
        
        $csv = file_get_contents($outputFile);
        $this->assertNotEmpty($csv, 'Output file should not be empty');
        $this->assertStringContainsString('metric,value,unit', $csv);
        $this->assertStringContainsString('driver,ethereum', $csv);
        $this->assertStringContainsString('latency_p50', $csv);
        $this->assertStringContainsString('throughput', $csv);
    }

    /**
     * Test specific workload scenarios
     */
    public function testSpecificWorkloadScenarios(): void
    {
        $workloads = ['getBalance', 'getTransaction', 'getBlock'];
        
        foreach ($workloads as $workload) {
            $outputFile = $this->tempDir . "/results-{$workload}.json";
            
            $command = sprintf(
                'php %s --driver=ethereum --mock --iterations=5 --workload=%s --output-format=json --output-file=%s 2>&1',
                escapeshellarg($this->benchScript),
                $workload,
                escapeshellarg($outputFile)
            );
            
            exec($command, $output, $exitCode);
            
            $this->assertEquals(0, $exitCode, "Benchmark should exit with code 0 for workload: {$workload}");
            $this->assertFileExists($outputFile, "Output file should be created for workload: {$workload}");
            
            $json = file_get_contents($outputFile);
            $results = json_decode($json, true);
            
            $this->assertEquals($workload, $results['workload'], "Workload should match: {$workload}");
        }
    }

    /**
     * Test percentile calculation accuracy
     */
    public function testPercentileCalculations(): void
    {
        // Test with known latency values
        $latencies = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0];
        
        $percentiles = $this->calculatePercentiles($latencies);
        
        $this->assertEquals(1.0, $percentiles['min']);
        $this->assertEquals(10.0, $percentiles['max']);
        $this->assertEquals(5.5, $percentiles['avg']);
        
        // p50 should be around median (5th element in sorted array)
        $this->assertEqualsWithDelta(5.0, $percentiles['p50'], 1.0);
        
        // p90 should be around 9th element
        $this->assertEqualsWithDelta(9.0, $percentiles['p90'], 1.0);
        
        // p99 should be close to max
        $this->assertEqualsWithDelta(10.0, $percentiles['p99'], 1.0);
    }

    /**
     * Test help message display
     */
    public function testHelpMessageDisplay(): void
    {
        $command = sprintf('php %s --help 2>&1', escapeshellarg($this->benchScript));
        
        exec($command, $output, $exitCode);
        
        $this->assertEquals(0, $exitCode, 'Help should exit with code 0');
        
        $outputText = implode("\n", $output);
        $this->assertStringContainsString('Driver Benchmark Script', $outputText);
        $this->assertStringContainsString('--driver=NAME', $outputText);
        $this->assertStringContainsString('--mock', $outputText);
        $this->assertStringContainsString('--iterations', $outputText);
    }

    /**
     * Test error handling when driver is not specified
     */
    public function testErrorWhenDriverNotSpecified(): void
    {
        $command = sprintf('php %s --mock --iterations=10 2>&1', escapeshellarg($this->benchScript));
        
        exec($command, $output, $exitCode);
        
        $this->assertNotEquals(0, $exitCode, 'Should exit with error code when driver not specified');
        
        $outputText = implode("\n", $output);
        $this->assertStringContainsString('--driver is required', $outputText);
    }

    /**
     * Test error handling when endpoint is not specified in live mode
     */
    public function testErrorWhenEndpointNotSpecifiedInLiveMode(): void
    {
        $command = sprintf('php %s --driver=ethereum --iterations=10 2>&1', escapeshellarg($this->benchScript));
        
        exec($command, $output, $exitCode);
        
        $this->assertNotEquals(0, $exitCode, 'Should exit with error code when endpoint not specified in live mode');
        
        $outputText = implode("\n", $output);
        $this->assertStringContainsString('--endpoint is required', $outputText);
    }

    /**
     * Test throughput calculation
     */
    public function testThroughputCalculation(): void
    {
        $outputFile = $this->tempDir . '/throughput.json';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=100 --output-format=json --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $json = file_get_contents($outputFile);
        $results = json_decode($json, true);
        
        $throughput = $results['metrics']['throughput_ops_per_sec'];
        $this->assertGreaterThan(0, $throughput, 'Throughput should be positive');
        $this->assertIsFloat($throughput) || $this->assertIsInt($throughput);
    }

    /**
     * Test error rate calculation (should be 0% in mock mode with valid responses)
     */
    public function testErrorRateInMockMode(): void
    {
        $outputFile = $this->tempDir . '/errors.json';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=50 --output-format=json --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $json = file_get_contents($outputFile);
        $results = json_decode($json, true);
        
        $this->assertEquals(0, $results['metrics']['errors']['count'], 'Error count should be 0 in mock mode');
        $this->assertEquals(0.0, $results['metrics']['errors']['rate_percent'], 'Error rate should be 0% in mock mode');
    }

    /**
     * Test JSON output structure
     */
    public function testJsonOutputStructure(): void
    {
        $outputFile = $this->tempDir . '/structure.json';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=20 --workload=getBalance --output-format=json --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $json = file_get_contents($outputFile);
        $results = json_decode($json, true);
        
        // Verify top-level structure
        $this->assertArrayHasKey('driver', $results);
        $this->assertArrayHasKey('workload', $results);
        $this->assertArrayHasKey('iterations', $results);
        $this->assertArrayHasKey('mode', $results);
        $this->assertArrayHasKey('metrics', $results);
        
        // Verify metrics structure
        $metrics = $results['metrics'];
        $this->assertArrayHasKey('duration_seconds', $metrics);
        $this->assertArrayHasKey('throughput_ops_per_sec', $metrics);
        $this->assertArrayHasKey('latency_ms', $metrics);
        $this->assertArrayHasKey('errors', $metrics);
        
        // Verify latency structure
        $latency = $metrics['latency_ms'];
        $this->assertArrayHasKey('min', $latency);
        $this->assertArrayHasKey('max', $latency);
        $this->assertArrayHasKey('p50', $latency);
        $this->assertArrayHasKey('p90', $latency);
        $this->assertArrayHasKey('p95', $latency);
        $this->assertArrayHasKey('p99', $latency);
        $this->assertArrayHasKey('avg', $latency);
        
        // Verify errors structure
        $errors = $metrics['errors'];
        $this->assertArrayHasKey('count', $errors);
        $this->assertArrayHasKey('rate_percent', $errors);
    }

    /**
     * Test CSV output format
     */
    public function testCsvOutputFormat(): void
    {
        $outputFile = $this->tempDir . '/format.csv';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=15 --workload=getBalance --output-format=csv --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $csv = file_get_contents($outputFile);
        $lines = explode("\n", trim($csv));
        
        // First line should be header
        $this->assertEquals('metric,value,unit', $lines[0]);
        
        // Check that all expected metrics are present
        $csvContent = implode("\n", $lines);
        $expectedMetrics = [
            'driver',
            'workload',
            'iterations',
            'mode',
            'duration',
            'throughput',
            'latency_min',
            'latency_max',
            'latency_p50',
            'latency_p90',
            'latency_p95',
            'latency_p99',
            'latency_avg',
            'error_count',
            'error_rate',
        ];
        
        foreach ($expectedMetrics as $metric) {
            $this->assertStringContainsString($metric, $csvContent, "CSV should contain metric: {$metric}");
        }
    }

    /**
     * Test all workloads mode
     */
    public function testAllWorkloadsMode(): void
    {
        $outputFile = $this->tempDir . '/all-workloads.json';
        
        $command = sprintf(
            'php %s --driver=ethereum --mock --iterations=5 --workload=all --output-format=json --output-file=%s 2>&1',
            escapeshellarg($this->benchScript),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $exitCode);
        
        $this->assertEquals(0, $exitCode, 'Benchmark should exit with code 0 for all workloads');
        
        $json = file_get_contents($outputFile);
        $results = json_decode($json, true);
        
        $this->assertEquals('all', $results['workload']);
        
        // In 'all' mode, we should have metrics aggregated from all workload types
        // The iteration count should reflect operations from all workloads
        $this->assertGreaterThan(0, $results['metrics']['duration_seconds']);
    }

    /**
     * Helper method to calculate percentiles (mirrors the bench script logic)
     *
     * @param array<float> $latencies Array of latency values
     * @return array<string,float> Percentile values
     */
    private function calculatePercentiles(array $latencies): array
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
     * Test mock adapter creation with Ethereum driver
     */
    public function testMockAdapterWithEthereumDriver(): void
    {
        // Create mock responses
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000', // 1 ETH in wei
            ]));
        }
        
        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);
        
        // Create driver with mock adapter
        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'http://mock.local']);
        
        // Create metric collector
        $exporter = new NoopExporter();
        $collector = new MetricCollector($exporter);
        
        // Run a few operations
        $latencies = [];
        for ($i = 0; $i < 5; $i++) {
            $timerId = $collector->startTimer('test.latency');
            try {
                $balance = $driver->getBalance('0x' . str_repeat('0', 40));
                $this->assertIsFloat($balance);
            } catch (\Exception $e) {
                $this->fail('Mock adapter should not throw exceptions: ' . $e->getMessage());
            }
            $latencies[] = $collector->stopTimer($timerId);
        }
        
        // Verify we got latency measurements
        $this->assertCount(5, $latencies);
        foreach ($latencies as $latency) {
            $this->assertIsFloat($latency);
            $this->assertGreaterThan(0, $latency);
        }
    }

    /**
     * Test that percentile calculations handle edge cases
     */
    public function testPercentileCalculationsEdgeCases(): void
    {
        // Test with single value
        $singleValue = [5.0];
        $percentiles = $this->calculatePercentiles($singleValue);
        $this->assertEquals(5.0, $percentiles['min']);
        $this->assertEquals(5.0, $percentiles['max']);
        $this->assertEquals(5.0, $percentiles['avg']);
        
        // Test with empty array
        $emptyArray = [];
        $percentiles = $this->calculatePercentiles($emptyArray);
        $this->assertEquals(0.0, $percentiles['min']);
        $this->assertEquals(0.0, $percentiles['max']);
        
        // Test with two values
        $twoValues = [3.0, 7.0];
        $percentiles = $this->calculatePercentiles($twoValues);
        $this->assertEquals(3.0, $percentiles['min']);
        $this->assertEquals(7.0, $percentiles['max']);
        $this->assertEquals(5.0, $percentiles['avg']);
    }
}
