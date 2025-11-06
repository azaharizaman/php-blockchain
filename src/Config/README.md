# Configuration

This directory contains configuration files and classes for the PHP Blockchain Integration Layer.

## Planned Configuration Files

- **blockchain.php**: Default blockchain configurations
- **networks.php**: Network-specific settings (mainnet, testnet, devnet)
- **drivers.php**: Driver registration and settings

## Usage

Configuration classes will be available under the `Blockchain\Config` namespace:

```php
use Blockchain\Config\NetworkConfig;
use Blockchain\Config\DriverConfig;

$config = NetworkConfig::getSolanaMainnet();
$drivers = DriverConfig::getRegisteredDrivers();
```