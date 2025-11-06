# Exceptions

This directory contains custom exception classes for the PHP Blockchain Integration Layer.

## Exception Hierarchy

All exceptions extend the base `\Exception` class and are in the `Blockchain\Exceptions` namespace.

```
\Exception
├── ConfigurationException
├── UnsupportedDriverException
├── TransactionException
└── ValidationException
```

## Available Exceptions

### ConfigurationException

**Thrown when:** Driver configuration is invalid or missing.

**Common scenarios:**
- Required configuration parameters are missing
- Configuration values are invalid or malformed
- A driver is not properly configured before use

**Example:**
```php
use Blockchain\BlockchainManager;
use Blockchain\Exceptions\ConfigurationException;

try {
    $manager = new BlockchainManager();
    // Trying to use manager without setting a driver
    $balance = $manager->getBalance('address');
} catch (ConfigurationException $e) {
    echo "Configuration error: " . $e->getMessage();
}
```

### UnsupportedDriverException

**Thrown when:** Requested driver is not registered or available.

**Common scenarios:**
- Attempting to use a blockchain driver that hasn't been registered
- Requesting a driver that doesn't exist in the registry
- Trying to switch to a driver that was never loaded

**Example:**
```php
use Blockchain\BlockchainManager;
use Blockchain\Exceptions\UnsupportedDriverException;

try {
    // Trying to use a non-existent driver
    $blockchain = new BlockchainManager('nonexistent', [
        'endpoint' => 'https://example.com'
    ]);
} catch (UnsupportedDriverException $e) {
    echo "Driver not supported: " . $e->getMessage();
}
```

### TransactionException

**Thrown when:** Transaction operations fail or are invalid.

**Common scenarios:**
- Transaction submission fails
- Transaction validation fails
- Transaction signing errors occur
- Insufficient funds for transaction
- Gas estimation fails

**Additional methods:**
- `setTransactionHash(?string $hash): self` - Store transaction hash
- `getTransactionHash(): ?string` - Retrieve transaction hash

**Example:**
```php
use Blockchain\BlockchainManager;
use Blockchain\Exceptions\TransactionException;

try {
    $blockchain = new BlockchainManager('solana', [
        'endpoint' => 'https://api.mainnet-beta.solana.com'
    ]);
    $txHash = $blockchain->sendTransaction('from', 'to', 1.5);
} catch (TransactionException $e) {
    echo "Transaction failed: " . $e->getMessage();
    if ($e->getTransactionHash()) {
        echo "Transaction hash: " . $e->getTransactionHash();
    }
}
```

### ValidationException

**Thrown when:** Input validation fails (addresses, amounts, etc.).

**Common scenarios:**
- Invalid blockchain addresses are provided
- Transaction amounts are invalid or out of range
- Driver class validation fails
- Input parameters don't meet required constraints

**Additional methods:**
- `setErrors(array $errors): self` - Store validation errors
- `getErrors(): array` - Retrieve validation errors

**Example:**
```php
use Blockchain\Registry\DriverRegistry;
use Blockchain\Exceptions\ValidationException;

try {
    $registry = new DriverRegistry();
    // Trying to register a class that doesn't implement the interface
    $registry->registerDriver('invalid', 'NonExistentClass');
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
    $errors = $e->getErrors();
    if (!empty($errors)) {
        print_r($errors);
    }
}
```

## Comprehensive Error Handling

For robust error handling, catch exceptions in order from most specific to least specific:

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
    
    $txHash = $blockchain->sendTransaction($from, $to, $amount);
    
} catch (ValidationException $e) {
    // Handle validation errors
    echo "Validation failed: " . $e->getMessage();
    foreach ($e->getErrors() as $field => $error) {
        echo "  - {$field}: {$error}\n";
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
    // Handle unsupported driver errors
    echo "Driver error: " . $e->getMessage();
} catch (\Exception $e) {
    // Handle any other errors
    echo "Unexpected error: " . $e->getMessage();
}
```

## Future Exceptions

Planned exceptions for future releases:
- **NetworkException**: Network-related errors (timeouts, connectivity issues)
- **AuthenticationException**: Authentication and signing errors