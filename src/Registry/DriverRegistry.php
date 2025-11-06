<?php

declare(strict_types=1);

namespace Blockchain\Registry;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\UnsupportedDriverException;

class DriverRegistry
{
    private array $drivers = [];

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register a blockchain driver.
     */
    public function registerDriver(string $name, string $driverClass): void
    {
        if (!class_exists($driverClass)) {
            throw new UnsupportedDriverException("Driver class '{$driverClass}' does not exist.");
        }

        if (!is_subclass_of($driverClass, BlockchainDriverInterface::class)) {
            throw new UnsupportedDriverException("Driver class '{$driverClass}' must implement BlockchainDriverInterface.");
        }

        $this->drivers[strtolower($name)] = $driverClass;
    }

    /**
     * Get a driver instance by name.
     */
    public function getDriver(string $name): BlockchainDriverInterface
    {
        $name = strtolower($name);
        
        if (!$this->hasDriver($name)) {
            throw new UnsupportedDriverException("Driver '{$name}' is not registered.");
        }

        $driverClass = $this->drivers[$name];
        return new $driverClass();
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[strtolower($name)]);
    }

    /**
     * Get all registered driver names.
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Register default blockchain drivers.
     */
    private function registerDefaultDrivers(): void
    {
        // Register Solana driver
        $this->registerDriver('solana', \Blockchain\Drivers\SolanaDriver::class);
        
        // Additional drivers can be registered here as they are implemented
        // $this->registerDriver('ethereum', \Blockchain\Drivers\EthereumDriver::class);
        // $this->registerDriver('polygon', \Blockchain\Drivers\PolygonDriver::class);
    }
}