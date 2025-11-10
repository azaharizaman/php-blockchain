<?php

declare(strict_types=1);

namespace Blockchain;

use Blockchain\Config\ConfigLoader;
use Blockchain\Config\NetworkProfiles;
use Blockchain\Registry\DriverRegistry;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\UnsupportedDriverException;

/**
 * BlockchainManager constructor.
 *
 * Supports multiple initialization patterns for backward compatibility:
 * - New pattern: new BlockchainManager($registry) or new BlockchainManager()
 * - Old pattern: new BlockchainManager('driver_name', $config)
 *
 * @param string|DriverRegistry|null $driverNameOrRegistry Driver name (string) for backward compatibility, or DriverRegistry instance, or null
 * @param array<string,mixed> $config Driver configuration (only used with driver name pattern)
 * @throws UnsupportedDriverException If driver name is invalid
 */

class BlockchainManager implements BlockchainDriverInterface
{
    private ?BlockchainDriverInterface $currentDriver = null;
    
    /**
     * @var array<string, BlockchainDriverInterface>
     */
    private array $drivers = [];
    
    private DriverRegistry $registry;

    public function __construct(?string $driverName = null, array $config = [])
    {
        // Handle different constructor patterns
        if ($driverName instanceof DriverRegistry) {
            // New pattern: DriverRegistry provided
            $this->registry = $driverName;
        } elseif (is_string($driverName)) {
            // Old pattern: driver name provided for backward compatibility
            $this->registry = new DriverRegistry();
            $this->setDriver($driverName, $config);
        } else {
            // No arguments or null provided
            $this->registry = new DriverRegistry();
        }
    }

    /**
     * Set and configure a blockchain driver.
     *
     * Supports two modes:
     * 1. Direct configuration: Pass driver name and config array
     * 2. Profile-based: Pass profile name (e.g., 'solana.mainnet') as driver name with empty config
     *
     * @param string $driverNameOrProfile Driver name or network profile (e.g., 'solana', 'ethereum.mainnet')
     * @param array<string,mixed> $config Driver configuration (optional if using profile)
     * @return self For fluent interface
     * @throws UnsupportedDriverException If driver is not registered
     * @throws \Blockchain\Exceptions\ValidationException If configuration is invalid
     * @throws \InvalidArgumentException If profile is not found
     */
    public function setDriver(string $driverNameOrProfile, array $config = []): self
    {
        // Check if this is a profile name (contains a dot)
        if (empty($config) && NetworkProfiles::has($driverNameOrProfile)) {
            // Load configuration from profile
            $profileConfig = NetworkProfiles::get($driverNameOrProfile);
            $driverName = $profileConfig['driver'];
            $config = $profileConfig;
        } else {
            $driverName = $driverNameOrProfile;
        }

        if (!$this->registry->hasDriver($driverName)) {
            throw new UnsupportedDriverException("Driver '{$driverName}' is not supported.");
        }

        // Validate configuration before using it
        ConfigLoader::validateConfig($config, $driverName);

        if (isset($this->drivers[$driverName])) {
            // Driver already loaded, just switch to it
            $this->currentDriver = $this->drivers[$driverName];
            return $this;
        }

        $driverClass = $this->registry->getDriver($driverName);
        $driver = new $driverClass();
        $driver->connect($config);

        $this->drivers[$driverName] = $driver;
        $this->currentDriver = $driver;
        
        return $this;
    }

    /**
     * Set driver using a network profile.
     *
     * Convenience method for setting up a driver using a pre-configured network profile.
     * Profile names follow the pattern 'driver.network' (e.g., 'solana.mainnet', 'ethereum.goerli').
     *
     * @param string $profileName Network profile name (e.g., 'ethereum.mainnet', 'solana.devnet')
     * @return self For fluent interface
     * @throws \InvalidArgumentException If profile is not found
     * @throws UnsupportedDriverException If driver is not registered
     * @throws \Blockchain\Exceptions\ValidationException If configuration is invalid
     */
    public function setDriverByProfile(string $profileName): self
    {
        $profileConfig = NetworkProfiles::get($profileName);
        $driverName = $profileConfig['driver'];

        return $this->setDriver($driverName, $profileConfig);
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
    /**
     * @param array<string,mixed> $options
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
    /**
     * @return array<string,mixed>
     */
    public function getTransaction(string $txHash): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getTransaction($txHash);
    }

    /**
     * Get block information.
     *
     * @param int|string $blockIdentifier Block number or hash
     * @return array<string,mixed> Block information
     * @throws ConfigurationException If no driver is set
     */
    /**
     * @return array<string,mixed>
     */
    public function getBlock(int|string $blockNumber): array
    {
        $this->ensureDriverIsSet();
        return $this->currentDriver->getBlock($blockNumber);
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
    /**
     * @param array<string,mixed> $options
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
    /**
     * @return array<string,mixed>|null
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
    /**
     * @return string[]
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
