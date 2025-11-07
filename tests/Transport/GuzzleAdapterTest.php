<?php

declare(strict_types=1);

namespace Blockchain\Tests\Transport;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\ValidationException;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for GuzzleAdapter.
 *
 * Uses Guzzle MockHandler to test HTTP operations without real network calls.
 * This follows SEC-001 requirement for no network calls during unit tests.
 */
class GuzzleAdapterTest extends TestCase
{
    /**
     * Test constructor with default client.
     */
    public function testConstructorWithDefaultClient(): void
    {
        $adapter = new GuzzleAdapter();
        $this->assertInstanceOf(GuzzleAdapter::class, $adapter);
    }

    /**
     * Test constructor with custom client.
     */
    public function testConstructorWithCustomClient(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['test' => 'data']))
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $adapter = new GuzzleAdapter($client);
        $this->assertInstanceOf(GuzzleAdapter::class, $adapter);
    }

    /**
     * Test constructor with custom configuration.
     */
    public function testConstructorWithCustomConfig(): void
    {
        $adapter = new GuzzleAdapter(null, ['timeout' => 60, 'verify' => false]);
        $this->assertInstanceOf(GuzzleAdapter::class, $adapter);
    }

    /**
     * Test get() method with successful response.
     */
    public function testGetMethodWithSuccessfulResponse(): void
    {
        $responseData = [
            'status' => 'success',
            'data' => ['id' => 1, 'name' => 'Test']
        ];
        
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $result = $adapter->get('https://api.example.com/data');

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertEquals(1, $result['data']['id']);
    }

    /**
     * Test get() method with 404 error response.
     */
    public function testGetMethodWith404Error(): void
    {
        $mockHandler = new MockHandler([
            new Response(404, [], json_encode(['error' => 'Resource not found']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/HTTP 404 error/');

        $adapter->get('https://api.example.com/notfound');
    }

    /**
     * Test get() method with 500 error response.
     */
    public function testGetMethodWith500Error(): void
    {
        $mockHandler = new MockHandler([
            new Response(500, [], json_encode(['message' => 'Internal server error']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageMatches('/HTTP 500 error/');

        $adapter->get('https://api.example.com/error');
    }

    /**
     * Test get() method with network error (ConnectException).
     */
    public function testGetMethodWithNetworkError(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Connection timeout', new Request('GET', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Network connection failed/');

        $adapter->get('https://api.example.com/timeout');
    }

    /**
     * Test get() method with invalid JSON response.
     */
    public function testGetMethodWithInvalidJsonResponse(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], 'This is not JSON')
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON response/');

        $adapter->get('https://api.example.com/invalid');
    }

    /**
     * Test post() method with successful response.
     */
    public function testPostMethodWithSuccessfulResponse(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'created', 'id' => 123]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $result = $adapter->post('https://api.example.com/create', [
            'name' => 'Alice',
            'email' => 'alice@example.com'
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(123, $result['id']);
    }

    /**
     * Test post() method with 400 error response.
     */
    public function testPostMethodWith400Error(): void
    {
        $mockHandler = new MockHandler([
            new Response(400, [], json_encode(['error' => 'Bad Request']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/HTTP 400 error/');

        $adapter->post('https://api.example.com/invalid', ['bad' => 'data']);
    }

    /**
     * Test post() method with network timeout.
     */
    public function testPostMethodWithNetworkTimeout(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException('Timeout', new Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Network connection failed/');

        $adapter->post('https://api.example.com/slow', ['data' => 'test']);
    }

    /**
     * Test setTimeout method.
     */
    public function testSetTimeout(): void
    {
        $adapter = new GuzzleAdapter();
        $adapter->setTimeout(60);
        $this->assertInstanceOf(GuzzleAdapter::class, $adapter);
    }

    /**
     * Test setTimeout with invalid value.
     */
    public function testSetTimeoutWithInvalidValue(): void
    {
        $adapter = new GuzzleAdapter();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        $adapter->setTimeout(0);
    }

    /**
     * Test setDefaultHeader method.
     */
    public function testSetDefaultHeader(): void
    {
        $adapter = new GuzzleAdapter();
        $adapter->setDefaultHeader('Authorization', 'Bearer token123');
        $this->assertInstanceOf(GuzzleAdapter::class, $adapter);
    }

    /**
     * Test handling ClientException (4xx errors).
     */
    public function testHandleClientException(): void
    {
        $mockHandler = new MockHandler([
            new ClientException(
                'Client error',
                new Request('GET', 'test'),
                new Response(401, [], json_encode(['error' => 'Unauthorized']))
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Client error \(HTTP 401\)/');

        $adapter->get('https://api.example.com/protected');
    }

    /**
     * Test handling ServerException (5xx errors).
     */
    public function testHandleServerException(): void
    {
        $mockHandler = new MockHandler([
            new ServerException(
                'Server error',
                new Request('POST', 'test'),
                new Response(502, [], json_encode(['message' => 'Bad Gateway']))
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessageMatches('/Server error \(HTTP 502\)/');

        $adapter->post('https://api.example.com/action', []);
    }

    /**
     * Test multiple sequential requests.
     */
    public function testMultipleSequentialRequests(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['request' => 1])),
            new Response(200, [], json_encode(['request' => 2])),
            new Response(200, [], json_encode(['request' => 3]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $result1 = $adapter->get('https://api.example.com/1');
        $this->assertEquals(1, $result1['request']);

        $result2 = $adapter->get('https://api.example.com/2');
        $this->assertEquals(2, $result2['request']);

        $result3 = $adapter->post('https://api.example.com/3', []);
        $this->assertEquals(3, $result3['request']);
    }
}
