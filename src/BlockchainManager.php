<?php

declare(strict_types=1);

namespace Blockchain;

use Blockchain\Registry\DriverRegistry;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\UnsupportedDriverException;

/**
 * BlockchainManager orchestrates driver lifecycle and provides a unified API.
 *
 * This class manages multiple blockchain drivers, allowing for driver switching
 * and providing a consistent interface for blockchain operations across
 * different blockchain networks.
 */
class BlockchainManager implements BlockchainDriverInterface
{
    private ?BlockchainDriverInterface $currentDriver = null;
    
    /**
     * @var array<string, BlockchainDriverInterface>
     */
    private array $drivers = [];
    
    private DriverRegistry $registry;

    public function __construct(?DriverRegistry $registry = null)
    {
        $this->registry = $registry ?? new DriverRegistry();
    }

    /**
     * Set and configure a blockchain driver.
     *
     * @param string $name Driver name to set
     * @param array<string,mixed> $config Driver configuration
     * @return self For fluent interface
     * @throws UnsupportedDriverException If driver is not registered
     */
    public function setDriver(string $name, array $config): self
    {
        if (!$this->registry->hasDriver($name)) {
            throw new UnsupportedDriverException("Driver '{$name}' is not supported.");
        }

        $driver = $this->registry->getDriver($name);
        $driver->connect($config);
        
        $this->drivers[$name] = $driver;
        $this->currentDriver = $driver;
        
        return $this;
    }

    /**
     * Switch to a previously loaded driver.
     *
     * @param string $name Driver name to switch to
     * @return self For fluent interface
     * @throws ConfigurationException If driver not previously loaded
     */
    public function switchDriver(string $name): self
    {
        if (!isset($this->drivers[$name])) {
            throw new ConfigurationException("Driver '{$name}' has not been loaded. Use setDriver() first.");
        }
        
        $this->currentDriver = $this->drivers[$name];
        
        return $this;
    }

    /**
     * Connect to the blockchain network with the given configuration.
     *
     * @param array<string,mixed> $config Configuration parameters
     * @throws ConfigurationException If no driver is set
     * @return void
     */
    public function connect(array $config): void
    {
        $this->ensureDriverIsSet();
        $this->currentDriver->connect($config);
    }

    /**
     * Get the balance of an address.
     *
     * @param string $address The blockchain address to query
     * @return float The balance in native token units
     * @throws ConfigurationException If no driver is set
     */
    public function getBalance(string $address): float
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getBalance($address);
    }

    /**
     * Send a transaction.
     *
     * @param string $from Sender's address
     * @param string $to Recipient's address
     * @param float $amount Amount to transfer
     * @param array<string,mixed> $options Additional options
     * @return string Transaction hash
     * @throws ConfigurationException If no driver is set
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->sendTransaction($from, $to, $amount, $options);
    }

    /**
     * Get transaction details.
     *
     * @param string $hash Transaction hash
     * @return array<string,mixed> Transaction details
     * @throws ConfigurationException If no driver is set
     */
    public function getTransaction(string $hash): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getTransaction($hash);
    }

    /**
     * Get block information.
     *
     * @param int|string $blockIdentifier Block number or hash
     * @return array<string,mixed> Block information
     * @throws ConfigurationException If no driver is set
     */
    public function getBlock(int|string $blockIdentifier): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getBlock($blockIdentifier);
    }

    /**
     * Estimate gas for a transaction.
     *
     * @param string $from Sender's address
     * @param string $to Recipient's address
     * @param float $amount Amount to transfer
     * @param array<string,mixed> $options Additional options
     * @return int|null Estimated gas units
     * @throws ConfigurationException If no driver is set
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->estimateGas($from, $to, $amount, $options);
    }

    /**
     * Get token balance.
     *
     * @param string $address Wallet address
     * @param string $tokenAddress Token contract address
     * @return float|null Token balance
     * @throws ConfigurationException If no driver is set
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getTokenBalance($address, $tokenAddress);
    }

    /**
     * Get network information.
     *
     * @return array<string,mixed>|null Network information
     * @throws ConfigurationException If no driver is set
     */
    public function getNetworkInfo(): ?array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getNetworkInfo();
    }

    /**
     * Get the driver registry instance.
     *
     * @return DriverRegistry Registry instance
     */
    public function getDriverRegistry(): DriverRegistry
    {
        return $this->registry;
    }

    /**
     * Get list of supported drivers.
     *
     * @return array<int,string> List of registered driver names
     */
    public function getSupportedDrivers(): array
    {
        return $this->registry->getRegisteredDrivers();
    }

    /**
     * Ensure a driver is set before performing operations.
     *
     * @throws ConfigurationException If no driver is set
     */
    private function ensureDriverIsSet(): void
    {
        if ($this->currentDriver === null) {
            throw new ConfigurationException("No blockchain driver is configured. Please set a driver first.");
        }
    }
}
