# Ethereum Driver Documentation

Comprehensive documentation for the Ethereum driver in the PHP Blockchain Integration Layer.

## Overview

The `EthereumDriver` provides a complete interface for interacting with Ethereum and EVM-compatible blockchain networks. It implements the `BlockchainDriverInterface` and uses JSON-RPC communication with Ethereum nodes.

### Supported Networks

The driver works with any EVM-compatible network:

- **Ethereum Mainnet** - Production Ethereum network
- **Ethereum Testnets** - Sepolia, Goerli (deprecated), Holesky
- **Layer 2 Networks** - Polygon, Arbitrum, Optimism, Base
- **Local Development** - Hardhat, Ganache, Anvil
- **Other EVM Chains** - Binance Smart Chain, Avalanche C-Chain, Fantom

### Supported RPC Providers

- **Infura** - https://infura.io
- **Alchemy** - https://alchemy.com
- **QuickNode** - https://quicknode.com
- **Ankr** - https://ankr.com
- **Self-hosted** - Your own Ethereum node

### Key Features

- ✅ **ETH Balance Queries** - Get native ETH balance for any address
- ✅ **Transaction Details** - Retrieve transaction information by hash
- ✅ **Block Information** - Query block data by number or hash
- ✅ **Gas Estimation** - Estimate gas costs for transactions with safety buffer
- ✅ **Network Information** - Get chain ID, gas price, and block number
- ✅ **ERC-20 Token Support** - Query token balances (via ABI encoding)
- ✅ **Caching Layer** - Built-in caching to reduce RPC calls
- ✅ **Error Handling** - Comprehensive exception handling
- 🔄 **Transaction Signing** - Planned for Phase 2

## Installation

The Ethereum driver is included in the main package. Install via Composer:

```bash
composer require azaharizaman/php-blockchain
```

### Dependencies

- **PHP**: 8.2 or higher
- **guzzlehttp/guzzle**: ^7.0 (for HTTP requests)
- **ext-bcmath** or **ext-gmp**: Recommended for large number handling

### PHP Extensions

While not strictly required, these extensions improve performance:

```bash
# Ubuntu/Debian
sudo apt-get install php8.2-bcmath php8.2-gmp

# macOS with Homebrew
brew install php@8.2
```

## Configuration

### Configuration Schema

The driver accepts the following configuration options:

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `endpoint` | string | ✅ Yes | - | RPC endpoint URL |
| `chainId` | int | ❌ No | Auto-detected | Network chain ID |
| `timeout` | int | ❌ No | 30 | HTTP timeout in seconds |

### Configuration Examples

#### Ethereum Mainnet (Infura)

```php
$config = [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
    'timeout' => 30
];
```

#### Ethereum Mainnet (Alchemy)

```php
$config = [
    'endpoint' => 'https://eth-mainnet.g.alchemy.com/v2/YOUR_API_KEY',
    'timeout' => 30
];
```

#### Sepolia Testnet

```php
$config = [
    'endpoint' => 'https://sepolia.infura.io/v3/YOUR_PROJECT_ID',
    'chainId' => 11155111,
    'timeout' => 30
];
```

#### Local Development Node

```php
$config = [
    'endpoint' => 'http://localhost:8545',
    'timeout' => 10
];
```

#### Polygon Mainnet

```php
$config = [
    'endpoint' => 'https://polygon-rpc.com',
    'chainId' => 137,
    'timeout' => 30
];
```

### Environment Variable Configuration

For production applications, use environment variables to protect API keys:

```php
// .env file
ETHEREUM_RPC_ENDPOINT=https://mainnet.infura.io/v3/YOUR_PROJECT_ID
ETHEREUM_TIMEOUT=30

// In your code
use Blockchain\BlockchainManager;

$config = [
    'endpoint' => getenv('ETHEREUM_RPC_ENDPOINT'),
    'timeout' => (int) getenv('ETHEREUM_TIMEOUT')
];

$ethereum = new BlockchainManager('ethereum', $config);
```

## Basic Usage Examples

### Initialize Driver

