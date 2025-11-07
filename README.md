# 🔗 PHP Blockchain Integration Layer

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/azaharizaman/php-blockchain.svg)](https://packagist.org/packages/azaharizaman/php-blockchain)

A **modular, unified PHP interface** for integrating various blockchain networks (EVM and non-EVM) into any PHP application. This package provides an **agent-ready architecture** where new blockchain drivers can be automatically generated, tested, and integrated.

## 🚀 Features

- **Unified Interface**: Consistent method naming across all supported blockchain networks
- **Modular Architecture**: Easy to extend with new blockchain drivers
- **Agent-Ready**: Supports auto-generation of new drivers via GitHub Copilot
- **Framework Integration**: Works with Laravel, Symfony, and standalone applications
- **Comprehensive Testing**: Full PHPUnit test coverage
- **PSR Standards**: Follows PSR-4 autoloading and PSR-12 coding standards

## 📦 Installation

Install via Composer:

```bash
composer require azaharizaman/php-blockchain
```

## 🔧 Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use Blockchain\BlockchainManager;

// Initialize with Solana
$blockchain = new BlockchainManager('solana', [
    'endpoint' => 'https://api.mainnet-beta.solana.com'
]);

// Get balance
$balance = $blockchain->getBalance('YourSolanaPublicKeyHere');
echo "Balance: {$balance} SOL\n";

// Get transaction details
$transaction = $blockchain->getTransaction('transaction_signature_here');
print_r($transaction);

// Get network information
$networkInfo = $blockchain->getNetworkInfo();
print_r($networkInfo);
```

## 🌐 Supported Blockchains

| Blockchain | Status | Driver Class | Network Type |
|------------|--------|--------------|--------------|
| Solana     | ✅ Ready | `SolanaDriver` | Non-EVM |
| Ethereum   | 🔄 Planned | `EthereumDriver` | EVM |
| Polygon    | 🔄 Planned | `PolygonDriver` | EVM |
| Near       | 🔄 Planned | `NearDriver` | Non-EVM |

## 📚 Usage Examples

### Working with Different Blockchains

```php
use Blockchain\BlockchainManager;

// Solana example
$solana = new BlockchainManager('solana', [
    'endpoint' => 'https://api.mainnet-beta.solana.com',
    'timeout' => 30
]);

$balance = $solana->getBalance('wallet_address');
$tokenBalance = $solana->getTokenBalance('wallet_address', 'token_mint_address');

// Switch to another blockchain (when implemented)
$ethereum = new BlockchainManager('ethereum', [
    'endpoint' => 'https://mainnet.infura.io/v3/your-project-id',
    'timeout' => 30
]);
```

### Error Handling

The package provides structured exception classes for different error scenarios:

```php
use Blockchain\BlockchainManager;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\UnsupportedDriverException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\ValidationException;

try {
    $blockchain = new BlockchainManager('solana', [
        'endpoint' => 'https://api.mainnet-beta.solana.com'
    ]);
    
    $balance = $blockchain->getBalance('address');
    
} catch (ValidationException $e) {
    // Handle input validation errors
    echo "Validation error: " . $e->getMessage();
    foreach ($e->getErrors() as $field => $error) {
        echo "  {$field}: {$error}\n";
    }
} catch (TransactionException $e) {
    // Handle transaction errors
    echo "Transaction failed: " . $e->getMessage();
    if ($hash = $e->getTransactionHash()) {
        echo "  Transaction hash: {$hash}\n";
    }
} catch (ConfigurationException $e) {
    // Handle configuration errors
    echo "Configuration error: " . $e->getMessage();
} catch (UnsupportedDriverException $e) {
    // Handle driver errors
    echo "Driver error: " . $e->getMessage();
} catch (\Exception $e) {
    // Handle any other errors
    echo "General error: " . $e->getMessage();
}
```

#### Exception Types

| Exception | Thrown When | Additional Methods |
|-----------|-------------|-------------------|
| `ConfigurationException` | Configuration is invalid or missing | - |
| `UnsupportedDriverException` | Driver is not registered or available | - |
| `TransactionException` | Transaction operations fail | `getTransactionHash()` |
| `ValidationException` | Input validation fails | `getErrors()` |

For detailed exception documentation, see [src/Exceptions/README.md](src/Exceptions/README.md).

### Registry Management

```php
use Blockchain\BlockchainManager;

$manager = new BlockchainManager();

// Check supported drivers
$drivers = $manager->getSupportedDrivers();
print_r($drivers); // ['solana']

// Register a custom driver
$registry = $manager->getDriverRegistry();
$registry->registerDriver('custom', CustomBlockchainDriver::class);
```

## 🛠️ Utility Classes

The package provides several utility classes to help with common blockchain operations:

### AddressValidator

Validates and normalizes blockchain addresses for different networks:

```php
use Blockchain\Utils\AddressValidator;

// Validate Solana address
$isValid = AddressValidator::isValid('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM', 'solana');
// Returns: true

// Validate with default network (solana)
$isValid = AddressValidator::isValid('invalid123');
// Returns: false

// Normalize addresses (trim whitespace, lowercase hex addresses)
$normalized = AddressValidator::normalize('  0x1234ABCD  ');
// Returns: '0x1234abcd'
```

**Supported Networks:**
- `solana` - Base58 encoded addresses (32-44 characters)
- More networks coming soon

### Serializer

Handles data serialization and deserialization:

```php
use Blockchain\Utils\Serializer;

// JSON serialization
$data = ['name' => 'Alice', 'balance' => 100];
$json = Serializer::toJson($data);
// Returns: '{"name":"Alice","balance":100}'

$decoded = Serializer::fromJson($json);
// Returns: ['name' => 'Alice', 'balance' => 100]

// Base64 encoding
$encoded = Serializer::toBase64('Hello World');
// Returns: 'SGVsbG8gV29ybGQ='

$decoded = Serializer::fromBase64($encoded);
// Returns: 'Hello World'
```

**Error Handling:**
- Throws `JsonException` for invalid JSON
- Throws `InvalidArgumentException` for invalid Base64

### HttpClientAdapter

Provides a simplified HTTP client interface with Guzzle integration:

```php
use Blockchain\Utils\HttpClientAdapter;
use GuzzleHttp\Client;

// Using default client (30 second timeout, JSON headers)
$adapter = new HttpClientAdapter();

// Make GET request
$data = $adapter->get('https://api.example.com/data');

// Make POST request
$result = $adapter->post('https://api.example.com/submit', [
    'name' => 'Alice',
    'amount' => 100
]);

// Using custom Guzzle client
$client = new Client(['timeout' => 60]);
$adapter = new HttpClientAdapter($client);
```

**Error Handling:**
- Converts Guzzle exceptions to `ConfigurationException`
- Automatically parses JSON responses
- Provides detailed error messages with HTTP status codes

### ConfigLoader

Loads and validates blockchain driver configurations from multiple sources:

```php
use Blockchain\Config\ConfigLoader;

// Load from array
$config = ConfigLoader::fromArray([
    'rpc_url' => 'https://api.mainnet-beta.solana.com',
    'timeout' => 30,
    'commitment' => 'finalized'
]);

// Load from environment variables
// Looks for variables like: BLOCKCHAIN_RPC_URL, BLOCKCHAIN_TIMEOUT, etc.
$config = ConfigLoader::fromEnv('BLOCKCHAIN_');

// Load from PHP file
$config = ConfigLoader::fromFile(__DIR__ . '/config/solana.php');

// Load from JSON file
$config = ConfigLoader::fromFile(__DIR__ . '/config/solana.json');

// Validate configuration
try {
    ConfigLoader::validateConfig($config, 'solana');
    echo "Configuration is valid!";
} catch (\Blockchain\Exceptions\ValidationException $e) {
    echo "Invalid configuration: " . $e->getMessage();
    print_r($e->getErrors()); // Get detailed error information
}
```

**Configuration Sources:**
1. **Array**: Direct configuration arrays
2. **Environment**: Environment variables with customizable prefix
3. **File**: PHP files (returning arrays) or JSON files

**Schema Validation:**

ConfigLoader validates configurations against driver-specific schemas. For Solana:

- **Required fields:**
  - `rpc_url` (string, valid HTTP/HTTPS URL)

- **Optional fields:**
  - `timeout` (integer, > 0) - Request timeout in seconds
  - `commitment` (string, one of: 'finalized', 'confirmed', 'processed')

**Environment Variable Parsing:**

The loader automatically converts environment variable values:
- `'true'`/`'false'` → boolean
- Numeric strings → int/float
- Other values → string

**Example Configuration Files:**

See `config/solana.example.php` and `.env.example` for complete configuration examples.

**Error Messages:**

Validation errors include detailed field-specific information:

```php
try {
    ConfigLoader::validateConfig($config, 'solana');
} catch (\Blockchain\Exceptions\ValidationException $e) {
    // Get all validation errors
    $errors = $e->getErrors();
    // Example: ['rpc_url' => 'must be a valid URL', 'timeout' => 'must be greater than 0']
}
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage

# Run static analysis
composer analyse

# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix
```

## 🤖 Agent Integration

This repository is **agent-ready** and supports automatic driver generation via GitHub Copilot. The `.copilot/agent.yml` file defines tasks for:

- **Creating new drivers**: Generate blockchain driver classes automatically
- **Running tests**: Execute PHPUnit tests for specific drivers
- **Updating documentation**: Regenerate README with new driver information

### Example Agent Tasks

```yaml
# Generate a new Ethereum driver
copilot task create-new-driver --blockchain_name=ethereum --rpc_spec_url=https://ethereum.org/en/developers/docs/apis/json-rpc/

# Run tests for Solana driver
copilot task test-driver --driver=solana

# Update README with new drivers
copilot task update-readme
```

## 🏗️ Architecture

```
/php-blockchain
├── .copilot/              # Agent configuration
├── src/
│   ├── BlockchainManager.php     # Main entry point
│   ├── Contracts/               # Interfaces
│   ├── Drivers/                 # Blockchain implementations
│   ├── Registry/                # Driver registry
│   ├── Utils/                   # Helper classes
│   ├── Exceptions/              # Custom exceptions
│   └── Config/                  # Configuration
├── tests/                 # PHPUnit tests
├── composer.json         # Dependencies
└── README.md            # Documentation
```

## 🔌 Creating Custom Drivers

To create a new blockchain driver:

1. **Implement the interface**:

```php
use Blockchain\Contracts\BlockchainDriverInterface;

class CustomDriver implements BlockchainDriverInterface
{
    public function connect(array $config): void { /* ... */ }
    public function getBalance(string $address): float { /* ... */ }
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { /* ... */ }
    public function getTransaction(string $txHash): array { /* ... */ }
    public function getBlock(int|string $blockNumber): array { /* ... */ }
    
    // Optional methods
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int { /* ... */ }
    public function getTokenBalance(string $address, string $tokenAddress): ?float { /* ... */ }
    public function getNetworkInfo(): ?array { /* ... */ }
}
```

2. **Register the driver**:

```php
$registry = new DriverRegistry();
$registry->registerDriver('custom', CustomDriver::class);
```

3. **Add tests**:

Create a test file in `tests/` following the pattern of `SolanaDriverTest.php`.

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Follow PSR-12 coding standards
6. Submit a pull request

## 📜 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🔮 Roadmap

- [ ] **Ethereum Driver**: Complete EVM-based blockchain support
- [ ] **Polygon Driver**: Layer 2 scaling solution support  
- [ ] **Near Protocol Driver**: NEAR blockchain integration
- [ ] **Smart Contract Interface**: Deploy and interact with contracts
- [ ] **Wallet Management**: Generate and manage blockchain wallets
- [ ] **Event Listeners**: WebSocket-based real-time event monitoring
- [ ] **Laravel Package**: Native Laravel service provider
- [ ] **Symfony Bundle**: Symfony framework integration

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/azaharizaman/php-blockchain/issues)
- **Discussions**: [GitHub Discussions](https://github.com/azaharizaman/php-blockchain/discussions)
- **Documentation**: [Wiki](https://github.com/azaharizaman/php-blockchain/wiki)

---

**Made with ❤️ by [Azahari Zaman](https://github.com/azaharizaman)**