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

| Blockchain | Status | Driver Class | Network Type | Documentation |
|------------|--------|--------------|--------------|---------------|
| Solana     | ✅ Ready | `SolanaDriver` | Non-EVM | [docs/drivers/solana.md](docs/drivers/solana.md) |
| Ethereum   | ✅ Ready | `EthereumDriver` | EVM | [docs/drivers/ethereum.md](docs/drivers/ethereum.md) |
| Polygon    | 🔄 Planned | `PolygonDriver` | EVM | - |
| Near       | 🔄 Planned | `NearDriver` | Non-EVM | - |

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

### Abi

ABI (Application Binary Interface) encoding and decoding for Ethereum smart contracts. Provides helpers for encoding function calls and decoding responses when interacting with EVM-compatible blockchains.

```php
use Blockchain\Utils\Abi;

// Generate function selector
$selector = Abi::getFunctionSelector('balanceOf(address)');
// Returns: '0x70a08231'

// Encode function call with parameters
$data = Abi::encodeFunctionCall('transfer(address,uint256)', [
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '1000000000000000000'  // 1 token with 18 decimals
]);

// Decode response data
$balance = Abi::decodeResponse('uint256', $responseData);
// Returns: '1000000000000000000'

$address = Abi::decodeResponse('address', $responseData);
// Returns: '0x742d35cc6634c0532925a3b844bc9e7595f0beb'

// ERC-20 convenience methods
$balanceOfData = Abi::encodeBalanceOf($walletAddress);
$transferData = Abi::encodeTransfer($recipientAddress, $amount);
```

**Supported Types:**
- Encoding: `address`, `uint256`, `bool`, `string`
- Decoding: `uint256`, `address`, `bool`, `string`
- Note: Arrays, structs, and complex types are planned for future releases

### GuzzleAdapter

Provides a centralized HTTP client adapter that implements the `HttpClientAdapter` interface. This adapter standardizes HTTP operations across all blockchain drivers with consistent error handling and configuration.

```php
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;

// Using default configuration (30s timeout, JSON headers, SSL verification)
$adapter = new GuzzleAdapter();

// Make GET request
$data = $adapter->get('https://api.example.com/data');

// Make POST request with JSON data
$result = $adapter->post('https://api.example.com/submit', [
    'name' => 'Alice',
    'amount' => 100
]);

// Using custom configuration
$adapter = new GuzzleAdapter(null, [
    'timeout' => 60,
    'verify' => false, // Disable SSL verification (not recommended for production)
]);

// Set custom headers
$adapter->setDefaultHeader('Authorization', 'Bearer token123');

// Adjust timeout
$adapter->setTimeout(120); // 2 minutes
```

**Integration with Drivers:**

The GuzzleAdapter can be injected into blockchain drivers for better testability and control:

```php
use Blockchain\Drivers\SolanaDriver;
use Blockchain\Transport\GuzzleAdapter;

// Create adapter with custom config
$adapter = new GuzzleAdapter(null, ['timeout' => 60]);

// Inject into driver
$driver = new SolanaDriver($adapter);
$driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);

$balance = $driver->getBalance('address');
```

**Testing with MockHandler:**

The adapter supports Guzzle's MockHandler for unit testing without real network calls:

```php
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

// Create mock responses
$mockHandler = new MockHandler([
    new Response(200, [], json_encode(['status' => 'success', 'data' => [...]])),
    new Response(404, [], json_encode(['error' => 'Not found'])),
]);

$handlerStack = HandlerStack::create($mockHandler);
$client = new Client(['handler' => $handlerStack]);
$adapter = new GuzzleAdapter($client);

// Now requests will use mocked responses
$result = $adapter->get('https://api.example.com/test');
```

**Error Handling:**

The GuzzleAdapter automatically maps Guzzle HTTP exceptions to blockchain exceptions:

