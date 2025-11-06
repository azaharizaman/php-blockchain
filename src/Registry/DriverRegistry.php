<?php

declare(strict_types=1);

namespace Blockchain\Registry;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\UnsupportedDriverException;
use Blockchain\Exceptions\ValidationException;

class DriverRegistry
{
    /**
     * @var array<string,class-string<BlockchainDriverInterface>>
     */
    private array $drivers = [];

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register a blockchain driver.
     *
     * @param string $name Driver name
     * @param class-string<BlockchainDriverInterface> $driverClass Fully qualified class name
     * @return void
     * @throws ValidationException If class doesn't exist or doesn't implement BlockchainDriverInterface
     */
    public function registerDriver(string $name, string $driverClass): void
    {
        if (!class_exists($driverClass)) {
            throw new ValidationException("Driver class '{$driverClass}' does not exist.");
        }

        if (!is_subclass_of($driverClass, BlockchainDriverInterface::class)) {
            throw new ValidationException("Driver class '{$driverClass}' must implement BlockchainDriverInterface.");
        }

        $this->drivers[strtolower($name)] = $driverClass;
    }

    /**
     * Get a driver class name by name.
     *
     * @param string $name Driver name
     * @return class-string<BlockchainDriverInterface> Driver class name
     * @throws UnsupportedDriverException If driver is not registered
     */
    public function getDriver(string $name): string
    {
        $name = strtolower($name);

        if (!$this->hasDriver($name)) {
            throw new UnsupportedDriverException("Driver '{$name}' is not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Check if a driver is registered.
     *
     * @param string $name Driver name
     * @return bool True if driver is registered, false otherwise
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[strtolower($name)]);
    }

    /**
     * Get all registered driver names.
     *
     * @return array<int,string> List of registered driver names
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Register default blockchain drivers.
     *
     * Registers the Solana driver as the default driver.
     * This method is idempotent - safe to call multiple times.
     *
     * Note: This method is public primarily for testing purposes and typically does not need to be called directly,
     * as it is invoked automatically in the constructor.
     *
     * @return void
     */
    public function registerDefaultDrivers(): void
    {
        // Register Solana driver
        $this->registerDriver('solana', \Blockchain\Drivers\SolanaDriver::class);

        // Additional drivers can be registered here as they are implemented
        // $this->registerDriver('ethereum', \Blockchain\Drivers\EthereumDriver::class);
        // $this->registerDriver('polygon', \Blockchain\Drivers\PolygonDriver::class);
    }
}