```php
<?php

require_once 'vendor/autoload.php';

use Blockchain\BlockchainManager;

// Create instance with configuration
$ethereum = new BlockchainManager('ethereum', [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
    'timeout' => 30
]);
```

### Connect to Network

```php
// Connection happens automatically when using BlockchainManager
// Or manually with the driver:

use Blockchain\Drivers\EthereumDriver;

$driver = new EthereumDriver();
$driver->connect([
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'
]);
```

### Get ETH Balance

```php
// Get balance for an address
$address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';
$balance = $ethereum->getBalance($address);

echo "Balance: {$balance} ETH\n";
// Output: Balance: 1.234567 ETH
```

### Get Transaction Details

```php
// Fetch transaction by hash
$txHash = '0x1234567890abcdef...';
$transaction = $ethereum->getTransaction($txHash);

print_r($transaction);
/* Output:
Array (
    [hash] => 0x1234...
    [from] => 0xabc...
    [to] => 0xdef...
    [value] => 0xde0b6b3a7640000
    [gas] => 0x5208
    [gasPrice] => 0x4a817c800
    [blockNumber] => 0x123456
    ...
)
*/
```

### Get Block Information

```php
// Get block by number
$blockNumber = 18000000;
$block = $ethereum->getBlock($blockNumber);

echo "Block hash: {$block['hash']}\n";
echo "Timestamp: {$block['timestamp']}\n";
echo "Transaction count: " . count($block['transactions']) . "\n";

// Get block by hash
$blockHash = '0xabcdef1234567890...';
$block = $ethereum->getBlock($blockHash);
```

### Get Network Information

```php
// Retrieve network details
$networkInfo = $ethereum->getNetworkInfo();

echo "Chain ID: {$networkInfo['chainId']}\n";
echo "Gas Price: {$networkInfo['gasPrice']} wei\n";
echo "Block Number: {$networkInfo['blockNumber']}\n";

/* Output:
Chain ID: 1
Gas Price: 20000000000 wei
Block Number: 18123456
*/
```

## ERC-20 Token Examples

### Get Token Balance

```php
use Blockchain\Utils\Abi;

// Token contract address (USDT)
$tokenAddress = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
$walletAddress = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';

// Encode balanceOf call
$data = Abi::encodeBalanceOf($walletAddress);

// Get token balance (requires driver support for eth_call)
// NOTE: The EthereumDriver must implement getTokenBalance() for this to work.
$balance = $ethereum->getTokenBalance($tokenAddress, $walletAddress);
echo "Token Balance: {$balance}\n";
```

### Check Multiple Token Balances

```php
// Common ERC-20 token addresses on Ethereum Mainnet
$tokens = [
    'USDT' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    'USDC' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
    'DAI'  => '0x6B175474E89094C44Da98b954EedeAC495271d0F'
];

foreach ($tokens as $name => $tokenAddress) {
    $data = Abi::encodeBalanceOf($walletAddress);
    // Call contract and decode (implementation details omitted)
    echo "{$name} Balance: {$balance}\n";
}
```

### Handle Tokens with Different Decimals

```php
// Most tokens use 18 decimals, but not all
$tokenDecimals = [
    'USDT' => 6,   // 6 decimals
    'USDC' => 6,   // 6 decimals
    'DAI'  => 18,  // 18 decimals
    'WBTC' => 8    // 8 decimals
];

// Convert raw balance to human-readable format
function formatTokenBalance(string $rawBalance, int $decimals): float
{
    return (float) $rawBalance / pow(10, $decimals);
}

$rawBalance = '1000000'; // 1 USDT (6 decimals)
$formatted = formatTokenBalance($rawBalance, $tokenDecimals['USDT']);
echo "Formatted: {$formatted} USDT\n"; // Output: 1.0 USDT
```

### Common Token Contract Addresses

#### Ethereum Mainnet

| Token | Symbol | Address | Decimals |
|-------|--------|---------|----------|
| Tether | USDT | `0xdAC17F958D2ee523a2206206994597C13D831ec7` | 6 |
| USD Coin | USDC | `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` | 6 |
| Dai | DAI | `0x6B175474E89094C44Da98b954EedeAC495271d0F` | 18 |
| Wrapped BTC | WBTC | `0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599` | 8 |
| Chainlink | LINK | `0x514910771AF9Ca656af840dff83E8264EcF986CA` | 18 |

