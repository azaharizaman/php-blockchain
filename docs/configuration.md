# Configuration Guide

This guide explains how to configure and use the PHP Blockchain Integration Layer with various blockchain networks.

## Table of Contents

- [Overview](#overview)
- [Network Profiles](#network-profiles)
- [Using Profiles with BlockchainManager](#using-profiles-with-blockchainmanager)
- [Environment Variables](#environment-variables)
- [Manual Configuration](#manual-configuration)
- [Available Network Profiles](#available-network-profiles)

## Overview

The PHP Blockchain Integration Layer supports two configuration approaches:

1. **Network Profiles** - Pre-configured profiles for quick setup
2. **Manual Configuration** - Custom configuration arrays for advanced use cases

Network profiles provide a convenient way to connect to common blockchain networks without manually specifying endpoints and parameters.

## Network Profiles

Network profiles are pre-configured templates that map logical network names to complete driver configurations. They simplify the process of connecting to various blockchain networks.

### Profile Naming Convention

Profiles follow the pattern: `{blockchain}.{network}`

Examples:
- `solana.mainnet` - Solana mainnet
- `ethereum.goerli` - Ethereum Goerli testnet
- `ethereum.localhost` - Local Ethereum development node

## Using Profiles with BlockchainManager

### Method 1: Using setDriver() with Profile Name

When you pass only a profile name (no config array), `setDriver()` automatically resolves it:

```php
use Blockchain\BlockchainManager;

// Create manager
$manager = new BlockchainManager();

// Set driver using profile name (automatic resolution)
$manager->setDriver('solana.mainnet');

// Use the driver
$balance = $manager->getBalance('YourSolanaAddressHere');
```

### Method 2: Using setDriverByProfile()

The explicit profile method provides better clarity:

```php
use Blockchain\BlockchainManager;

$manager = new BlockchainManager();

// Explicitly set driver from profile
$manager->setDriverByProfile('ethereum.mainnet');

// Use the driver
$balance = $manager->getBalance('0xYourEthereumAddress');
```

### Method 3: Direct Profile Access

You can also retrieve profile configurations directly:

```php
use Blockchain\Config\NetworkProfiles;
use Blockchain\BlockchainManager;

// Get profile configuration
$config = NetworkProfiles::get('solana.devnet');

// Use with BlockchainManager
$manager = new BlockchainManager();
$manager->setDriver('solana', $config);
```

### Checking Profile Availability

```php
use Blockchain\Config\NetworkProfiles;

// Check if a profile exists
if (NetworkProfiles::has('ethereum.sepolia')) {
    $config = NetworkProfiles::get('ethereum.sepolia');
}

// List all available profiles
$profiles = NetworkProfiles::all();
print_r($profiles);
```

## Environment Variables

Some network profiles require API keys for third-party RPC providers. These are specified using environment variable placeholders.

### Setting Environment Variables

#### In .env file:
```env
INFURA_API_KEY=your_infura_api_key_here
ALCHEMY_API_KEY=your_alchemy_api_key_here
```

#### In PHP code:
```php
// Set environment variables before using profiles
putenv('INFURA_API_KEY=your_infura_api_key_here');
$_ENV['INFURA_API_KEY'] = 'your_infura_api_key_here';

// Now use the profile
$manager->setDriverByProfile('ethereum.mainnet');
```

#### In server configuration (Apache/Nginx):
```apache
# Apache
SetEnv INFURA_API_KEY your_infura_api_key_here
```

```nginx
# Nginx
fastcgi_param INFURA_API_KEY your_infura_api_key_here;
```

### How Interpolation Works

Network profiles use `${VARIABLE_NAME}` syntax for environment variable placeholders:

```php
// Profile definition (internal)
'endpoint' => 'https://mainnet.infura.io/v3/${INFURA_API_KEY}'

// After interpolation (with INFURA_API_KEY=abc123)
'endpoint' => 'https://mainnet.infura.io/v3/abc123'
```

If an environment variable is not set, the placeholder remains unchanged. This allows for graceful degradation or explicit error handling.

## Manual Configuration

For advanced use cases or custom networks, you can provide manual configuration:

```php
use Blockchain\BlockchainManager;

$manager = new BlockchainManager();

// Custom Solana configuration
$manager->setDriver('solana', [
    'endpoint' => 'https://your-custom-solana-node.com',
    'timeout' => 60,
    'commitment' => 'confirmed',
]);

// Custom Ethereum configuration
$manager->setDriver('ethereum', [
    'endpoint' => 'https://your-custom-ethereum-node.com',
    'chainId' => '0x1',
    'timeout' => 45,
]);
```

## Available Network Profiles

### Solana Networks

#### solana.mainnet
- **Description**: Solana mainnet (production)
- **Endpoint**: `https://api.mainnet-beta.solana.com`
- **Commitment**: finalized
- **Use Case**: Production applications

```php
$manager->setDriverByProfile('solana.mainnet');
```

#### solana.devnet
- **Description**: Solana devnet (development)
- **Endpoint**: `https://api.devnet.solana.com`
- **Commitment**: finalized
- **Use Case**: Development and testing

```php
$manager->setDriverByProfile('solana.devnet');
```

#### solana.testnet
- **Description**: Solana testnet
- **Endpoint**: `https://api.testnet.solana.com`
- **Commitment**: finalized
- **Use Case**: Testing before mainnet deployment

```php
$manager->setDriverByProfile('solana.testnet');
```

### Ethereum Networks

#### ethereum.mainnet
- **Description**: Ethereum mainnet (production)
- **Endpoint**: `https://mainnet.infura.io/v3/${INFURA_API_KEY}`
- **Chain ID**: 0x1 (1)
- **Requirements**: INFURA_API_KEY environment variable
- **Use Case**: Production applications

```php
// Set your API key first
putenv('INFURA_API_KEY=your_api_key_here');

$manager->setDriverByProfile('ethereum.mainnet');
```

#### ethereum.goerli
- **Description**: Ethereum Goerli testnet
- **Endpoint**: `https://goerli.infura.io/v3/${INFURA_API_KEY}`
- **Chain ID**: 0x5 (5)
- **Requirements**: INFURA_API_KEY environment variable
- **Use Case**: Testing with Goerli testnet
- **Note**: Goerli is being phased out, consider using Sepolia

```php
putenv('INFURA_API_KEY=your_api_key_here');
$manager->setDriverByProfile('ethereum.goerli');
```

#### ethereum.sepolia
- **Description**: Ethereum Sepolia testnet
- **Endpoint**: `https://sepolia.infura.io/v3/${INFURA_API_KEY}`
- **Chain ID**: 0xaa36a7 (11155111)
- **Requirements**: INFURA_API_KEY environment variable
- **Use Case**: Testing with Sepolia testnet (recommended testnet)

```php
putenv('INFURA_API_KEY=your_api_key_here');
$manager->setDriverByProfile('ethereum.sepolia');
```

#### ethereum.localhost
- **Description**: Local Ethereum development node
- **Endpoint**: `http://localhost:8545`
- **Chain ID**: 0x539 (1337)
- **Requirements**: None (local node must be running)
- **Use Case**: Local development with Hardhat, Ganache, or Anvil

```php
// No API key needed for localhost
$manager->setDriverByProfile('ethereum.localhost');
```

## Configuration Validation

All configurations (both profiles and manual) are validated before use:

```php
use Blockchain\Config\ConfigLoader;

// Manual validation
$config = [
    'endpoint' => 'https://api.mainnet-beta.solana.com',
    'timeout' => 30,
];

// Validate against driver schema
ConfigLoader::validateConfig($config, 'solana');
```

Validation ensures:
- Required fields are present
- Field types are correct
- URLs are valid
- Values are within acceptable ranges

## Error Handling

```php
use Blockchain\Config\NetworkProfiles;

try {
    // Attempt to load a profile
    $config = NetworkProfiles::get('invalid.profile');
} catch (\InvalidArgumentException $e) {
    // Profile not found
    echo "Error: " . $e->getMessage();
    
    // List available profiles
    $available = NetworkProfiles::all();
    echo "Available profiles: " . implode(', ', $available);
}
```

## Best Practices

1. **Use Profiles for Standard Networks**: Leverage built-in profiles for common networks
2. **Store API Keys Securely**: Never hardcode API keys; use environment variables
3. **Validate Custom Configurations**: Always validate manual configurations
4. **Use Localhost for Development**: Start with local nodes before testing on public networks
5. **Check Profile Existence**: Use `NetworkProfiles::has()` before accessing profiles

## Examples

### Complete Example: Switching Between Networks

```php
use Blockchain\BlockchainManager;

// Initialize manager
$manager = new BlockchainManager();

// Development: Use testnet
$manager->setDriverByProfile('solana.devnet');
$devBalance = $manager->getBalance('DevnetAddressHere');

// Production: Switch to mainnet
$manager->setDriverByProfile('solana.mainnet');
$prodBalance = $manager->getBalance('MainnetAddressHere');
```

### Multi-Chain Application

```php
use Blockchain\BlockchainManager;

$manager = new BlockchainManager();

// Query Solana balance
$manager->setDriverByProfile('solana.mainnet');
$solBalance = $manager->getBalance('SolanaAddress');

// Query Ethereum balance
$manager->setDriverByProfile('ethereum.mainnet');
$ethBalance = $manager->getBalance('0xEthereumAddress');
```

### Custom Configuration with Fallback

```php
use Blockchain\Config\NetworkProfiles;
use Blockchain\BlockchainManager;

$manager = new BlockchainManager();

// Try custom configuration first
if ($customEndpoint) {
    $manager->setDriver('solana', [
        'endpoint' => $customEndpoint,
        'timeout' => 60,
    ]);
} else {
    // Fallback to profile
    $manager->setDriverByProfile('solana.mainnet');
}
```

## Related Documentation

- [Driver Documentation](drivers/README.md)
- [Logging and Audit](LOGGING-AND-AUDIT.md)
- [Telemetry Guide](telemetry.md)
- [Testing Guide](../TESTING.md)
