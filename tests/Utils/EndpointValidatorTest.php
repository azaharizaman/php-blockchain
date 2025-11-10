<?php

declare(strict_types=1);

namespace Blockchain\Tests\Utils;

use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Utils\EndpointValidator;
use Blockchain\Utils\ValidationResult;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for EndpointValidator utility class.
 *
 * Verifies endpoint validation functionality including dry-run mode,
 * live HTTP validation, and RPC ping capabilities.
 */
class EndpointValidatorTest extends TestCase
{
    /**
     * Test validateDryRun() with valid HTTP URLs.
     */
    public function testValidateDryRunWithValidHttpUrls(): void
    {
        $validator = new EndpointValidator();

        $validUrls = [
            'http://localhost:8545',
            'https://api.mainnet-beta.solana.com',
            'https://eth-mainnet.g.alchemy.com/v2/your-api-key',
            'http://192.168.1.1:8545',
            'https://example.com:8080/rpc',
        ];

        foreach ($validUrls as $url) {
            $result = $validator->validateDryRun($url);
            $this->assertTrue(
                $result->isValid(),
                "Expected URL to be valid: {$url}"
            );
            $this->assertNull($result->getLatency());
            $this->assertNull($result->getError());
        }
    }

    /**
     * Test validateDryRun() with valid WebSocket URLs.
     */
    public function testValidateDryRunWithValidWebSocketUrls(): void
    {
        $validator = new EndpointValidator();

        $validUrls = [
            'wss://api.mainnet-beta.solana.com',
            'wss://ethereum.publicnode.com',
        ];

        foreach ($validUrls as $url) {
            $result = $validator->validateDryRun($url);
            $this->assertTrue(
                $result->isValid(),
                "Expected WebSocket URL to be valid: {$url}"
            );
        }
    }

    /**
     * Test validateDryRun() with invalid URL format.
     */
    public function testValidateDryRunWithInvalidUrlFormat(): void
    {
        $validator = new EndpointValidator();

        $result = $validator->validateDryRun('not-a-valid-url');
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('scheme', $result->getError());
    }

    /**
     * Test validateDryRun() with invalid scheme.
     */
    public function testValidateDryRunWithInvalidScheme(): void
    {
        $validator = new EndpointValidator();

        $invalidUrls = [
            'ftp://example.com',
            'file:///tmp/file',
            'ssh://user@host',
        ];

        foreach ($invalidUrls as $url) {
            $result = $validator->validateDryRun($url);
            $this->assertFalse(
                $result->isValid(),
                "Expected URL with invalid scheme to be invalid: {$url}"
            );
            $this->assertStringContainsString('scheme', $result->getError());
        }
    }

    /**
     * Test validateDryRun() with missing host.
     */
    public function testValidateDryRunWithMissingHost(): void
    {
        $validator = new EndpointValidator();

        $result = $validator->validateDryRun('http://');
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('host', $result->getError());
    }

    /**
     * Test validate() with successful HTTP request.
     */
    public function testValidateWithSuccessfulHttpRequest(): void
    {
        // Create mock handler with successful response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com');

        $this->assertTrue($result->isValid());
        $this->assertIsFloat($result->getLatency());
        $this->assertGreaterThan(0, $result->getLatency());
        $this->assertNull($result->getError());
    }

