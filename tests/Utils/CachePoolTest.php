<?php

declare(strict_types=1);

namespace Tests\Utils;

use PHPUnit\Framework\TestCase;
use Blockchain\Utils\CachePool;

class CachePoolTest extends TestCase
{
    private CachePool $cache;

    protected function setUp(): void
    {
        $this->cache = new CachePool();
    }

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('key1', 'value1'));
        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cache->get('non_existent'));
    }

    public function testGetWithDefault(): void
    {
        $default = 'default_value';
        $this->assertEquals($default, $this->cache->get('non_existent', $default));
    }

    public function testHasExistingKey(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
    }

    public function testHasNonExistentKey(): void
    {
        $this->assertFalse($this->cache->has('non_existent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
        
        $this->assertTrue($this->cache->delete('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testDeleteNonExistentKey(): void
    {
        $this->assertFalse($this->cache->delete('non_existent'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $this->assertTrue($this->cache->clear());
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testTtlExpiration(): void
    {
        // Set item with 1 second TTL
        $this->cache->set('key1', 'value1', 1);
        $this->assertTrue($this->cache->has('key1'));
        $this->assertEquals('value1', $this->cache->get('key1'));

        // Sleep for 2 seconds to ensure expiration
        sleep(2);

        // Item should be expired
        $this->assertFalse($this->cache->has('key1'));
        $this->assertNull($this->cache->get('key1'));
    }

    public function testTtlNotExpired(): void
    {
        // Set item with 5 second TTL
        $this->cache->set('key1', 'value1', 5);
        $this->assertTrue($this->cache->has('key1'));
        
        // Sleep for 1 second
        sleep(1);
        
        // Item should still be valid
        $this->assertTrue($this->cache->has('key1'));
        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function testSetDefaultTtl(): void
    {
        $this->cache->setDefaultTtl(600);
        
        // Set without explicit TTL should use default
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
    }

    public function testSetDefaultTtlInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be greater than 0');
        
        $this->cache->setDefaultTtl(0);
    }

    public function testSetDefaultTtlNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be greater than 0');
        
        $this->cache->setDefaultTtl(-1);
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], $result);
    }

    public function testGetMultipleWithMissingKeys(): void
    {
        $this->cache->set('key1', 'value1');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'default',
            'key3' => 'default',
        ], $result);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertTrue($this->cache->setMultiple($values));

        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals('value3', $this->cache->get('key3'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->assertTrue($this->cache->setMultiple($values, 1));

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        sleep(2);

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGenerateKey(): void
    {
        $key1 = CachePool::generateKey('getBalance', ['address' => 'abc123']);
        $key2 = CachePool::generateKey('getBalance', ['address' => 'abc123']);

        // Same params should generate same key
        $this->assertEquals($key1, $key2);
    }

    public function testGenerateKeyDifferentParams(): void
    {
        $key1 = CachePool::generateKey('getBalance', ['address' => 'abc123']);
        $key2 = CachePool::generateKey('getBalance', ['address' => 'xyz789']);

        // Different params should generate different keys
        $this->assertNotEquals($key1, $key2);
    }

    public function testGenerateKeyFormat(): void
    {
        $key = CachePool::generateKey('getBalance', ['address' => 'abc123']);

        // Key should have the format "blockchain:method:hash"
        $this->assertStringStartsWith('blockchain:getBalance:', $key);
    }

    public function testStoreComplexData(): void
    {
        $complexData = [
            'user' => 'john_doe',
            'transactions' => [
                ['id' => 1, 'amount' => 100],
                ['id' => 2, 'amount' => 200],
            ],
            'metadata' => [
                'timestamp' => time(),
                'source' => 'blockchain',
            ],
        ];

        $this->cache->set('complex_key', $complexData);
        $retrieved = $this->cache->get('complex_key');

        $this->assertEquals($complexData, $retrieved);
    }

    public function testStoreNullValue(): void
    {
        $this->cache->set('null_key', null);
        
        // has() should return true even for null values
        $this->assertTrue($this->cache->has('null_key'));
        
        // get() should return the null value
        $this->assertNull($this->cache->get('null_key'));
    }

    public function testStoreBooleanValues(): void
    {
        $this->cache->set('true_key', true);
        $this->cache->set('false_key', false);

        $this->assertTrue($this->cache->get('true_key'));
        $this->assertFalse($this->cache->get('false_key'));
    }
}