## Gas Estimation Examples

### Estimate Gas for Simple Transfer

```php
$fromAddress = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';
$toAddress = '0x1234567890123456789012345678901234567890';
$amount = 0.1; // 0.1 ETH

// Estimate gas
$gasEstimate = $ethereum->estimateGas($fromAddress, $toAddress, $amount);

echo "Estimated gas: {$gasEstimate} units\n";
// Output: Estimated gas: 25200 units (21000 * 1.2 safety buffer)

// Calculate total cost
$networkInfo = $ethereum->getNetworkInfo();
$gasPrice = $networkInfo['gasPrice'];
$totalCostWei = $gasEstimate * $gasPrice;
$totalCostEth = $totalCostWei / 1e18;

echo "Estimated cost: {$totalCostEth} ETH\n";
```

### Estimate Gas for Token Transfer

```php
// ERC-20 transfer requires more gas than simple ETH transfer
$tokenAddress = '0xdAC17F958D2ee523a2206206994597C13D831ec7'; // USDT

// Encode transfer call
$transferData = Abi::encodeTransfer($toAddress, '1000000'); // 1 USDT

// Estimate with data parameter
$gasEstimate = $ethereum->estimateGas(
    $fromAddress,
    $tokenAddress,
    0, // No ETH value for token transfers
    ['data' => $transferData]
);

echo "Estimated gas for token transfer: {$gasEstimate} units\n";
// Output: ~65000 units (typical for ERC-20 transfers)
```

### Estimate Gas for Contract Interaction

```php
// Custom contract interaction
$contractAddress = '0xYourContractAddress';
$encodedData = '0x...'; // Your encoded contract call

$gasEstimate = $ethereum->estimateGas(
    $fromAddress,
    $contractAddress,
    0,
    ['data' => $encodedData]
);

echo "Estimated gas: {$gasEstimate} units\n";
```

### Gas Safety Buffer Calculation

The driver automatically applies a 1.2x (20%) safety buffer to gas estimates:

```php
// Driver constant
const GAS_SAFETY_BUFFER = 1.2;

// Applied automatically
$rawEstimate = 21000; // From eth_estimateGas
$safeEstimate = (int) ($rawEstimate * GAS_SAFETY_BUFFER); // 25200

// This prevents "out of gas" errors due to state changes between estimation and execution
```

## Error Handling

### Common Exceptions

The driver throws specific exceptions for different error scenarios:

```php
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\ValidationException;

try {
    $ethereum = new BlockchainManager('ethereum', [
        'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'
    ]);
    
    $balance = $ethereum->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
    
} catch (ValidationException $e) {
    // Invalid input (e.g., malformed address)
    echo "Validation error: " . $e->getMessage();
    
} catch (ConfigurationException $e) {
    // Configuration or connection issues
    echo "Configuration error: " . $e->getMessage();
    
} catch (TransactionException $e) {
    // Transaction-related errors
    echo "Transaction error: " . $e->getMessage();
    
} catch (\Exception $e) {
    // Catch-all for other errors
    echo "Error: " . $e->getMessage();
}
```

### ConfigurationException Examples

Thrown when configuration is invalid or connection fails:

```php
// Missing endpoint
try {
    $driver->connect([]);
} catch (ConfigurationException $e) {
    // "Ethereum endpoint is required in configuration."
}

// Network timeout
try {
    $driver->connect([
        'endpoint' => 'https://invalid-endpoint.com',
        'timeout' => 5
    ]);
} catch (ConfigurationException $e) {
    // "Network connection failed: ..."
}

// Not connected
try {
    $driver = new EthereumDriver();
    $balance = $driver->getBalance('0x...');
} catch (ConfigurationException $e) {
    // "Ethereum driver is not connected. Please call connect() first."
}
```

### TransactionException Examples

Thrown when transaction operations fail:

