# Utilities

This directory contains helper classes and utilities for the PHP Blockchain Integration Layer.

## Available Utilities

### AddressValidator
Validate blockchain addresses for different networks.

```php
use Blockchain\Utils\AddressValidator;

$isValid = AddressValidator::isValid($address, 'solana');
$normalized = AddressValidator::normalize($address);
```

### Serializer
Data serialization and deserialization utilities.

```php
use Blockchain\Utils\Serializer;

$json = Serializer::toJson(['key' => 'value']);
$data = Serializer::fromJson($json);
$base64 = Serializer::toBase64($string);
$decoded = Serializer::fromBase64($base64);
```

### Abi
ABI (Application Binary Interface) encoding and decoding for Ethereum smart contracts.

```php
use Blockchain\Utils\Abi;

// Generate function selector
$selector = Abi::getFunctionSelector('balanceOf(address)');
// Returns: '0x70a08231'

// Encode function call
$data = Abi::encodeFunctionCall('transfer(address,uint256)', [
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '1000000000000000000'
]);

// Decode response
$balance = Abi::decodeResponse('uint256', $responseData);

// ERC-20 helpers
$data = Abi::encodeBalanceOf($address);
$data = Abi::encodeTransfer($to, $amount);
```

#### Supported Types

**Encoding:**
- `address` - Ethereum addresses
- `uint256` - Unsigned 256-bit integers
- `bool` - Boolean values
- `string` - String values

**Decoding:**
- `uint256` - Returns decimal string (preserves precision)
- `address` - Returns lowercase address with 0x prefix
- `bool` - Returns PHP boolean
- `string` - Returns decoded string

### Keccak
Keccak-256 hash function for Ethereum (different from SHA3-256).

```php
use Blockchain\Utils\Keccak;

$hash = Keccak::hash('balanceOf(address)');
// Returns: '70a08231b98ef4ca268c9cc3f6b4590e4bfec28280db06bb5d45e689f2a360be'
```

## Planned Utilities

- **FormatConverter**: Convert between different data formats (hex, base58, etc.)
- **TransactionBuilder**: Helper for building transactions
- **KeyGenerator**: Generate keypairs for different blockchain networks

## Usage

Utilities are available under the `Blockchain\Utils` namespace:

```php
use Blockchain\Utils\AddressValidator;
use Blockchain\Utils\Abi;
use Blockchain\Utils\Serializer;

$isValid = AddressValidator::isValid($address, 'solana');
$encoded = Abi::encodeBalanceOf($address);
$json = Serializer::toJson($data);
```