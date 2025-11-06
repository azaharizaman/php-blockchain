# Exceptions

This directory contains custom exception classes for the PHP Blockchain Integration Layer.

## Available Exceptions

- **UnsupportedDriverException**: Thrown when trying to use an unsupported blockchain driver
- **ConfigurationException**: Thrown when configuration is invalid or missing

## Planned Exceptions

- **NetworkException**: Network-related errors
- **TransactionException**: Transaction-related errors
- **ValidationException**: Input validation errors
- **AuthenticationException**: Authentication and signing errors

## Usage

```php
use Blockchain\Exceptions\UnsupportedDriverException;
use Blockchain\Exceptions\ConfigurationException;

try {
    $manager = new BlockchainManager('unsupported', $config);
} catch (UnsupportedDriverException $e) {
    // Handle unsupported driver
} catch (ConfigurationException $e) {
    // Handle configuration error
}
```