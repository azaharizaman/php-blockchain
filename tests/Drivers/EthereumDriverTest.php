<?php

declare(strict_types=1);

namespace Tests\Drivers;

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

    public function testGetBalanceWithInvalidAddress(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Ethereum address format: invalid_address');

        $driver->getBalance('invalid_address');
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
                    'transactions' => [
                        [
                            'hash' => '0xtx1',
                            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'value' => '0xde0b6b3a7640000',
                            'gas' => '0x5208',
                            'gasPrice' => '0x3b9aca00'
                        ],
                        [
                            'hash' => '0xtx2',
                            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'value' => '0x0',
                            'gas' => '0x5208',
                            'gasPrice' => '0x3b9aca00'
                        ]
                    ],
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
                    'transactions' => [
                        [
                            'hash' => '0xtx1',
                            'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                            'value' => '0x0',
                            'gas' => '0x5208',
                            'gasPrice' => '0x3b9aca00'
                        ]
                    ],
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

    public function testEstimateGasSimpleTransfer(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x5208', // 21,000 gas
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            1.0
        );

        // Should return 21,000 * 1.2 = 25,200
        $this->assertEquals(25200, $gasEstimate);
    }

    public function testEstimateGasContractCall(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas with higher estimate for contract call
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0xea60', // 60,000 gas
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // USDC contract
            0.0,
            ['data' => '0xa9059cbb0000000000000000000000001234567890123456789012345678901234567890']
        );

        // Should return 60,000 * 1.2 = 72,000
        $this->assertEquals(72000, $gasEstimate);
    }

    public function testEstimateGasFallbackSimpleTransfer(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas with error
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => ['message' => 'Execution reverted'],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            1.0
        );

        // Should return fallback estimate for simple transfer: 21,000
        $this->assertEquals(21000, $gasEstimate);
    }

    public function testEstimateGasFallbackERC20Transfer(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas with error
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => ['message' => 'Insufficient funds'],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        // ERC-20 transfer has data starting with 0xa9059cbb (transfer function selector)
        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            0.0,
            ['data' => '0xa9059cbb0000000000000000000000001234567890123456789012345678901234567890']
        );

        // Should return fallback estimate for ERC-20 transfer: 65,000
        $this->assertEquals(65000, $gasEstimate);
    }

    public function testEstimateGasWithZeroAmount(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x5208', // 21,000 gas
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            0.0
        );

        // Should still estimate gas correctly
        $this->assertEquals(25200, $gasEstimate);
    }

    public function testEstimateGasWithLargeAmount(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x5208', // 21,000 gas (same for any amount)
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            1000.0
        );

        // Should return same gas estimate regardless of amount
        $this->assertEquals(25200, $gasEstimate);
    }

    public function testEstimateGasFallbackWithLargeContractData(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_estimateGas with error
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => ['message' => 'Gas estimation failed'],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        // Large contract data (not ERC-20 transfer)
        // 200 bytes of data = 100 hex characters (without 0x)
        $largeData = '0x' . str_repeat('1234567890abcdef', 25); // 400 hex chars = 200 bytes

        $gasEstimate = $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            0.0,
            ['data' => $largeData]
        );

        // Should return base + data cost: 100,000 + (200 * 68) = 113,600
        $this->assertEquals(113600, $gasEstimate);
    }

    public function testEstimateGasWithoutConnection(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Ethereum driver is not connected. Please call connect() first.');

        $this->driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            1.0
        );
    }

    public function testEstimateGasWithInvalidFromAddress(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Ethereum address format for 'from': invalid_from");

        $driver->estimateGas(
            'invalid_from',
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            1.0
        );
    }

    public function testEstimateGasWithInvalidToAddress(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Ethereum address format for 'to': invalid_to");

        $driver->estimateGas(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            'invalid_to',
            1.0
        );
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

    public function testGetBalanceWithZeroBalance(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBalance with 0 balance
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x0',
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');

        $this->assertEquals(0.0, $balance);
    }

    public function testGetBalanceWithLargeBalance(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBalance with large balance
            // 1000 ETH = 1000 * 10^18 wei = 0xde0b6b3a7640000 * 1000 = 0x3635c9adc5dea00000
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x3635c9adc5dea00000', // 1000 ETH
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');

        $this->assertEquals(1000.0, $balance);
    }

    public function testGetTransactionWithNonExistent(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getTransactionByHash with null (non-existent)
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => null,
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $transaction = $driver->getTransaction('0xnonexistenthash');

        $this->assertIsArray($transaction);
        $this->assertEmpty($transaction);
    }

    public function testGetBlockWithNonExistent(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBlockByNumber with null (non-existent)
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => null,
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $block = $driver->getBlock(999999999);

        $this->assertIsArray($block);
        $this->assertEmpty($block);
    }

    public function testGetBlockWithLatestTag(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response for eth_getBlockByHash with "latest" tag
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'number' => '0x123456',
                    'hash' => '0xlatestblockhash',
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

        $block = $driver->getBlock('latest');

        $this->assertIsArray($block);
        $this->assertEquals('0xlatestblockhash', $block['hash']);
    }

    public function testConnectWithHttpEndpoint(): void
    {
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
        $driver->connect(['endpoint' => 'http://localhost:8545']);

        $this->assertTrue(true);
    }

    public function testConnectWithHttpsEndpoint(): void
    {
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

        $this->assertTrue(true);
    }

    public function testConnectWithWssEndpoint(): void
    {
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
        $driver->connect(['endpoint' => 'wss://mainnet.infura.io/ws/v3/test']);

        $this->assertTrue(true);
    }

    public function testRpcErrorWithDifferentErrorCodes(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response with error code -32000
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32000,
                    'message' => 'Server error'
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ethereum RPC Error: Server error');

        $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
    }

    public function testRpcErrorWithInvalidParams(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // Response with error code -32602 (Invalid params)
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params'
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ethereum RPC Error: Invalid params');

        $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
    }

    public function testMultipleSequentialCalls(): void
    {
        $mockHandler = new MockHandler([
            // Response for eth_chainId during connect
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0x1',
                'id' => 1
            ])),
            // First call - getBalance
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => '0xde0b6b3a7640000',
                'id' => 1
            ])),
            // Second call - getTransaction
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'hash' => '0x123',
                    'from' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                    'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
                    'value' => '0xde0b6b3a7640000'
                ],
                'id' => 1
            ])),
            // Third call - getBlock
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'number' => '0x123456',
                    'hash' => '0xblockhash',
                    'timestamp' => '0x5f5e100',
                    'transactions' => []
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new EthereumDriver($adapter);
        $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/test']);

        // Make multiple calls and verify they all work
        $balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
        $this->assertEquals(1.0, $balance);

        $transaction = $driver->getTransaction('0x123');
        $this->assertEquals('0x123', $transaction['hash']);

        $block = $driver->getBlock(1193046);
        $this->assertEquals('0xblockhash', $block['hash']);
    }
}
