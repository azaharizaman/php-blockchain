# Utilities

This directory contains helper classes and utilities for the PHP Blockchain Integration Layer.

## Planned Utilities

- **AddressValidator**: Validate blockchain addresses for different networks
- **FormatConverter**: Convert between different data formats (hex, base58, etc.)
- **TransactionBuilder**: Helper for building transactions
- **KeyGenerator**: Generate keypairs for different blockchain networks
- **HashHelper**: Cryptographic hash functions

## Usage

Utilities will be available under the `Blockchain\Utils` namespace:

```php
use Blockchain\Utils\AddressValidator;
use Blockchain\Utils\FormatConverter;

$isValid = AddressValidator::validateSolanaAddress($address);
$hex = FormatConverter::base58ToHex($base58String);
```