```php
// Attempt to send transaction (not yet implemented)
try {
    $txHash = $ethereum->sendTransaction($from, $to, 1.0);
} catch (TransactionException $e) {
    // "Raw transaction signing not yet implemented for Ethereum driver."
}

// RPC returns error
try {
    $tx = $ethereum->getTransaction('0xinvalid');
} catch (\Exception $e) {
    // "Ethereum RPC Error: transaction not found"
}
```

### RPC Error Codes

Common JSON-RPC error codes and their meanings:

| Code | Message | Meaning | Solution |
|------|---------|---------|----------|
| -32700 | Parse error | Invalid JSON | Check request format |
| -32600 | Invalid request | Malformed request | Verify RPC method and params |
| -32601 | Method not found | Unknown method | Check method name spelling |
| -32602 | Invalid params | Wrong parameters | Verify parameter types and count |
| -32603 | Internal error | Server error | Retry or contact provider |
| -32000 | Server error | Generic error | Check node logs |
| 3 | Execution reverted | Transaction failed | Check transaction validity |

### Network Timeout Handling

```php
// Configure longer timeout for slow networks
$config = [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
    'timeout' => 60 // 60 seconds
];

try {
    $ethereum = new BlockchainManager('ethereum', $config);
    $balance = $ethereum->getBalance($address);
    
} catch (ConfigurationException $e) {
    if (strpos($e->getMessage(), 'timeout') !== false) {
        // Retry with longer timeout or different endpoint
        echo "Request timed out. Please try again.";
    }
}
```

## Advanced Usage

### Using Custom HTTP Client Configuration

```php
use Blockchain\Drivers\EthereumDriver;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;

// Create custom Guzzle client
$guzzleClient = new Client([
    'timeout' => 60,
    'verify' => true, // SSL verification
    'headers' => [
        'User-Agent' => 'MyApp/1.0',
        'Authorization' => 'Bearer token123'
    ]
]);

// Create adapter with custom client
$adapter = new GuzzleAdapter($guzzleClient);

// Inject into driver
$driver = new EthereumDriver($adapter);
$driver->connect([
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'
]);
```

### Caching Configuration for Performance

```php
use Blockchain\Utils\CachePool;
use Blockchain\Drivers\EthereumDriver;

// Create cache pool
$cache = new CachePool();
$cache->setDefaultTtl(600); // 10 minutes

// Create driver with cache
$driver = new EthereumDriver(null, $cache);
$driver->connect([
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'
]);

// First call - hits the network
$balance1 = $driver->getBalance($address); // Network request

// Second call - uses cache
$balance2 = $driver->getBalance($address); // From cache (fast!)

// Clear cache when needed
$cache->clear();
```

### Batch Requests (Future Feature)

```php
// Planned for future release
// Will allow multiple RPC calls in single HTTP request

/*
$batch = $ethereum->createBatch();
$batch->add('eth_getBalance', [$address1]);
$batch->add('eth_getBalance', [$address2]);
$batch->add('eth_getBalance', [$address3]);
$results = $batch->execute();
*/
```

### WebSocket Support (Future Feature)

```php
// Planned for future release
// Will enable real-time event monitoring

/*
$ethereum->subscribe('newHeads', function($block) {
    echo "New block: {$block['number']}\n";
});

$ethereum->subscribe('logs', [
    'address' => $tokenAddress,
    'topics' => [...]
], function($log) {
    echo "New transfer event\n";
});
*/
```

## Best Practices

### 1. Use Environment Variables for API Keys

**❌ Bad Practice:**
```php
$config = [
    'endpoint' => 'https://mainnet.infura.io/v3/abc123def456' // Hardcoded!
];
```

**✅ Good Practice:**
```php
// .env file (never commit this!)
ETHEREUM_ENDPOINT=https://mainnet.infura.io/v3/abc123def456

// In your code
$config = [
    'endpoint' => $_ENV['ETHEREUM_ENDPOINT'] ?? getenv('ETHEREUM_ENDPOINT')
];
```

### 2. Implement Rate Limiting for Public RPC Endpoints