    /**
     * Test validate() with network connection error.
     */
    public function testValidateWithNetworkConnectionError(): void
    {
        // Create mock handler with connection exception
        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection refused',
                new Request('GET', 'https://api.example.com')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com');

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getError());
        $this->assertStringContainsString('Network error', $result->getError());
    }

    /**
     * Test validate() with HTTP error response.
     */
    public function testValidateWithHttpErrorResponse(): void
    {
        // Create mock handler with 404 error
        $mockHandler = new MockHandler([
            new Response(404, [], json_encode(['error' => 'Not Found']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com');

        $this->assertFalse($result->isValid());
        $this->assertIsFloat($result->getLatency());
        $this->assertNotNull($result->getError());
    }

    /**
     * Test validate() with RPC ping for Ethereum.
     */
    public function testValidateWithRpcPingForEthereum(): void
    {
        // Create mock handler with valid Ethereum RPC response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://eth.example.com', [
            'rpc-ping' => true,
            'blockchain' => 'ethereum'
        ]);

        $this->assertTrue($result->isValid());
        $this->assertIsFloat($result->getLatency());
        $this->assertNull($result->getError());
    }

    /**
     * Test validate() with RPC ping for Solana.
     */
    public function testValidateWithRpcPingForSolana(): void
    {
        // Create mock handler with valid Solana RPC response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => 'ok'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.mainnet-beta.solana.com', [
            'rpc-ping' => true,
            'blockchain' => 'solana'
        ]);

        $this->assertTrue($result->isValid());
        $this->assertIsFloat($result->getLatency());
        $this->assertNull($result->getError());
    }

    /**
     * Test validate() with RPC ping for unsupported blockchain.
     */
    public function testValidateWithRpcPingForUnsupportedBlockchain(): void
    {
        $validator = new EndpointValidator();
        $result = $validator->validate('https://api.example.com', [
            'rpc-ping' => true,
            'blockchain' => 'bitcoin'
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Unsupported blockchain', $result->getError());
    }

    /**
     * Test validate() with RPC error response.
     */
    public function testValidateWithRpcErrorResponse(): void
    {
        // Create mock handler with RPC error response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found'
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com', [
            'rpc-ping' => true,
            'blockchain' => 'ethereum'
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('RPC error', $result->getError());
        $this->assertStringContainsString('Method not found', $result->getError());
    }

    /**
     * Test validate() with invalid RPC response structure.
     */
    public function testValidateWithInvalidRpcResponseStructure(): void
    {
        // Create mock handler with invalid RPC response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'ok'  // Not a valid JSON-RPC response
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com', [
            'rpc-ping' => true,
            'blockchain' => 'ethereum'
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Invalid RPC response', $result->getError());
    }

    /**
     * Test validate() falls back to dry-run when URL is invalid.
     */
    public function testValidateFallsBackToDryRunForInvalidUrl(): void
    {
        $validator = new EndpointValidator();
        $result = $validator->validate('not-a-valid-url');

        $this->assertFalse($result->isValid());
        $this->assertNull($result->getLatency());
        $this->assertNotNull($result->getError());
        $this->assertStringContainsString('scheme', $result->getError());
    }

    /**
     * Test ValidationResult immutability.
     */
    public function testValidationResultIsImmutable(): void
    {
        $result = new ValidationResult(true, 0.123, null);

        $this->assertTrue($result->isValid());
        $this->assertEquals(0.123, $result->getLatency());
        $this->assertNull($result->getError());

        // Create another result with different values
        $result2 = new ValidationResult(false, null, 'Error message');

        $this->assertFalse($result2->isValid());
        $this->assertNull($result2->getLatency());
        $this->assertEquals('Error message', $result2->getError());

        // Original result should remain unchanged
        $this->assertTrue($result->isValid());
        $this->assertEquals(0.123, $result->getLatency());
    }

    /**
     * Test validate() measures latency correctly.
     */
    public function testValidateMeasuresLatencyCorrectly(): void
    {
        // Create mock handler
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);
        $result = $validator->validate('https://api.example.com');

        $this->assertTrue($result->isValid());
        $this->assertIsFloat($result->getLatency());
        // Latency should be a small positive number (< 1 second for mocked request)
        $this->assertGreaterThan(0, $result->getLatency());
        $this->assertLessThan(1.0, $result->getLatency());
    }

    /**
     * Test validate() without RPC ping option.
     */
    public function testValidateWithoutRpcPingOption(): void
    {
        // Create mock handler
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $validator = new EndpointValidator($adapter);

        // Test without rpc-ping option (default behavior)
        $result = $validator->validate('https://api.example.com');
        $this->assertTrue($result->isValid());

        // Test with rpc-ping explicitly set to false
        $result2 = $validator->validate('https://api.example.com', ['rpc-ping' => false]);
        $this->assertTrue($result2->isValid());
    }
}
