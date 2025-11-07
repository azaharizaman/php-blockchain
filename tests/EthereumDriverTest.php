<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Blockchain\Drivers\EthereumDriver;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Utils\CachePool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class EthereumDriverTest extends TestCase
{
    private EthereumDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new EthereumDriver();
    }

    public function testConnectWithValidConfig(): void
    {
        // Mock eth_chainId response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1', // Mainnet
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $config = [
            'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
            'timeout' => 30
        ];

        $driver->connect($config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testConnectWithoutEndpoint(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Ethereum endpoint is required in configuration.');

        $this->driver->connect([]);
    }

    public function testGetBalanceSuccess(): void
    {
        // Mock HTTP responses
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBalance
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0xde0b6b3a7640000', // 1 ETH in wei (hex)
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');

        $this->assertEquals(1.0, $balance);
    }

    public function testGetBalanceWithError(): void
    {
        // Mock HTTP responses
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBalance with error
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => ['message' => 'Invalid address format'],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ethereum RPC Error: Invalid address format');

        $driver->getBalance('InvalidAddress');
    }

    public function testGetTransactionSuccess(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getTransactionByHash
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'hash' => '0x123abc',
                    'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                    'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                    'value' => '0xde0b6b3a7640000',
                    'blockNumber' => '0x123456',
                    'gas' => '0x5208',
                    'gasPrice' => '0x3b9aca00'
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $transaction = $driver->getTransaction('0x123abc');

        $this->assertIsArray($transaction);
        $this->assertEquals('0x123abc', $transaction['hash']);
        $this->assertEquals('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', $transaction['from']);
    }

    public function testGetBlockByNumberSuccess(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBlockByNumber
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'number' => '0x123456',
                    'hash' => '0xabc123def456',
                    'timestamp' => '0x5f5e100',
                    'transactions' => ['0xtx1', '0xtx2'],
                    'parentHash' => '0xparent123'
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $block = $driver->getBlock(1193046);

        $this->assertIsArray($block);
        $this->assertEquals('0x123456', $block['number']);
        $this->assertEquals('0xabc123def456', $block['hash']);
    }

    public function testGetBlockByHashSuccess(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBlockByHash
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'number' => '0x123456',
                    'hash' => '0xabc123def456',
                    'timestamp' => '0x5f5e100',
                    'transactions' => [],
                    'parentHash' => '0xparent123'
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $block = $driver->getBlock('0xabc123def456');

        $this->assertIsArray($block);
        $this->assertEquals('0xabc123def456', $block['hash']);
    }

    public function testSendTransactionNotImplemented(): void
    {
        // Mock eth_chainId response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Raw transaction signing not yet implemented for Ethereum driver.');

        $driver->sendTransaction('0xfrom', '0xto', 1.0);
    }

    public function testEstimateGasReturnsNull(): void
    {
        $result = $this->driver->estimateGas('0xfrom', '0xto', 1.0);
        $this->assertNull($result);
    }

    public function testGetTokenBalanceReturnsNull(): void
    {
        $result = $this->driver->getTokenBalance('0xaddress', '0xtokenaddress');
        $this->assertNull($result);
    }

    public function testGetNetworkInfoSuccess(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_chainId in getNetworkInfo
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1', // Mainnet
                'id' => 1
            ])),
            // Response for eth_gasPrice
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x3b9aca00', // 1 Gwei
                'id' => 1
            ])),
            // Response for eth_blockNumber
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x123456', // Block 1193046
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $networkInfo = $driver->getNetworkInfo();

        $this->assertIsArray($networkInfo);
        $this->assertEquals(1, $networkInfo['chainId']);
        $this->assertEquals(1000000000, $networkInfo['gasPrice']);
        $this->assertEquals(1193046, $networkInfo['blockNumber']);
    }

    public function testOperationWithoutConnection(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Ethereum driver is not connected. Please call connect() first.');

        $this->driver->getBalance('0xaddress');
    }

    public function testCachingBehavior(): void
    {
        $cache = new CachePool();

        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // First eth_getBalance call
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0xde0b6b3a7640000',
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter, $cache);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        // First call - should hit the network
        $balance1 = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');

        // Second call - should use cache (no additional mock response needed)
        $balance2 = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');

        $this->assertEquals($balance1, $balance2);
        $this->assertEquals(1.0, $balance2);
    }
}
