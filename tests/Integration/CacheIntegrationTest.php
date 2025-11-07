<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Blockchain\Drivers\SolanaDriver;
use Blockchain\Utils\CachePool;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CacheIntegrationTest extends TestCase
{
    private SolanaDriver $driver;
    private CachePool $cache;

    protected function setUp(): void
    {
        $this->cache = new CachePool();
        $this->driver = new SolanaDriver($this->cache);
    }

    public function testGetBalanceWithCacheHit(): void
    {
        // Create a mock that should only be called once
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 2000000000], // 2 SOL in lamports
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to set the client
        $reflection = new \ReflectionClass($this->driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->driver, $client);

        $address = 'TestSolanaAddress123';

        // First call - should hit the network
        $balance1 = $this->driver->getBalance($address);
        $this->assertEquals(2.0, $balance1);

        // Second call - should hit the cache, not make another network request
        $balance2 = $this->driver->getBalance($address);
        $this->assertEquals(2.0, $balance2);

        // Both balances should be identical
        $this->assertEquals($balance1, $balance2);

        // MockHandler would throw exception if called more than once
        // since we only added one response
    }

    public function testGetTransactionWithCacheHit(): void
    {
        $txData = [
            'slot' => 123456,
            'transaction' => ['signatures' => ['tx_signature']],
            'meta' => ['fee' => 5000]
        ];

        // Create a mock that should only be called once
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => $txData,
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to set the client
        $reflection = new \ReflectionClass($this->driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->driver, $client);

        $txHash = 'some_transaction_hash';

        // First call - should hit the network
        $transaction1 = $this->driver->getTransaction($txHash);
        $this->assertEquals($txData, $transaction1);

        // Second call - should hit the cache
        $transaction2 = $this->driver->getTransaction($txHash);
        $this->assertEquals($txData, $transaction2);

        // Both transactions should be identical
        $this->assertEquals($transaction1, $transaction2);
    }

    public function testGetBlockWithCacheHit(): void
    {
        $blockData = [
            'blockhash' => 'block_hash_123',
            'previousBlockhash' => 'prev_block_hash',
            'transactions' => []
        ];

        // Create a mock that should only be called once
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => $blockData,
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to set the client
        $reflection = new \ReflectionClass($this->driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->driver, $client);

        $blockNumber = 100000;

        // First call - should hit the network
        $block1 = $this->driver->getBlock($blockNumber);
        $this->assertEquals($blockData, $block1);

        // Second call - should hit the cache
        $block2 = $this->driver->getBlock($blockNumber);
        $this->assertEquals($blockData, $block2);

        // Both blocks should be identical
        $this->assertEquals($block1, $block2);
    }

    public function testCacheTtlExpiration(): void
    {
        // Set very short TTL on the cache
        $this->cache->setDefaultTtl(1);

        // Create two responses since cache will expire
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 1000000000], // 1 SOL
                'id' => 1
            ])),
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 2000000000], // 2 SOL (different value)
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to set the client
        $reflection = new \ReflectionClass($this->driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->driver, $client);

        $address = 'TestAddress';

        // First call
        $balance1 = $this->driver->getBalance($address);
        $this->assertEquals(1.0, $balance1);

        // Wait for cache to expire
        sleep(2);

        // Second call - cache expired, should fetch from network again
        $balance2 = $this->driver->getBalance($address);
        $this->assertEquals(2.0, $balance2);

        // Balances should be different since cache expired and new data was fetched
        $this->assertNotEquals($balance1, $balance2);
    }

    public function testDriverWithoutExplicitCache(): void
    {
        // Driver should create its own cache if not provided
        $driver = new SolanaDriver();

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 3000000000], // 3 SOL
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $reflection = new \ReflectionClass($driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($driver, $client);

        $address = 'TestAddress';
        $balance = $driver->getBalance($address);

        $this->assertEquals(3.0, $balance);

        // Second call should use cache
        $balance2 = $driver->getBalance($address);
        $this->assertEquals(3.0, $balance2);
    }

    public function testCacheKeysAreDeterministic(): void
    {
        $address = 'SameAddress123';

        // First driver instance
        $cache1 = new CachePool();
        $driver1 = new SolanaDriver($cache1);

        $mockHandler1 = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 5000000000], // 5 SOL
                'id' => 1
            ]))
        ]);

        $handlerStack1 = HandlerStack::create($mockHandler1);
        $client1 = new Client(['handler' => $handlerStack1]);

        $reflection1 = new \ReflectionClass($driver1);
        $clientProperty1 = $reflection1->getProperty('client');
        $clientProperty1->setAccessible(true);
        $clientProperty1->setValue($driver1, $client1);

        // Cache the balance
        $balance1 = $driver1->getBalance($address);
        $this->assertEquals(5.0, $balance1);

        // Second driver instance with same cache
        $driver2 = new SolanaDriver($cache1);

        $reflection2 = new \ReflectionClass($driver2);
        $clientProperty2 = $reflection2->getProperty('client');
        $clientProperty2->setAccessible(true);
        // Don't set client for driver2 to ensure it uses cache

        // This should NOT make a network request, but use cached value
        $key = CachePool::generateKey('getBalance', ['address' => $address]);
        $cachedValue = $cache1->get($key);

        $this->assertEquals(5.0, $cachedValue);
    }
}