| Guzzle Exception | Blockchain Exception | Use Case |
|------------------|---------------------|----------|
| `ConnectException` | `ConfigurationException` | Network errors, timeouts, DNS failures |
| `ClientException` (4xx) | `ValidationException` | Bad requests, authentication errors |
| `ServerException` (5xx) | `TransactionException` | Server errors, service unavailable |

All exceptions preserve the original exception in the chain for debugging:

```php
try {
    $data = $adapter->get('https://api.example.com/data');
} catch (\Blockchain\Exceptions\ConfigurationException $e) {
    echo $e->getMessage(); // "Network connection failed: ..."
    $original = $e->getPrevious(); // Original Guzzle exception
}
```

**Configuration Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `timeout` | 30 | Request timeout in seconds |
| `connect_timeout` | 10 | Connection timeout in seconds |
| `verify` | true | Enable SSL certificate verification |
| `headers` | JSON headers | Default headers for all requests |
| `http_errors` | false | Manual error handling (adapter controls exceptions) |

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

### CachePool

The CachePool provides an in-memory caching layer to reduce redundant blockchain API calls and improve performance:

```php
use Blockchain\Utils\CachePool;
use Blockchain\Drivers\SolanaDriver;

// Create a cache pool
$cache = new CachePool();

// Configure default TTL (time-to-live) in seconds
$cache->setDefaultTtl(600); // 10 minutes

// Basic cache operations
$cache->set('my_key', 'my_value', 300); // Store with 5 minute TTL
$value = $cache->get('my_key'); // Retrieve value
$exists = $cache->has('my_key'); // Check if key exists
$cache->delete('my_key'); // Remove specific key
$cache->clear(); // Clear all cached items

// Bulk operations
$cache->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
], 300);

$values = $cache->getMultiple(['key1', 'key2']);
// Returns: ['key1' => 'value1', 'key2' => 'value2']

// Generate deterministic cache keys
$key = CachePool::generateKey('getBalance', ['address' => 'wallet123']);
// Returns: "blockchain:getBalance:{hash}"
```

**Using Cache with Drivers:**

Drivers automatically use caching when provided with a CachePool instance:

```php
use Blockchain\Utils\CachePool;
use Blockchain\Drivers\SolanaDriver;

// Create cache and driver
$cache = new CachePool();
$driver = new SolanaDriver($cache);

// Configure the driver
$driver->connect([
    'endpoint' => 'https://api.mainnet-beta.solana.com'
]);

// First call hits the blockchain
$balance1 = $driver->getBalance('address123'); // Network request

// Second call uses cached value (no network request)
$balance2 = $driver->getBalance('address123'); // From cache
```

**Cache Behavior:**

- **Read Operations Cached**: `getBalance()`, `getTransaction()`, `getBlock()`
- **Write Operations NOT Cached**: `sendTransaction()`
- **Default TTL**: 300 seconds (5 minutes)
- **Custom TTL per Operation**:
  - Balance queries: 300 seconds (5 minutes)
  - Transaction details: 3600 seconds (1 hour) - immutable data
  - Block details: 3600 seconds (1 hour) - immutable data

**When to Clear Cache:**

```php
// Clear cache after sending a transaction
$driver->sendTransaction($from, $to, $amount);
$cache->clear(); // Or delete specific keys

// Clear specific address balance
$key = CachePool::generateKey('getBalance', ['address' => 'wallet123']);
$cache->delete($key);
```

**Advanced Usage:**

```php
use Blockchain\BlockchainManager;
use Blockchain\Utils\CachePool;

// Share cache across multiple drivers
$cache = new CachePool();
$cache->setDefaultTtl(600);

// Use with BlockchainManager (requires manual driver instantiation)
$solanaDriver = new Blockchain\Drivers\SolanaDriver($cache);

// Register and use
$registry = new Blockchain\Registry\DriverRegistry();
$registry->registerDriverInstance('solana', $solanaDriver);
```

**Benefits:**

