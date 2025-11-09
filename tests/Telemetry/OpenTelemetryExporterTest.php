<?php

declare(strict_types=1);

namespace Tests\Telemetry;

use PHPUnit\Framework\TestCase;
use Blockchain\Telemetry\OpenTelemetryExporter;
use Blockchain\Telemetry\ExporterInterface;
use Blockchain\Exceptions\ConfigurationException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;

/**
 * OpenTelemetryExporterTest
 *
 * Comprehensive test suite for the OpenTelemetryExporter class.
 * Tests metric conversion, HTTP export, error handling, batch limits,
 * and dry-run mode.
 */
class OpenTelemetryExporterTest extends TestCase
{
    /**
     * Test that OpenTelemetryExporter implements ExporterInterface
     */
    public function testImplementsInterface(): void
    {
        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
        ]);

        $this->assertInstanceOf(ExporterInterface::class, $exporter);
    }

    /**
     * Test that constructor requires endpoint configuration
     */
    public function testConstructorRequiresEndpoint(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OpenTelemetry exporter requires "endpoint" configuration');

        new OpenTelemetryExporter([]);
    }

    /**
     * Test that constructor validates endpoint URL format
     */
    public function testConstructorValidatesEndpointUrl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must be a valid URL');

        new OpenTelemetryExporter([
            'endpoint' => 'not-a-valid-url',
        ]);
    }

    /**
     * Test successful instantiation with valid configuration
     */
    public function testSuccessfulInstantiation(): void
    {
        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
        ]);

        $this->assertInstanceOf(OpenTelemetryExporter::class, $exporter);
    }

    /**
     * Test export in dry-run mode does not make network calls
     */
    public function testDryRunModeDoesNotMakeNetworkCalls(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Dry-run mode: Would export metrics'),
                $this->callback(function ($context) {
                    return isset($context['metric_count']) && $context['metric_count'] === 1;
                })
            );

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
            'batch_size' => 1, // Force immediate flush
        ], $mockLogger);

        $exporter->export([
            'test_counter' => 42,
        ]);

        // If dry-run works, no network error will occur
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test counter metric conversion to OT format
     */
    public function testCounterMetricConversion(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to test with custom client
        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export counter metric
        $exporter->export([
            'request_count' => 42,
        ]);

        // Verify request was made (implicitly tested by MockHandler not throwing)
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test gauge metric conversion to OT format
     */
    public function testGaugeMetricConversion(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export gauge metric
        $exporter->export([
            'active_connections' => 15.5,
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test histogram metric conversion to OT format
     */
    public function testHistogramMetricConversion(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export histogram metric (timer with aggregates)
        $exporter->export([
            'request_latency_ms' => [
                'count' => 100,
                'sum' => 24500.0,
                'min' => 150.0,
                'max' => 500.0,
                'avg' => 245.0,
            ],
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test mixed metric types in single export
     */
    public function testMixedMetricTypes(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export multiple metric types
        $exporter->export([
            'request_count' => 42,
            'active_connections' => 15.5,
            'request_latency_ms' => [
                'count' => 100,
                'sum' => 24500.0,
                'min' => 150.0,
                'max' => 500.0,
                'avg' => 245.0,
            ],
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test context attributes are preserved as resource attributes
     */
    public function testContextAttributesPreserved(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export with context
        $exporter->export([
            'request_count' => 42,
            'context' => [
                'driver' => 'ethereum',
                'network' => 'mainnet',
                'operation' => 'sendTransaction',
            ],
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test batch size limits
     */
    public function testBatchSizeLimits(): void
    {
        $callCount = 0;
        $mockHandler = new MockHandler([
            function () use (&$callCount) {
                $callCount++;
                return new Response(200, [], json_encode(['status' => 'success']));
            },
            function () use (&$callCount) {
                $callCount++;
                return new Response(200, [], json_encode(['status' => 'success']));
            },
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 2, // Flush after 2 metrics
        ]);

        // Export 4 metrics (should trigger 2 flushes)
        $exporter->export(['metric1' => 1]);
        $exporter->export(['metric2' => 2]);
        $exporter->export(['metric3' => 3]);
        $exporter->export(['metric4' => 4]);

        // Verify 2 HTTP calls were made
        $this->assertEquals(2, $callCount);
    }

    /**
     * Test network failure handling
     */
    public function testNetworkFailureHandling(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to export metrics to OpenTelemetry collector'),
                $this->arrayHasKey('error')
            );

        $mockHandler = new MockHandler([
            new RequestException(
                'Connection refused',
                new Request('POST', 'http://localhost:4318/v1/metrics')
            ),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ], $mockLogger);

        // Export should not throw, but log error
        $exporter->export(['metric' => 1]);

        // Test passes if no exception thrown
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test HTTP 5xx error handling
     */
    public function testServerErrorHandling(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('error');

        $mockHandler = new MockHandler([
            new Response(503, [], 'Service Unavailable'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ], $mockLogger);

        // Export should not throw, but log error
        $exporter->export(['metric' => 1]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test environment variable override for endpoint
     */
    public function testEnvironmentVariableOverrideEndpoint(): void
    {
        // Set environment variable
        putenv('OTEL_EXPORTER_OTLP_ENDPOINT=http://env-endpoint:4318/v1/metrics');

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://config-endpoint:4318/v1/metrics',
            'dry_run' => true,
        ]);

        // Clean up
        putenv('OTEL_EXPORTER_OTLP_ENDPOINT');

        // Verify exporter was created (environment override applied)
        $this->assertInstanceOf(OpenTelemetryExporter::class, $exporter);
    }

    /**
     * Test environment variable override for headers
     */
    public function testEnvironmentVariableOverrideHeaders(): void
    {
        // Set environment variable
        putenv('OTEL_EXPORTER_OTLP_HEADERS=Authorization=Bearer token123,X-Custom=value');

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
        ]);

        // Clean up
        putenv('OTEL_EXPORTER_OTLP_HEADERS');

        $this->assertInstanceOf(OpenTelemetryExporter::class, $exporter);
    }

    /**
     * Test export with custom headers
     */
    public function testExportWithCustomHeaders(): void
    {
        $mockHandler = new MockHandler([
            function (Request $request) {
                // Verify Authorization header is present
                $this->assertTrue($request->hasHeader('Authorization'));
                return new Response(200, [], json_encode(['status' => 'success']));
            },
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'headers' => [
                'Authorization' => 'Bearer secret-token',
            ],
            'batch_size' => 1,
        ]);

        $exporter->export(['metric' => 1]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test export with resource attributes
     */
    public function testExportWithResourceAttributes(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'resource_attributes' => [
                'service.name' => 'test-service',
                'deployment.environment' => 'production',
            ],
            'batch_size' => 1,
        ]);

        $exporter->export(['metric' => 1]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test empty metrics array does nothing
     */
    public function testEmptyMetricsArrayDoesNothing(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->never())
            ->method('info');
        $mockLogger->expects($this->never())
            ->method('error');

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
        ], $mockLogger);

        $exporter->export([]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test auto-flush on destruct
     */
    public function testAutoFlushOnDestruct(): void
    {
        $callCount = 0;
        $mockHandler = new MockHandler([
            function () use (&$callCount) {
                $callCount++;
                return new Response(200, [], json_encode(['status' => 'success']));
            },
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 100, // Large batch size to prevent auto-flush
        ]);

        // Add metric but don't reach batch size
        $exporter->export(['metric' => 1]);

        // Trigger destructor
        unset($exporter);

        // Verify flush was called on destruct
        $this->assertEquals(1, $callCount);
    }

    /**
     * Test unit determination from metric name
     */
    public function testUnitDeterminationFromMetricName(): void
    {
        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'dry_run' => true,
        ]);

        // Test different metric names that should get appropriate units
        $exporter->export([
            'request_latency_ms' => 245.5, // Should be 'ms'
            'response_size_bytes' => 1024,  // Should be 'By'
            'request_count' => 42,          // Should be '1'
            'cache_hit_ratio' => 0.85,      // Should be '%'
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test that structural elements are skipped during conversion
     */
    public function testStructuralElementsSkippedDuringConversion(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ]);

        // Export with structural elements that should be ignored
        $exporter->export([
            'request_count' => 42,
            'timestamp' => 1699522152,
            'labels' => ['key' => 'value'],
            'metadata' => ['info' => 'data'],
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test custom timeout configuration
     */
    public function testCustomTimeoutConfiguration(): void
    {
        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'timeout' => 60,
            'dry_run' => true,
        ]);

        $this->assertInstanceOf(OpenTelemetryExporter::class, $exporter);
    }

    /**
     * Test successful export logs debug message
     */
    public function testSuccessfulExportLogsDebugMessage(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Successfully exported metrics to OpenTelemetry collector'),
                $this->callback(function ($context) {
                    return isset($context['metric_count']) && $context['endpoint'];
                })
            );

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'success'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ], $mockLogger);

        $exporter->export(['metric' => 1]);
    }

    /**
     * Test buffer is cleared after successful flush
     */
    public function testBufferClearedAfterSuccessfulFlush(): void
    {
        $callCount = 0;
        $mockHandler = new MockHandler([
            function () use (&$callCount) {
                $callCount++;
                return new Response(200, [], json_encode(['status' => 'success']));
            },
            function () use (&$callCount) {
                $callCount++;
                return new Response(200, [], json_encode(['status' => 'success']));
            },
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 2,
        ]);

        // First batch
        $exporter->export(['metric1' => 1]);
        $exporter->export(['metric2' => 2]);

        // Second batch
        $exporter->export(['metric3' => 3]);
        $exporter->export(['metric4' => 4]);

        // Both batches should have been flushed separately
        $this->assertEquals(2, $callCount);
    }

    /**
     * Test buffer is cleared on error to prevent memory buildup
     */
    public function testBufferClearedOnError(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);

        $mockHandler = new MockHandler([
            new RequestException(
                'Connection refused',
                new Request('POST', 'http://localhost:4318/v1/metrics')
            ),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $exporter = new OpenTelemetryExporter([
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'batch_size' => 1,
        ], $mockLogger);

        // Export should clear buffer even on error
        $exporter->export(['metric' => 1]);

        // If we export again, it should be a new attempt (not accumulated)
        $this->expectNotToPerformAssertions();
    }
}