```php
// Simple rate limiting example
class RateLimiter {
    private int $lastCall = 0;
    private int $minInterval = 100; // milliseconds
    
    public function throttle(): void {
        $now = (int) (microtime(true) * 1000);
        $elapsed = $now - $this->lastCall;
        
        if ($elapsed < $this->minInterval) {
            usleep(($this->minInterval - $elapsed) * 1000);
        }
        
        $this->lastCall = (int) (microtime(true) * 1000);
    }
}

$limiter = new RateLimiter();
foreach ($addresses as $address) {
    $limiter->throttle();
    $balance = $ethereum->getBalance($address);
}
```

### 3. Cache Token Decimals and Rarely-Changing Data

```php
// Cache token metadata to reduce RPC calls
class TokenMetadataCache {
    private array $decimals = [];
    
    public function getDecimals(string $tokenAddress): int {
        if (!isset($this->decimals[$tokenAddress])) {
            // Fetch from contract (once)
            $this->decimals[$tokenAddress] = $this->fetchDecimals($tokenAddress);
        }
        return $this->decimals[$tokenAddress];
    }
}
```

### 4. Handle Network Errors Gracefully

```php
function getBalanceWithRetry($ethereum, $address, $maxRetries = 3): ?float {
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $ethereum->getBalance($address);
        } catch (ConfigurationException $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                error_log("Failed to get balance after {$maxRetries} attempts: " . $e->getMessage());
                return null;
            }
            sleep(1); // Wait before retry
        }
    }
    
    return null;
}
```

### 5. Validate Addresses Before Making Calls

```php
function isValidEthereumAddress(string $address): bool {
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

// Use before API calls
if (!isValidEthereumAddress($address)) {
    throw new \InvalidArgumentException("Invalid Ethereum address: {$address}");
}

$balance = $ethereum->getBalance($address);
```

## Supported Networks Table

| Network | Chain ID | RPC Endpoint Example | Block Explorer |
|---------|----------|---------------------|----------------|
| **Ethereum Mainnet** | 1 | `https://mainnet.infura.io/v3/YOUR_KEY` | https://etherscan.io |
| **Sepolia Testnet** | 11155111 | `https://sepolia.infura.io/v3/YOUR_KEY` | https://sepolia.etherscan.io |
| **Goerli Testnet** | 5 | `https://goerli.infura.io/v3/YOUR_KEY` | https://goerli.etherscan.io |
| **Holesky Testnet** | 17000 | `https://holesky.infura.io/v3/YOUR_KEY` | https://holesky.etherscan.io |
| **Polygon Mainnet** | 137 | `https://polygon-rpc.com` | https://polygonscan.com |
| **Polygon Mumbai** | 80001 | `https://rpc-mumbai.maticvigil.com` | https://mumbai.polygonscan.com |
| **Binance Smart Chain** | 56 | `https://bsc-dataseed.binance.org` | https://bscscan.com |
| **Arbitrum One** | 42161 | `https://arb1.arbitrum.io/rpc` | https://arbiscan.io |
| **Arbitrum Goerli** | 421613 | `https://goerli-rollup.arbitrum.io/rpc` | https://goerli.arbiscan.io |
| **Optimism Mainnet** | 10 | `https://mainnet.optimism.io` | https://optimistic.etherscan.io |
| **Optimism Goerli** | 420 | `https://goerli.optimism.io` | https://goerli-optimism.etherscan.io |
| **Base Mainnet** | 8453 | `https://mainnet.base.org` | https://basescan.org |
| **Avalanche C-Chain** | 43114 | `https://api.avax.network/ext/bc/C/rpc` | https://snowtrace.io |
| **Fantom Opera** | 250 | `https://rpc.ftm.tools` | https://ftmscan.com |

## API Reference

The `EthereumDriver` implements the `BlockchainDriverInterface` with the following methods:

### connect(array $config): void

Connects to an Ethereum RPC endpoint.

**Parameters:**
- `$config['endpoint']` (string, required) - RPC endpoint URL
- `$config['timeout']` (int, optional) - HTTP timeout in seconds (default: 30)
- `$config['chainId']` (int, optional) - Network chain ID (auto-detected if not provided)

