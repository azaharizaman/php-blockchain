<?php

declare(strict_types=1);

namespace Blockchain;

use Blockchain\Registry\DriverRegistry;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\UnsupportedDriverException;

class BlockchainManager
{
    private DriverRegistry $driverRegistry;
    private ?BlockchainDriverInterface $currentDriver = null;

    public function __construct(?string $driverName = null, array $config = [])
    {
        $this->driverRegistry = new DriverRegistry();
        
        if ($driverName) {
            $this->setDriver($driverName, $config);
        }
    }

    /**
     * Set the active blockchain driver.
     */
    public function setDriver(string $driverName, array $config = []): void
    {
        if (!$this->driverRegistry->hasDriver($driverName)) {
            throw new UnsupportedDriverException("Driver '{$driverName}' is not supported.");
        }

        $this->currentDriver = $this->driverRegistry->getDriver($driverName);
        
        if (empty($config)) {
            throw new ConfigurationException("Configuration is required for blockchain driver.");
        }
        
        $this->currentDriver->connect($config);
    }

    /**
     * Get the balance of an address.
     */
    public function getBalance(string $address): float
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getBalance($address);
    }

    /**
     * Send a transaction.
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->sendTransaction($from, $to, $amount, $options);
    }

    /**
     * Get transaction details.
     */
    public function getTransaction(string $txHash): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getTransaction($txHash);
    }

    /**
     * Get block information.
     */
    public function getBlock(int|string $blockNumber): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getBlock($blockNumber);
    }

    /**
     * Estimate gas for a transaction.
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->estimateGas($from, $to, $amount, $options);
    }

    /**
     * Get token balance.
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getTokenBalance($address, $tokenAddress);
    }

    /**
     * Get network information.
     */
    public function getNetworkInfo(): ?array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getNetworkInfo();
    }

    /**
     * Get the driver registry instance.
     */
    public function getDriverRegistry(): DriverRegistry
    {
        return $this->driverRegistry;
    }

    /**
     * Get list of supported drivers.
     */
    public function getSupportedDrivers(): array
    {
        return $this->driverRegistry->getRegisteredDrivers();
    }

    /**
     * Ensure a driver is set before performing operations.
     */
    private function ensureDriverIsSet(): void
    {
        if ($this->currentDriver === null) {
            throw new ConfigurationException("No blockchain driver is configured. Please set a driver first.");
        }
    }
}