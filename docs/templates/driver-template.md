# {{driver_name}} Driver

## Overview

The `{{driver_class}}` provides integration with the {{blockchain_name}} blockchain network.

**Network Type**: {{network_type}}  
**Native Currency**: {{native_currency}}  
**Decimals**: {{decimals}}  
**Default Endpoint**: {{default_endpoint}}

## Installation

```bash
composer require azaharizaman/php-blockchain
```

## Basic Usage

```php
<?php

use Blockchain\BlockchainManager;

// Initialize {{driver_name}} driver
$blockchain = new BlockchainManager('{{driver_lowercase}}', [
    'endpoint' => '{{default_endpoint}}',
    'timeout' => 30
]);

// Get balance
$balance = $blockchain->getBalance('your_wallet_address');
echo "Balance: {$balance} {{native_currency}}\n";

// Get transaction
$transaction = $blockchain->getTransaction('transaction_hash');
print_r($transaction);

// Send transaction
$txHash = $blockchain->sendTransaction(
    'from_address',
    'to_address',
    1.5,
    ['gas' => 21000]
);
echo "Transaction hash: {$txHash}\n";
```

## Available Methods

### Connection Management

#### `connect(array $config): void`

Establishes connection to the {{blockchain_name}} network.

```php
$blockchain->connect([
    'endpoint' => 'https://your-node-url',
    'timeout' => 30,
    'retry_attempts' => 3
]);
```

### Balance Operations

#### `getBalance(string $address): float`

Get the native token balance for an address.

```php
$balance = $blockchain->getBalance('wallet_address');
```

#### `getTokenBalance(string $address, string $tokenAddress): ?float`

Get token balance for a specific token contract.

```php
$tokenBalance = $blockchain->getTokenBalance(
    'wallet_address',
    'token_contract_address'
);
```

### Transaction Operations

#### `sendTransaction(string $from, string $to, float $amount, array $options = []): string`

Send a transaction on the network. Returns the transaction hash.

```php
$txHash = $blockchain->sendTransaction(
    'sender_address',
    'recipient_address',
    1.0,
    [
        'gas' => 21000,
        'gasPrice' => '20000000000'
    ]
);
```

#### `getTransaction(string $hash): array`

Retrieve transaction details by hash.

```php
$tx = $blockchain->getTransaction('transaction_hash');
```

### Block Operations

#### `getBlock(string|int $blockIdentifier): array`

Get block information by block number or hash.

```php
// By block number
$block = $blockchain->getBlock(12345);

// By block hash
$block = $blockchain->getBlock('block_hash');
```

### Network Information

#### `getNetworkInfo(): ?array`

Get current network status and information.

```php
$info = $blockchain->getNetworkInfo();
print_r($info);
```

#### `estimateGas(string $from, string $to, float $amount, array $options = []): ?int`

Estimate gas required for a transaction.

```php
$gasEstimate = $blockchain->estimateGas(
    'sender_address',
    'recipient_address',
    1.0
);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `endpoint` | string | {{default_endpoint}} | RPC endpoint URL |
| `timeout` | int | 30 | Request timeout in seconds |
| `retry_attempts` | int | 3 | Number of retry attempts on failure |
| `api_key` | string | null | Optional API key for authenticated endpoints |

## Error Handling

The driver throws specific exceptions for different error scenarios:

```php
use Blockchain\BlockchainManager;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\NetworkException;

try {
    $blockchain = new BlockchainManager('{{driver_lowercase}}', [
        'endpoint' => '{{default_endpoint}}'
    ]);
    
    $balance = $blockchain->getBalance('address');
} catch (ConfigurationException $e) {
    // Handle configuration errors
    echo "Configuration error: " . $e->getMessage();
} catch (TransactionException $e) {
    // Handle transaction errors
    echo "Transaction error: " . $e->getMessage();
} catch (NetworkException $e) {
    // Handle network errors
    echo "Network error: " . $e->getMessage();
}
```

## Testing

Run tests for the {{driver_name}} driver:

```bash
# Run all tests
vendor/bin/phpunit tests/Drivers/{{driver_class}}Test.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Drivers/{{driver_class}}Test.php
```

## Examples

### Example 1: Check Balance

```php
use Blockchain\BlockchainManager;

$blockchain = new BlockchainManager('{{driver_lowercase}}', [
    'endpoint' => '{{default_endpoint}}'
]);

$address = 'your_wallet_address';
$balance = $blockchain->getBalance($address);

echo "Address: $address\n";
echo "Balance: $balance {{native_currency}}\n";
```

### Example 2: Get Transaction Details

```php
use Blockchain\BlockchainManager;

$blockchain = new BlockchainManager('{{driver_lowercase}}', [
    'endpoint' => '{{default_endpoint}}'
]);

$txHash = 'transaction_hash';
$transaction = $blockchain->getTransaction($txHash);

echo "Transaction: $txHash\n";
echo "From: {$transaction['from']}\n";
echo "To: {$transaction['to']}\n";
echo "Amount: {$transaction['amount']}\n";
```

### Example 3: Monitor Network

```php
use Blockchain\BlockchainManager;

$blockchain = new BlockchainManager('{{driver_lowercase}}', [
    'endpoint' => '{{default_endpoint}}'
]);

$info = $blockchain->getNetworkInfo();

echo "Network Information:\n";
echo "Chain ID: {$info['chain_id']}\n";
echo "Latest Block: {$info['block_number']}\n";
```

## Resources

- Official {{blockchain_name}} Documentation: {{official_docs_url}}
- RPC API Reference: {{rpc_docs_url}}
- Block Explorer: {{block_explorer_url}}

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for contribution guidelines.

## License

MIT License. See [LICENSE](../../LICENSE) for details.
