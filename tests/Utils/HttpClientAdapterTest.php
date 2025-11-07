<?php

declare(strict_types=1);

namespace Blockchain\Tests\Utils;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Utils\HttpClientAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for HttpClientAdapter utility class.
 *
 * Uses Guzzle MockHandler to test HTTP operations without real network calls.
 * This follows SEC-001 requirement for no network calls during unit tests.
 */
class HttpClientAdapterTest extends TestCase
{
    /**
     * Test constructor with default client.
     */
    public function testConstructorWithDefaultClient(): void
    {
        $adapter = new HttpClientAdapter();

        $this->assertInstanceOf(HttpClientAdapter::class, $adapter);
    }

    /**
     * Test constructor with custom client.
     */
    public function testConstructorWithCustomClient(): void
    {
        $client = new Client(['timeout' => 60]);
        $adapter = new HttpClientAdapter($client);

        $this->assertInstanceOf(HttpClientAdapter::class, $adapter);
    }

    /**
     * Test get() method with successful response.
     */
    public function testGetMethodWithSuccessfulResponse(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => ['id' => 1, 'name' => 'Test']
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->get('https://api.example.com/data');

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertEquals(1, $result['data']['id']);
    }

    /**
     * Test get() method with query parameters.
     */
    public function testGetMethodWithQueryParameters(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['results' => []]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->get('https://api.example.com/search', [
            'query' => ['q' => 'test', 'limit' => 10]
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
    }

    /**
     * Test get() method with 404 error response.
     */
    public function testGetMethodWith404Error(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Not Found',
                new Request('GET', 'test'),
                new Response(404, [], 'Not Found')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTP request failed with status 404/');

        $adapter->get('https://api.example.com/notfound');
    }

    /**
     * Test get() method with 500 error response.
     */
    public function testGetMethodWith500Error(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Internal Server Error',
                new Request('GET', 'test'),
                new Response(500, [], 'Server Error')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTP request failed with status 500/');

        $adapter->get('https://api.example.com/error');
    }

    /**
     * Test get() method with network error.
     */
    public function testGetMethodWithNetworkError(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection timeout',
                new Request('GET', 'test')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTP request failed/');

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
        $adapter = new HttpClientAdapter($client);

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
            new Response(200, [], json_encode([
                'status' => 'created',
                'id' => 123
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->post('https://api.example.com/create', [
            'name' => 'Alice',
            'email' => 'alice@example.com'
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(123, $result['id']);
    }

    /**
     * Test post() method with empty data.
     */
    public function testPostMethodWithEmptyData(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok']))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->post('https://api.example.com/ping', []);

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['status']);
    }

    /**
     * Test post() method with nested data.
     */
    public function testPostMethodWithNestedData(): void
    {
        $mockHandler = new MockHandler([
            new Response(201, [], json_encode(['created' => true]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->post('https://api.example.com/users', [
            'user' => [
                'name' => 'Bob',
                'profile' => [
                    'age' => 30,
                    'city' => 'NYC'
                ]
            ]
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['created']);
    }

    /**
     * Test post() method with 400 error response.
     */
    public function testPostMethodWith400Error(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Bad Request',
                new Request('POST', 'test'),
                new Response(400, [], 'Bad Request')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTP request failed with status 400/');

        $adapter->post('https://api.example.com/invalid', ['bad' => 'data']);
    }

    /**
     * Test post() method with network timeout.
     */
    public function testPostMethodWithNetworkTimeout(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Timeout',
                new Request('POST', 'test')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTP request failed/');

        $adapter->post('https://api.example.com/slow', ['data' => 'test']);
    }

    /**
     * Test post() method with invalid JSON response.
     */
    public function testPostMethodWithInvalidJsonResponse(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], 'Invalid JSON response')
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON response/');

        $adapter->post('https://api.example.com/test', ['data' => 'test']);
    }

    /**
     * Test post() method with additional options.
     */
    public function testPostMethodWithAdditionalOptions(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['success' => true]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        $result = $adapter->post(
            'https://api.example.com/auth',
            ['username' => 'user'],
            ['headers' => ['Authorization' => 'Bearer token']]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test that error messages preserve exception information.
     */
    public function testExceptionPreservesOriginalException(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'DNS lookup failed',
                new Request('GET', 'test')
            )
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new HttpClientAdapter($client);

        try {
            $adapter->get('https://nonexistent.example.com');
            $this->fail('Expected ConfigurationException to be thrown');
        } catch (ConfigurationException $e) {
            $this->assertStringContainsString('HTTP request failed', $e->getMessage());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    /**
     * Test multiple sequential requests with MockHandler.
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
        $adapter = new HttpClientAdapter($client);

        $result1 = $adapter->get('https://api.example.com/1');
        $this->assertEquals(1, $result1['request']);

        $result2 = $adapter->get('https://api.example.com/2');
        $this->assertEquals(2, $result2['request']);

        $result3 = $adapter->post('https://api.example.com/3', []);
        $this->assertEquals(3, $result3['request']);
    }
}
