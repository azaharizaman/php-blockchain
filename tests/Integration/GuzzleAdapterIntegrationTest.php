<?php

declare(strict_types=1);

namespace Blockchain\Tests\Integration;

use Blockchain\Drivers\SolanaDriver;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GuzzleAdapter with blockchain drivers.
 *
 * Tests that the adapter properly integrates with drivers and handles
 * cross-driver compatibility scenarios.
 */
class GuzzleAdapterIntegrationTest extends TestCase
{
    /**
     * Test GuzzleAdapter integration with SolanaDriver.
     */
    public function testGuzzleAdapterWithSolanaDriver(): void
    {
        // Mock Solana RPC responses
        $mockHandler = new MockHandler([
            // Response for getBalance
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 1000000000] // 1 SOL in lamports
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Create driver with adapter
        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        // Test balance retrieval
        $balance = $driver->getBalance('test_address');
        
        $this->assertEquals(1.0, $balance);
    }

    /**
     * Test error propagation from adapter to driver.
     */
    public function testErrorPropagationFromAdapterToDriver(): void
    {
        // Mock error response
        $mockHandler = new MockHandler([
            new Response(500, [], json_encode([
                'error' => ['message' => 'Internal RPC error']
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $this->expectException(\Blockchain\Exceptions\TransactionException::class);
        
        $driver->getBalance('test_address');
    }

    /**
     * Test adapter with multiple sequential driver operations.
     */
    public function testAdapterWithMultipleDriverOperations(): void
    {
        // Mock multiple RPC responses
        $mockHandler = new MockHandler([
            // Response for getBalance
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 2000000000] // 2 SOL
            ])),
            // Response for getNetworkInfo
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'epoch' => 300,
                    'slotIndex' => 12345
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        // Test multiple operations
        $balance = $driver->getBalance('test_address');
        $this->assertEquals(2.0, $balance);

        $networkInfo = $driver->getNetworkInfo();
        $this->assertIsArray($networkInfo);
        $this->assertEquals(300, $networkInfo['epoch']);
    }

    /**
     * Test adapter configuration is properly applied to driver requests.
     */
    public function testAdapterConfigurationInDriver(): void
    {
        // Create adapter with custom timeout
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 500000000]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        
        // Custom config with longer timeout
        $adapter = new GuzzleAdapter($client, ['timeout' => 60]);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $balance = $driver->getBalance('test_address');
        
        $this->assertEquals(0.5, $balance);
    }

    /**
     * Test adapter handles Solana RPC error responses correctly.
     */
    public function testAdapterHandlesSolanaRpcErrors(): void
    {
        // Mock Solana RPC error response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid request'
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solana RPC Error: Invalid request');

        $driver->getBalance('invalid_address');
    }

    /**
     * Test adapter with token balance retrieval.
     */
    public function testAdapterWithTokenBalance(): void
    {
        // Mock token balance response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'value' => [
                        [
                            'account' => [
                                'data' => [
                                    'parsed' => [
                                        'info' => [
                                            'tokenAmount' => [
                                                'uiAmount' => 100.5
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $tokenBalance = $driver->getTokenBalance('wallet_address', 'token_mint_address');
        
        $this->assertEquals(100.5, $tokenBalance);
    }

    /**
     * Test adapter with transaction retrieval.
     */
    public function testAdapterWithTransactionRetrieval(): void
    {
        // Mock transaction response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'slot' => 123456,
                    'transaction' => [
                        'message' => [
                            'accountKeys' => ['key1', 'key2']
                        ]
                    ],
                    'meta' => [
                        'err' => null,
                        'fee' => 5000
                    ]
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $transaction = $driver->getTransaction('signature123');
        
        $this->assertIsArray($transaction);
        $this->assertEquals(123456, $transaction['slot']);
        $this->assertArrayHasKey('meta', $transaction);
    }

    /**
     * Test adapter with block retrieval.
     */
    public function testAdapterWithBlockRetrieval(): void
    {
        // Mock block response
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'blockhash' => 'blockhash123',
                    'previousBlockhash' => 'prevhash456',
                    'blockTime' => 1625097600,
                    'transactions' => []
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $block = $driver->getBlock(12345);
        
        $this->assertIsArray($block);
        $this->assertEquals('blockhash123', $block['blockhash']);
        $this->assertArrayHasKey('transactions', $block);
    }

    /**
     * Test that adapter can be reused across multiple driver instances.
     */
    public function testAdapterReuseAcrossDrivers(): void
    {
        // Mock responses for two different driver instances
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 1000000000]
            ])),
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 2000000000]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $sharedAdapter = new GuzzleAdapter($client);

        // First driver instance
        $driver1 = new SolanaDriver($sharedAdapter);
        $driver1->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);
        $balance1 = $driver1->getBalance('address1');
        
        // Second driver instance (reusing adapter)
        $driver2 = new SolanaDriver($sharedAdapter);
        $driver2->connect(['endpoint' => 'https://api.devnet.solana.com']);
        $balance2 = $driver2->getBalance('address2');

        $this->assertEquals(1.0, $balance1);
        $this->assertEquals(2.0, $balance2);
    }
}
