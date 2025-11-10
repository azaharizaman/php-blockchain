# CLI Tools

This document provides documentation for command-line tools included with the PHP Blockchain Integration Layer.

## switch-network.php

A CLI utility for developers to quickly switch between network profiles and output configuration for local development.

### Overview

The `switch-network.php` script helps developers:
- View network profile configurations in various formats
- Quickly switch between blockchain networks (mainnet, testnet, devnet)
- Generate configuration files for local development
- Export configurations in JSON, PHP array, or environment variable formats

### Installation

No installation required beyond having Composer dependencies installed:

```bash
composer install
```

### Usage

```bash
php bin/switch-network.php <profile-name> [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--output=<path>` | Write configuration to the specified file | stdout |
| `--dry-run` | Print configuration to stdout only | true |
| `--format=<format>` | Output format: json, php, or env | json |
| `--force` | Overwrite existing file without confirmation | false |
| `--help`, `-h` | Show help message | - |

### Available Profiles

The following network profiles are available out of the box:

**Solana Networks:**
- `solana.mainnet` - Solana Mainnet Beta
- `solana.devnet` - Solana Devnet
- `solana.testnet` - Solana Testnet

**Ethereum Networks:**
- `ethereum.mainnet` - Ethereum Mainnet
- `ethereum.goerli` - Ethereum Goerli Testnet
- `ethereum.sepolia` - Ethereum Sepolia Testnet
- `ethereum.localhost` - Local Ethereum Node (default: http://localhost:8545)

### Output Formats

#### JSON Format (Default)

Outputs the configuration as pretty-printed JSON:

```bash
php bin/switch-network.php solana.mainnet
```

Output:
```json
{
    "driver": "solana",
    "endpoint": "https://api.mainnet-beta.solana.com",
    "timeout": 30,
    "commitment": "finalized"
}
```

#### PHP Array Format

Outputs the configuration as a PHP array that can be included in configuration files:

```bash
php bin/switch-network.php ethereum.localhost --format=php
```

Output:
```php
<?php

return array (
  'driver' => 'ethereum',
  'endpoint' => 'http://localhost:8545',
  'chainId' => '0x539',
  'timeout' => 30,
);
```

#### Environment Variable Format

Outputs the configuration as KEY=value pairs suitable for `.env` files:

```bash
php bin/switch-network.php solana.devnet --format=env
```

Output:
```
DRIVER=solana
ENDPOINT=https://api.devnet.solana.com
TIMEOUT=30
COMMITMENT=finalized
```

### Examples

#### Display Configuration (Dry-Run Mode)

View a network profile's configuration without writing to a file:

```bash
# Display Solana mainnet configuration
php bin/switch-network.php solana.mainnet

# Display Ethereum mainnet configuration in PHP format
php bin/switch-network.php ethereum.mainnet --format=php

# Display configuration as environment variables
php bin/switch-network.php ethereum.sepolia --format=env
```

#### Write Configuration to File

Save network configuration to a file:

```bash
# Write JSON configuration to file
php bin/switch-network.php solana.devnet --output=config/active.json

# Write PHP configuration to file
php bin/switch-network.php ethereum.localhost --output=config/network.php --format=php

# Write ENV configuration to file
php bin/switch-network.php solana.testnet --output=config/network.env --format=env
```

#### Force Overwrite

Overwrite existing files without confirmation:

```bash
php bin/switch-network.php ethereum.mainnet --output=config/network.json --force
```

### Common Usage Patterns

#### Quick Development Setup

Switch to a testnet for development:

```bash
# Generate configuration for Ethereum Sepolia testnet
php bin/switch-network.php ethereum.sepolia --output=config/active.json --force
```

#### Environment Configuration

Generate environment variables for deployment:

```bash
# Export production configuration
php bin/switch-network.php ethereum.mainnet --format=env --output=.env.production

# Export staging configuration
php bin/switch-network.php ethereum.goerli --format=env --output=.env.staging
```

#### Local Development

Set up local development environment:

```bash
# Configure for local Ethereum node
php bin/switch-network.php ethereum.localhost --output=config/local.json --force
```

#### Configuration Inspection

Quickly inspect network configurations:

```bash
# View all available profiles
php bin/switch-network.php --help

# Compare different networks
php bin/switch-network.php solana.mainnet
php bin/switch-network.php solana.devnet
```

### Exit Codes

The script uses standard exit codes:

- `0` - Success (configuration displayed or written successfully)
- `1` - Error (invalid profile, write failure, invalid format, etc.)

### Error Handling

The script provides clear error messages for common issues:

**Invalid Profile:**
```bash
$ php bin/switch-network.php invalid.profile
Error: Network profile 'invalid.profile' not found. Available profiles: solana.mainnet, ...

Available profiles:
  - solana.mainnet
  - solana.devnet
  ...
```

**Invalid Format:**
```bash
$ php bin/switch-network.php solana.mainnet --format=xml
Error: Invalid format 'xml'. Supported formats: json, php, env
```

**Write Failure:**
```bash
$ php bin/switch-network.php solana.mainnet --output=/readonly/path/config.json
Error: Cannot write to file '/readonly/path/config.json'
```

### Security Considerations

#### File Permissions

When writing configuration files, the script automatically sets file permissions to `0600` (owner read/write only) to protect sensitive configuration data such as API keys and endpoints.

#### Confirmation Prompt

By default, the script prompts for confirmation before overwriting existing files. Use the `--force` flag to skip this prompt in automated scripts.

#### Environment Variables

Network profiles support environment variable interpolation. For example, Ethereum mainnet uses `${INFURA_API_KEY}`:

```bash
# Set API key
export INFURA_API_KEY="your_api_key_here"

# Generate configuration with interpolated values
php bin/switch-network.php ethereum.mainnet
```

Output:
```json
{
    "driver": "ethereum",
    "endpoint": "https://mainnet.infura.io/v3/your_api_key_here",
    ...
}
```

### Integration with Application

Use the generated configuration files in your application:

```php
use Blockchain\BlockchainManager;

// Load configuration from file
$config = json_decode(file_get_contents('config/active.json'), true);

// Initialize blockchain manager
$blockchain = new BlockchainManager(
    $config['driver'],
    $config
);

// Use the blockchain manager
$balance = $blockchain->getBalance($address);
```

### Troubleshooting

**Autoloader Not Found:**
```
Error: Composer autoloader not found. Run 'composer install' first.
```
**Solution:** Run `composer install` in the project root directory.

**Permission Denied:**
```
Error: Cannot create directory '/path/to/config'
```
**Solution:** Ensure you have write permissions to the target directory or choose a different output path.

**Profile Not Found:**
```
Error: Network profile 'custom.network' not found.
```
**Solution:** Use one of the available profiles listed in `--help` or add a custom profile to `src/Config/NetworkProfiles.php`.

### Advanced Usage

#### Custom Profiles

To add custom network profiles, extend the `NetworkProfiles` class in `src/Config/NetworkProfiles.php`:

```php
private static array $profiles = [
    // ... existing profiles ...
    
    'polygon.mainnet' => [
        'driver' => 'ethereum',
        'endpoint' => 'https://polygon-rpc.com',
        'chainId' => '0x89',
        'timeout' => 30,
    ],
];
```

Then use your custom profile:

```bash
php bin/switch-network.php polygon.mainnet
```

#### Scripting

Use the script in shell scripts or CI/CD pipelines:

```bash
#!/bin/bash

# Deploy to staging
php bin/switch-network.php ethereum.goerli --output=config/network.json --force

if [ $? -eq 0 ]; then
    echo "Configuration updated successfully"
    # Deploy application
else
    echo "Failed to update configuration"
    exit 1
fi
```

### Related Documentation

- [Configuration Guide](configuration.md) - Detailed configuration options
- [Network Profiles](../src/Config/NetworkProfiles.php) - Source code for network profiles
- [Configuration Loader](../src/Config/ConfigLoader.php) - Configuration validation and loading

### Support

For issues or questions:
- Check the [main README](../README.md)
- Review the [examples directory](../examples/)
- Open an issue on the GitHub repository