- **Reduced API Calls**: Minimize requests to blockchain RPC endpoints
- **Improved Performance**: Faster response times for repeated queries
- **Cost Savings**: Lower usage of rate-limited or paid RPC services
- **Network Resilience**: Serve cached data during temporary network issues

## 🧪 Testing

The package includes comprehensive unit tests and optional integration tests.

### Unit Tests

Unit tests use mocked responses and do not require network access:

```bash
# Run all unit tests
composer test

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage

# Run static analysis
composer analyse

# Check coding standards
composer lint

# Fix coding standards
composer fix
```

### Integration Tests

Integration tests connect to real blockchain test networks (e.g., Sepolia for Ethereum) to validate actual network interactions. These tests are **optional** and gated by environment variables.

#### Prerequisites

1. Set `RUN_INTEGRATION_TESTS=true` to enable integration tests
2. Set `ETHEREUM_RPC_ENDPOINT` to a valid testnet RPC URL

#### Getting Free RPC Endpoints

- **Infura**: Sign up at [infura.io](https://infura.io/) and use: `https://sepolia.infura.io/v3/YOUR_PROJECT_ID`
- **Alchemy**: Sign up at [alchemy.com](https://www.alchemy.com/) and use: `https://eth-sepolia.g.alchemy.com/v2/YOUR_API_KEY`
- **Public**: Use `https://rpc.sepolia.org` (may have rate limits)

#### Running Integration Tests

```bash
# Method 1: Export environment variables
export RUN_INTEGRATION_TESTS=true
export ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID
composer run integration-test

# Method 2: Inline
RUN_INTEGRATION_TESTS=true ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID composer run integration-test

# Method 3: Using .env file (not committed to repo)
echo "RUN_INTEGRATION_TESTS=true" >> .env
echo "ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID" >> .env
export $(cat .env | xargs)
composer run integration-test
```

#### What Integration Tests Cover

- ✅ Connection to Sepolia testnet
- ✅ Network info and chain ID validation
- ✅ Balance retrieval from real addresses
- ✅ Transaction and block queries
- ✅ Gas estimation with real network
- ✅ ERC-20 token balance retrieval
- ✅ Error handling and rate limiting

**Note**: Integration tests will be automatically skipped if `RUN_INTEGRATION_TESTS` is not set to `true`.

For detailed testing instructions, see [TESTING.md](TESTING.md).

### Chaos Testing and Resilience Scenarios

The package includes a chaos testing harness to validate system behavior under failure conditions. Chaos tests simulate various real-world failure scenarios to ensure the system's resilience patterns (retry, circuit breaker, rate limiter) handle failures gracefully.

#### Running Chaos Tests Locally

```bash
# Run all chaos tests with chaos mode enabled
CHAOS_TESTING=true vendor/bin/phpunit tests/Resilience/ResilienceScenariosTest.php

# Run specific chaos test
CHAOS_TESTING=true vendor/bin/phpunit --filter testSystemToleratesHighLatency tests/Resilience/ResilienceScenariosTest.php

# Run all resilience tests (without chaos injection)
vendor/bin/phpunit tests/Resilience/
```

#### Available Chaos Scenarios

The `ChaosHarness` class in `tests/Resilience/chaos-harness.php` provides several failure injection modes:

1. **Latency Injection**: Simulates slow network conditions
   - Tests timeout handling and user experience under latency
   - Configurable delay in milliseconds

2. **Rate Limit Spikes**: Simulates API rate limiting
   - Tests retry logic with 429 responses
   - Validates backoff strategies

3. **Intermittent Errors**: Random failures to simulate unreliable networks
   - Tests retry policies with configurable failure rates
   - Validates error recovery

4. **Partial Batch Failures**: Some operations succeed, others fail
   - Tests batch processing error handling
   - Validates partial success scenarios

5. **Combined Scenarios**: Multiple concurrent failure modes
   - Tests comprehensive resilience under complex failures
   - Validates recovery time windows

#### Using Chaos Harness in Tests

```php
use Blockchain\Tests\Resilience\ChaosHarness;

// Enable chaos testing
ChaosHarness::enable();

// Create a latency scenario (500ms delay)
$handler = ChaosHarness::createLatencyScenario(delayMs: 500);

// Create rate limit scenario
$handler = ChaosHarness::createRateLimitScenario(
    rateLimitCount: 3,
    retryAfterSeconds: 1
);

// Create intermittent error scenario (30% failure rate)
$handler = ChaosHarness::createIntermittentErrorScenario(
    failureRate: 30,
    totalRequests: 10,
    errorType: 'server'
);

// Use handler with HTTP client
$handlerStack = \GuzzleHttp\HandlerStack::create($handler);
$client = new \GuzzleHttp\Client(['handler' => $handlerStack]);
$adapter = new \Blockchain\Transport\GuzzleAdapter($client);
```

#### CI Integration

Chaos tests run automatically in CI on a **non-blocking nightly schedule** to avoid slowing down regular PR checks. The tests validate:

- System recovery from high latency conditions
- Retry policy handling of intermittent failures
- Circuit breaker protection during cascading failures
- Rate limiter handling of rate limit spikes
- Partial batch failure recovery
- Combined failure mode recovery within time windows

To run chaos tests in CI manually, trigger the workflow with the `chaos-testing` job.

**For detailed chaos testing documentation, see [docs/CHAOS_TESTING.md](docs/CHAOS_TESTING.md).**

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
│   ├── Transport/               # HTTP client adapters
│   ├── Registry/                # Driver registry
│   ├── Utils/                   # Helper classes
│   ├── Exceptions/              # Custom exceptions
│   └── Config/                  # Configuration
├── tests/                 # PHPUnit tests
├── composer.json         # Dependencies
└── README.md            # Documentation
```

### Layer Responsibilities

- **Transport Layer** (`src/Transport/`): Centralized HTTP client handling
  - `HttpClientAdapter` - Interface for HTTP operations
  - `GuzzleAdapter` - Guzzle-based implementation with error mapping

- **Driver Layer** (`src/Drivers/`): Blockchain-specific implementations
  - Each driver implements `BlockchainDriverInterface`
  - Uses `GuzzleAdapter` for HTTP communication
  - Handles blockchain-specific logic

- **Utility Layer** (`src/Utils/`): Common helper functions
  - Address validation, serialization, caching
  - Shared across all drivers

## 🔌 Creating Custom Drivers

To create a new blockchain driver:

1. **Implement the interface**:

```php
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Transport\GuzzleAdapter;

class CustomDriver implements BlockchainDriverInterface
{
    private ?GuzzleAdapter $httpClient = null;
    
    public function __construct(?GuzzleAdapter $httpClient = null)
    {
        $this->httpClient = $httpClient;
    }
    
    public function connect(array $config): void 
    { 
        // Create GuzzleAdapter if not provided
        if ($this->httpClient === null) {
            $this->httpClient = new GuzzleAdapter(null, [
                'base_uri' => $config['endpoint'],
                'timeout' => $config['timeout'] ?? 30,
            ]);
        }
    }
    
    public function getBalance(string $address): float 
    { 
        // Use $this->httpClient->get() or ->post()
        $data = $this->httpClient->post('/', [
            'method' => 'getBalance',
            'params' => [$address]
        ]);
        
        return (float) $data['result'];
    }
    
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { /* ... */ }
    public function getTransaction(string $txHash): array { /* ... */ }
    public function getBlock(int|string $blockNumber): array { /* ... */ }
    
    // Optional methods
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int { /* ... */ }
    public function getTokenBalance(string $address, string $tokenAddress): ?float { /* ... */ }
    public function getNetworkInfo(): ?array { /* ... */ }
}
```

**Benefits of using GuzzleAdapter:**
- Automatic error handling and exception mapping
- Consistent HTTP configuration across all drivers
- Easy testing with MockHandler
- JSON request/response handling built-in

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