**Throws:**
- `ConfigurationException` - If endpoint is missing or connection fails

**Example:**
```php
$driver->connect([
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
    'timeout' => 30
]);
```

### getBalance(string $address): float

Gets the ETH balance for an address.

**Parameters:**
- `$address` (string) - Ethereum address (0x-prefixed, 42 characters)

**Returns:**
- `float` - Balance in ETH

**Throws:**
- `ConfigurationException` - If not connected
- `InvalidArgumentException` - If address format is invalid

**Example:**
```php
$balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
// Returns: 1.234567
```

### sendTransaction(string $from, string $to, float $amount, array $options = []): string

Sends a transaction (currently not implemented).

**Parameters:**
- `$from` (string) - Sender address
- `$to` (string) - Recipient address
- `$amount` (float) - Amount in ETH
- `$options` (array, optional) - Additional transaction options

**Returns:**
- `string` - Transaction hash

**Throws:**
- `TransactionException` - Currently always throws (not yet implemented)

**Note:** Transaction signing is planned for Phase 2. Use external wallet libraries for now.

### getTransaction(string $hash): array

Retrieves transaction details by hash.

**Parameters:**
- `$hash` (string) - Transaction hash (0x-prefixed)

**Returns:**
- `array` - Transaction details including:
  - `hash` (string) - Transaction hash
  - `from` (string) - Sender address
  - `to` (string) - Recipient address
  - `value` (string) - Value in wei (hex)
  - `gas` (string) - Gas limit (hex)
  - `gasPrice` (string) - Gas price in wei (hex)
  - `blockNumber` (string) - Block number (hex)
  - `transactionIndex` (string) - Transaction index in block (hex)

**Throws:**
- `ConfigurationException` - If not connected

**Example:**
```php
$tx = $driver->getTransaction('0x1234567890abcdef...');
print_r($tx);
```

### getBlock(int|string $blockIdentifier): array

Retrieves block information.

**Parameters:**
- `$blockIdentifier` (int|string) - Block number (int) or block hash (string)

**Returns:**
- `array` - Block details including:
  - `number` (string) - Block number (hex)
  - `hash` (string) - Block hash
  - `parentHash` (string) - Parent block hash
  - `timestamp` (string) - Block timestamp (hex)
  - `transactions` (array) - Array of transaction hashes or objects
  - `gasUsed` (string) - Total gas used (hex)
  - `gasLimit` (string) - Gas limit (hex)

**Throws:**
- `ConfigurationException` - If not connected

**Example:**
```php
// By number
$block = $driver->getBlock(18000000);

// By hash
$block = $driver->getBlock('0xabcdef1234567890...');
```

### estimateGas(string $from, string $to, float $amount, array $options = []): ?int

Estimates gas required for a transaction with 20% safety buffer.

**Parameters:**
- `$from` (string) - Sender address
- `$to` (string) - Recipient address
- `$amount` (float) - Amount in ETH
- `$options` (array, optional) - Additional options:
  - `data` (string) - Contract call data for contract interactions

**Returns:**
- `int|null` - Estimated gas units with safety buffer, or null on failure

**Throws:**
- `ConfigurationException` - If not connected
- `InvalidArgumentException` - If address format is invalid

**Example:**
```php
$gas = $driver->estimateGas($from, $to, 0.1);
// Returns: 25200 (21000 * 1.2 safety buffer)
```

### getTokenBalance(string $address, string $tokenAddress): ?float

Gets ERC-20 token balance (currently returns null - implementation planned).

**Parameters:**
- `$address` (string) - Wallet address
- `$tokenAddress` (string) - Token contract address

**Returns:**
- `float|null` - Token balance (currently always null)

**Note:** Full implementation planned for TASK-005.

### getNetworkInfo(): ?array

Retrieves network information.

**Returns:**
- `array|null` - Network details including:
  - `chainId` (int) - Network chain ID
  - `gasPrice` (int) - Current gas price in wei
  - `blockNumber` (int) - Latest block number

**Throws:**
- `ConfigurationException` - If not connected

