<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Blockchain\BlockchainManager;
use Blockchain\Registry\DriverRegistry;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\UnsupportedDriverException;

class BlockchainManagerTest extends TestCase
{
    private BlockchainManager $manager;

    protected function setUp(): void
    {
        $this->manager = new BlockchainManager();
    }

    public function testConstructorWithoutRegistry(): void
    {
        $manager = new BlockchainManager();
        
        $this->assertInstanceOf(BlockchainManager::class, $manager);
        $this->assertInstanceOf(DriverRegistry::class, $manager->getDriverRegistry());
    }

    public function testConstructorWithRegistry(): void
    {
        $registry = new DriverRegistry();
        $manager = new BlockchainManager($registry);
        
        $this->assertSame($registry, $manager->getDriverRegistry());
    }

    public function testImplementsBlockchainDriverInterface(): void
    {
        $this->assertInstanceOf(BlockchainDriverInterface::class, $this->manager);
    }

    public function testSetDriverWithValidDriver(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];
        
        $result = $this->manager->setDriver('solana', $config);
        
        // Test fluent interface
        $this->assertSame($this->manager, $result);
    }

    public function testSetDriverThrowsExceptionForInvalidDriver(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage("Driver 'invalid_driver' is not supported.");
        
        $this->manager->setDriver('invalid_driver', ['endpoint' => 'test']);
    }

    public function testSwitchDriverSuccess(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];
        
        // Load the driver first
        $this->manager->setDriver('solana', $config);
        
        // Switch should work
        $result = $this->manager->switchDriver('solana');
        
        // Test fluent interface
        $this->assertSame($this->manager, $result);
    }

    public function testSwitchDriverThrowsExceptionForUnloadedDriver(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Driver 'solana' has not been loaded. Use setDriver() first.");
        
        $this->manager->switchDriver('solana');
    }

    public function testSwitchDriverBetweenMultipleDrivers(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];
        
        // Load solana driver
        $this->manager->setDriver('solana', $config);
        
        // Load it again (should replace in drivers array but with same driver)
        $this->manager->setDriver('solana', $config);
        
        // Switch back
        $result = $this->manager->switchDriver('solana');
        
        $this->assertSame($this->manager, $result);
    }

    public function testGetSupportedDrivers(): void
    {
        $drivers = $this->manager->getSupportedDrivers();
        
        $this->assertIsArray($drivers);
        $this->assertContains('solana', $drivers);
    }

    public function testGetBalanceThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->getBalance('some_address');
    }

    public function testSendTransactionThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->sendTransaction('from', 'to', 1.0);
    }

    public function testGetTransactionThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->getTransaction('tx_hash');
    }

    public function testGetBlockThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->getBlock(12345);
    }

    public function testEstimateGasThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->estimateGas('from', 'to', 1.0);
    }

    public function testGetTokenBalanceThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->getTokenBalance('address', 'token_address');
    }

    public function testGetNetworkInfoThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->getNetworkInfo();
    }

    public function testConnectThrowsExceptionWhenNoDriverSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No blockchain driver is configured. Please set a driver first.');
        
        $this->manager->connect(['endpoint' => 'test']);
    }

    public function testFluentInterface(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];
        
        // Test method chaining
        $result = $this->manager
            ->setDriver('solana', $config)
            ->switchDriver('solana');
        
        $this->assertSame($this->manager, $result);
    }

    public function testGetDriverRegistry(): void
    {
        $registry = $this->manager->getDriverRegistry();
        
        $this->assertInstanceOf(DriverRegistry::class, $registry);
    }

    public function testBackwardCompatibleConstructorWithDriverName(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30
        ];
        
        // Old pattern: new BlockchainManager('driver_name', $config)
        $manager = new BlockchainManager('solana', $config);
        
        $this->assertInstanceOf(BlockchainManager::class, $manager);
        $this->assertInstanceOf(DriverRegistry::class, $manager->getDriverRegistry());
    }

    public function testBackwardCompatibleConstructorWithInvalidDriver(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage("Driver 'invalid_driver' is not supported.");
        
        // Should throw exception for invalid driver
        new BlockchainManager('invalid_driver', ['endpoint' => 'test']);
    }

    public function testSetDriverValidatesConfigWithConfigLoader(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
        ];
        
        // Valid config should work
        $result = $this->manager->setDriver('solana', $config);
        
        $this->assertSame($this->manager, $result);
    }

    public function testSetDriverThrowsValidationExceptionForInvalidConfig(): void
    {
        $config = [
            'rpc_url' => 'not-a-valid-url', // Invalid URL
        ];
        
        $this->expectException(\Blockchain\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('must be a valid URL');
        
        $this->manager->setDriver('solana', $config);
    }

    public function testSetDriverThrowsValidationExceptionForMissingRequiredField(): void
    {
        $config = [
            'timeout' => 30, // Missing required rpc_url or endpoint
        ];
        
        $this->expectException(\Blockchain\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("One of the following fields is required");
        
        $this->manager->setDriver('solana', $config);
    }
}
