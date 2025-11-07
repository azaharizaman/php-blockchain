<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Blockchain\Drivers\SolanaDriver;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class SolanaDriverTest extends TestCase
{
    private SolanaDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new SolanaDriver();
    }

    public function testConnectWithValidConfig(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];

        $this->driver->connect($config);
        
        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testConnectWithoutEndpoint(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Solana endpoint is required in configuration.');
        
        $this->driver->connect([]);
    }

    public function testGetBalanceSuccess(): void
    {
        // Mock HTTP responses
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 1000000000], // 1 SOL in lamports
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Create driver with mocked adapter
        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $balance = $driver->getBalance('SomeValidSolanaAddress');
        
        $this->assertEquals(1.0, $balance); // 1 SOL
    }

    public function testGetBalanceWithError(): void
    {
        // Mock HTTP responses with error
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => ['message' => 'Invalid address'],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solana RPC Error: Invalid address');
        
        $driver->getBalance('InvalidAddress');
    }

    public function testGetTransactionSuccess(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => [
                    'slot' => 123456,
                    'transaction' => ['signatures' => ['tx_signature']],
                    'meta' => ['fee' => 5000]
                ],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

        $transaction = $driver->getTransaction('some_tx_hash');
        
        $this->assertIsArray($transaction);
        $this->assertEquals(123456, $transaction['slot']);
    }

    public function testSendTransactionNotImplemented(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transaction sending not yet implemented for Solana driver.');
        
        // Set up a mock adapter
        $mockHandler = new MockHandler([]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        $driver = new SolanaDriver($adapter);
        $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);
        
        $driver->sendTransaction('from_address', 'to_address', 1.0);
    }

    public function testEstimateGasReturnsNull(): void
    {
        $result = $this->driver->estimateGas('from', 'to', 1.0);
        $this->assertNull($result);
    }

    public function testOperationWithoutConnection(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Solana driver is not connected. Please call connect() first.');
        
        $this->driver->getBalance('some_address');
    }
}