**Example:**
```php
$info = $driver->getNetworkInfo();
// Returns: ['chainId' => 1, 'gasPrice' => 20000000000, 'blockNumber' => 18123456]
```

### Ethereum-Specific Behaviors

1. **Wei Conversion**: All values are automatically converted between Wei and ETH
2. **Hex Handling**: RPC responses in hex format are converted to appropriate types
3. **Address Validation**: Addresses must be 0x-prefixed with 40 hex characters
4. **Gas Safety Buffer**: Gas estimates include 20% buffer to prevent "out of gas" errors
5. **Caching**: Read operations are cached by default to improve performance

## Troubleshooting

### Common Issues and Solutions

#### Issue: "Invalid API key"

**Symptom:**
```
Ethereum RPC Error: invalid project id
```

**Solution:**
1. Verify your API key is correct in the endpoint URL
2. Check if your API key is active on the provider's dashboard
3. Ensure you're using the correct endpoint format for your provider

```php
// Correct format for Infura
$endpoint = 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID';

// Correct format for Alchemy
$endpoint = 'https://eth-mainnet.g.alchemy.com/v2/YOUR_API_KEY';
```

#### Issue: "Timeout"

**Symptom:**
```
Network connection failed: Connection timeout
```

**Solution:**
1. Increase timeout setting in configuration
2. Try a different RPC provider
3. Check your network connection

```php
$config = [
    'endpoint' => 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID',
    'timeout' => 60 // Increase from default 30
];
```

#### Issue: "Invalid address format"

**Symptom:**
```
Invalid Ethereum address format: 0x123
```

**Solution:**
Use properly formatted Ethereum addresses:
- Must start with `0x`
- Must be 42 characters long (0x + 40 hex characters)
- Use checksummed addresses when possible

```php
// ❌ Invalid
$address = '0x123'; // Too short
$address = '742d35Cc6634C0532925a3b844Bc9e7595f0bEb0'; // Missing 0x

// ✅ Valid
$address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';
```

#### Issue: "Gas estimation failed"

**Symptom:**
```
Ethereum RPC Error: execution reverted
```

**Solution:**
1. Check that the transaction would be valid (sufficient balance, correct parameters)
2. Verify the recipient address exists and can receive transactions
3. For contract calls, ensure the function exists and parameters are correct

```php
// Verify balance before estimating gas
$balance = $driver->getBalance($from);
if ($balance < $amount) {
    throw new \Exception("Insufficient balance");
}

$gasEstimate = $driver->estimateGas($from, $to, $amount);
```

#### Issue: "Rate limit exceeded"

**Symptom:**
```
Ethereum RPC Error: rate limit exceeded
```

**Solution:**
1. Implement rate limiting in your application
2. Upgrade to a paid RPC provider plan
3. Use caching to reduce API calls
4. Distribute requests across multiple RPC endpoints

```php
// Enable caching
$cache = new CachePool();
$cache->setDefaultTtl(600); // Cache for 10 minutes
$driver = new EthereumDriver(null, $cache);
```

#### Issue: "Method not found"

**Symptom:**
```
Ethereum RPC Error: method not found
```

**Solution:**
1. Verify the RPC method is supported by your node/provider
2. Check for typos in method names
3. Ensure you're connected to the correct network

```php
// Some methods may not be available on all providers
// Example: debug_* methods are often restricted
```

---

## Additional Resources

- **Ethereum JSON-RPC Specification**: https://ethereum.org/en/developers/docs/apis/json-rpc/
- **ERC-20 Token Standard**: https://eips.ethereum.org/EIPS/eip-20
- **Web3.php** (alternative library): https://github.com/web3p/web3.php
- **Infura Documentation**: https://docs.infura.io/
- **Alchemy Documentation**: https://docs.alchemy.com/

## Support

For issues, questions, or contributions related to the Ethereum driver:

- **GitHub Issues**: https://github.com/azaharizaman/php-blockchain/issues
- **Discussions**: https://github.com/azaharizaman/php-blockchain/discussions
- **Main Documentation**: [README.md](../../README.md)

---

**Last Updated**: 2025-11-08  
**Driver Version**: 1.0.0  
**Status**: Active Development
