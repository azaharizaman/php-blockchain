<?php

declare(strict_types=1);

namespace Blockchain\Tests\Registry;

use PHPUnit\Framework\TestCase;
use Blockchain\Registry\DriverRegistry;
use Blockchain\Drivers\SolanaDriver;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\ValidationException;
use Blockchain\Exceptions\UnsupportedDriverException;

class DriverRegistryTest extends TestCase
{
    private DriverRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new DriverRegistry();
    }

    public function testConstructorRegistersDefaultDrivers(): void
    {
        $drivers = $this->registry->getRegisteredDrivers();
        
        $this->assertContains('solana', $drivers);
    }

    public function testRegisterDriverAddsDriverSuccessfully(): void
    {
        $this->registry->registerDriver('test', SolanaDriver::class);
        
        $this->assertTrue($this->registry->hasDriver('test'));
    }

    public function testRegisterDriverThrowsExceptionForNonExistentClass(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Driver class 'NonExistentClass' does not exist.");
        
        $this->registry->registerDriver('invalid', 'NonExistentClass');
    }

    public function testRegisterDriverThrowsExceptionForNonInterfaceClass(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Driver class 'stdClass' must implement BlockchainDriverInterface.");
        
        $this->registry->registerDriver('invalid', \stdClass::class);
    }

    public function testRegisterDriverIsIdempotent(): void
    {
        $this->registry->registerDriver('solana', SolanaDriver::class);
        $this->registry->registerDriver('solana', SolanaDriver::class);
        
        $drivers = $this->registry->getRegisteredDrivers();
        $solanaCount = count(array_filter($drivers, fn($d) => $d === 'solana'));
        
        $this->assertEquals(1, $solanaCount, 'Driver should only be registered once');
    }

    public function testRegisterDriverNormalizesNameToLowercase(): void
    {
        $this->registry->registerDriver('TestDriver', SolanaDriver::class);
        
        $this->assertTrue($this->registry->hasDriver('testdriver'));
        $this->assertTrue($this->registry->hasDriver('TestDriver'));
        $this->assertTrue($this->registry->hasDriver('TESTDRIVER'));
    }

    public function testGetDriverReturnsCorrectClassName(): void
    {
        $className = $this->registry->getDriver('solana');
        
        $this->assertEquals(SolanaDriver::class, $className);
        $this->assertIsString($className);
    }

    public function testGetDriverThrowsExceptionForUnknownDriver(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage("Driver 'unknown' is not registered.");
        
        $this->registry->getDriver('unknown');
    }

    public function testGetDriverNormalizesNameToLowercase(): void
    {
        $className1 = $this->registry->getDriver('solana');
        $className2 = $this->registry->getDriver('Solana');
        $className3 = $this->registry->getDriver('SOLANA');
        
        $this->assertEquals($className1, $className2);
        $this->assertEquals($className2, $className3);
    }

    public function testHasDriverReturnsTrueForRegisteredDriver(): void
    {
        $this->assertTrue($this->registry->hasDriver('solana'));
    }

    public function testHasDriverReturnsFalseForUnregisteredDriver(): void
    {
        $this->assertFalse($this->registry->hasDriver('ethereum'));
        $this->assertFalse($this->registry->hasDriver('bitcoin'));
    }

    public function testHasDriverIsCaseInsensitive(): void
    {
        $this->assertTrue($this->registry->hasDriver('solana'));
        $this->assertTrue($this->registry->hasDriver('Solana'));
        $this->assertTrue($this->registry->hasDriver('SOLANA'));
        $this->assertTrue($this->registry->hasDriver('SoLaNa'));
    }

    public function testGetRegisteredDriversReturnsArrayOfNames(): void
    {
        $drivers = $this->registry->getRegisteredDrivers();
        
        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);
        $this->assertContains('solana', $drivers);
    }

    public function testGetRegisteredDriversReturnsAllDrivers(): void
    {
        $this->registry->registerDriver('test1', SolanaDriver::class);
        $this->registry->registerDriver('test2', SolanaDriver::class);
        
        $drivers = $this->registry->getRegisteredDrivers();
        
        $this->assertContains('solana', $drivers);
        $this->assertContains('test1', $drivers);
        $this->assertContains('test2', $drivers);
        $this->assertGreaterThanOrEqual(3, count($drivers));
    }

    public function testRegisterDefaultDriversRegistersSolana(): void
    {
        // Create a new registry without auto-registration
        $registry = new class extends DriverRegistry {
            public function __construct()
            {
                // Don't call parent constructor to avoid auto-registration
            }
            
            public function callRegisterDefaultDrivers(): void
            {
                $this->registerDefaultDrivers();
            }
        };
        
        // Initially no drivers
        $this->assertFalse($registry->hasDriver('solana'));
        
        // Register defaults
        $registry->callRegisterDefaultDrivers();
        
        // Now solana should be registered
        $this->assertTrue($registry->hasDriver('solana'));
    }

    public function testRegisterDefaultDriversIsIdempotent(): void
    {
        // Create a new registry
        $registry = new class extends DriverRegistry {
            public function __construct()
            {
                // Don't call parent constructor
            }
            
            public function callRegisterDefaultDrivers(): void
            {
                $this->registerDefaultDrivers();
            }
        };
        
        // Call multiple times
        $registry->callRegisterDefaultDrivers();
        $registry->callRegisterDefaultDrivers();
        $registry->callRegisterDefaultDrivers();
        
        $drivers = $registry->getRegisteredDrivers();
        $solanaCount = count(array_filter($drivers, fn($d) => $d === 'solana'));
        
        $this->assertEquals(1, $solanaCount, 'Solana should only be registered once');
    }

    public function testGetRegisteredDriversReturnsNumericallyIndexedArray(): void
    {
        $drivers = $this->registry->getRegisteredDrivers();
        
        $this->assertIsArray($drivers);
        
        // Verify it's a numerically indexed array (array_keys returns [0, 1, 2, ...])
        foreach (array_keys($drivers) as $index => $key) {
            $this->assertEquals($index, $key, 'Array should be numerically indexed');
        }
    }

    public function testDriverClassCanBeInstantiated(): void
    {
        $className = $this->registry->getDriver('solana');
        
        // Verify the class can be instantiated
        $this->assertTrue(class_exists($className));
        
        $driver = new $className();
        $this->assertInstanceOf(BlockchainDriverInterface::class, $driver);
    }

    public function testMultipleDriversCanBeRegisteredAndRetrieved(): void
    {
        // Register multiple test drivers
        $this->registry->registerDriver('driver1', SolanaDriver::class);
        $this->registry->registerDriver('driver2', SolanaDriver::class);
        $this->registry->registerDriver('driver3', SolanaDriver::class);
        
        // All should be retrievable
        $this->assertEquals(SolanaDriver::class, $this->registry->getDriver('driver1'));
        $this->assertEquals(SolanaDriver::class, $this->registry->getDriver('driver2'));
        $this->assertEquals(SolanaDriver::class, $this->registry->getDriver('driver3'));
        
        // All should be in the list
        $drivers = $this->registry->getRegisteredDrivers();
        $this->assertContains('driver1', $drivers);
        $this->assertContains('driver2', $drivers);
        $this->assertContains('driver3', $drivers);
    }
}
