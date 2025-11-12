# Endpoint Validation

This guide explains how to use the `EndpointValidator` utility to validate custom RPC endpoint reachability and functionality before use in production.

## Overview

The `EndpointValidator` class provides two validation modes:

1. **Dry-Run Mode**: Validates URL format without making network calls
2. **Live Mode**: Performs actual network requests to verify endpoint accessibility

## Installation

The endpoint validator is included in the `Blockchain\Utils` namespace:

```php
use Blockchain\Utils\EndpointValidator;
use Blockchain\Utils\ValidationResult;
```

## Basic Usage

### Dry-Run Validation

Dry-run validation checks URL format without making any network calls. This is useful for:
- Quick validation during configuration
- Pre-flight checks before live validation
- Validating user input without network overhead

```php
use Blockchain\Utils\EndpointValidator;

$validator = new EndpointValidator();

// Validate URL format only (no network calls)
$result = $validator->validateDryRun('https://api.mainnet-beta.solana.com');

if ($result->isValid()) {
    echo "URL format is valid\n";
} else {
    echo "Invalid URL: " . $result->getError() . "\n";
}
```

**Dry-run validation checks:**
- URL can be parsed
- Scheme is `http`, `https`, or `wss`
- Host is present

### Live Validation

Live validation performs actual HTTP requests to verify endpoint accessibility:

```php
use Blockchain\Utils\EndpointValidator;

$validator = new EndpointValidator();

// Perform live HTTP validation
$result = $validator->validate('https://api.mainnet-beta.solana.com');

if ($result->isValid()) {
    echo sprintf(
        "Endpoint is accessible (latency: %.3f seconds)\n",
        $result->getLatency()
    );
} else {
    echo "Endpoint validation failed: " . $result->getError() . "\n";
}
```

## RPC Ping Validation

For blockchain RPC endpoints, you can perform a more thorough validation by sending an actual RPC request:

### Ethereum RPC Ping

```php
$validator = new EndpointValidator();

$result = $validator->validate('https://eth-mainnet.g.alchemy.com/v2/your-api-key', [
    'rpc-ping' => true,
    'blockchain' => 'ethereum'
]);

if ($result->isValid()) {
    echo "Ethereum endpoint is working correctly\n";
    echo sprintf("Response time: %.3f seconds\n", $result->getLatency());
} else {
    echo "RPC ping failed: " . $result->getError() . "\n";
}
```

The Ethereum RPC ping sends an `eth_chainId` request to verify the endpoint can handle JSON-RPC calls.

### Solana RPC Ping

```php
$validator = new EndpointValidator();

$result = $validator->validate('https://api.mainnet-beta.solana.com', [
    'rpc-ping' => true,
    'blockchain' => 'solana'
]);

if ($result->isValid()) {
    echo "Solana endpoint is working correctly\n";
    echo sprintf("Response time: %.3f seconds\n", $result->getLatency());
} else {
    echo "RPC ping failed: " . $result->getError() . "\n";
}
```

The Solana RPC ping sends a `getHealth` request to verify the endpoint is operational.

## Validation Options

The `validate()` method accepts an options array:

| Option | Type | Description |
|--------|------|-------------|
| `rpc-ping` | `bool` | Enable RPC ping validation (default: `false`) |
| `blockchain` | `string` | Blockchain type for RPC ping: `'ethereum'` or `'solana'` |

## ValidationResult Object

Both validation methods return a `ValidationResult` object with these methods:

### `isValid(): bool`

Returns whether the endpoint passed validation.

```php
if ($result->isValid()) {
    // Endpoint is valid
}
```

### `getLatency(): ?float`

Returns the latency measured during validation in seconds, or `null` if not measured (dry-run mode).

```php
if ($result->isValid()) {
    $latency = $result->getLatency();
    echo sprintf("Latency: %.3f seconds\n", $latency);
}
```

### `getError(): ?string`

Returns the error message if validation failed, or `null` if successful.

```php
if (!$result->isValid()) {
    echo "Error: " . $result->getError() . "\n";
}
```

## Common Scenarios

### Validating Multiple Endpoints

```php
$validator = new EndpointValidator();

$endpoints = [
    'https://api.mainnet-beta.solana.com',
    'https://eth-mainnet.g.alchemy.com/v2/key',
    'https://api.devnet.solana.com',
];

foreach ($endpoints as $endpoint) {
    $result = $validator->validate($endpoint);
    
    if ($result->isValid()) {
        echo "✓ {$endpoint} - OK ({$result->getLatency()}s)\n";
    } else {
        echo "✗ {$endpoint} - FAILED: {$result->getError()}\n";
    }
}
```

### Pre-Production Endpoint Check

```php
use Blockchain\Utils\EndpointValidator;

function checkEndpointBeforeUse(string $endpoint): bool
{
    $validator = new EndpointValidator();
    
    // First, quick format check
    $dryRunResult = $validator->validateDryRun($endpoint);
    if (!$dryRunResult->isValid()) {
        error_log("Invalid endpoint format: " . $dryRunResult->getError());
        return false;
    }
    
    // Then, live validation with RPC ping
    $liveResult = $validator->validate($endpoint, [
        'rpc-ping' => true,
        'blockchain' => 'solana'
    ]);
    
    if (!$liveResult->isValid()) {
        error_log("Endpoint not accessible: " . $liveResult->getError());
        return false;
    }
    
    if ($liveResult->getLatency() > 2.0) {
        error_log("Warning: High latency detected ({$liveResult->getLatency()}s)");
    }
    
    return true;
}

// Use in your application
if (checkEndpointBeforeUse($userProvidedEndpoint)) {
    // Safe to use endpoint
    $blockchain = new BlockchainManager('solana', [
        'endpoint' => $userProvidedEndpoint
    ]);
}
```

### Configuration Validation

```php
use Blockchain\Utils\EndpointValidator;

class ConfigValidator
{
    public static function validateBlockchainConfig(array $config): array
    {
        $errors = [];
        $validator = new EndpointValidator();
        
        if (isset($config['endpoint'])) {
            $result = $validator->validateDryRun($config['endpoint']);
            if (!$result->isValid()) {
                $errors[] = "Invalid endpoint: " . $result->getError();
            }
        } else {
            $errors[] = "Missing required 'endpoint' configuration";
        }
        
        return $errors;
    }
}

// Validate configuration before use
$errors = ConfigValidator::validateBlockchainConfig($config);
if (!empty($errors)) {
    throw new \InvalidArgumentException(
        "Configuration validation failed: " . implode(", ", $errors)
    );
}
```

## Testing with Mock Adapter

For unit testing, you can inject a mock adapter:

```php
use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Utils\EndpointValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

// Create mock handler
$mockHandler = new MockHandler([
    new Response(200, [], json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => 'ok'
    ]))
]);

$handlerStack = HandlerStack::create($mockHandler);
$client = new Client(['handler' => $handlerStack]);
$adapter = new GuzzleAdapter($client);

// Inject mock adapter
$validator = new EndpointValidator($adapter);

// Test with mocked responses
$result = $validator->validate('https://test.example.com', [
    'rpc-ping' => true,
    'blockchain' => 'solana'
]);

assert($result->isValid());
```

## Error Handling

The validator handles various error conditions:

- **Invalid URL format**: Returns immediately with descriptive error
- **Network errors**: Catches connection failures and timeouts
- **HTTP errors**: Handles 4xx and 5xx status codes
- **Invalid RPC responses**: Validates JSON-RPC structure
- **RPC errors**: Extracts error messages from RPC error responses

Example error handling:

```php
$validator = new EndpointValidator();
$result = $validator->validate($endpoint);

if (!$result->isValid()) {
    $error = $result->getError();
    
    if (str_contains($error, 'Network error')) {
        // Handle network connectivity issues
        echo "Network connectivity problem\n";
    } elseif (str_contains($error, 'RPC error')) {
        // Handle RPC-specific errors
        echo "RPC endpoint error\n";
    } else {
        // Handle other errors
        echo "Validation failed: {$error}\n";
    }
}
```

## Performance Considerations

- **Dry-run mode**: Extremely fast (< 1ms), suitable for high-frequency validation
- **Live mode**: Depends on network latency (typically 10-500ms)
- **RPC ping mode**: Similar to live mode, with additional RPC processing overhead

**Recommendations:**
- Use dry-run for input validation and quick checks
- Use live validation sparingly, cache results when possible
- Consider implementing a validation cache for frequently used endpoints
- Set appropriate timeouts for production environments

## Best Practices

1. **Always validate user-provided endpoints** before use in production
2. **Use dry-run first** for quick format validation, then live validation if needed
3. **Cache validation results** to avoid repeated network calls
4. **Monitor latency** and set thresholds for acceptable response times
5. **Log validation failures** for debugging and monitoring
6. **Inject mock adapters** in tests to avoid real network calls

## Related Documentation

- [Configuration Guide](configuration.md) - Managing blockchain configurations
- [CLI Tools](cli-tools.md) - Command-line utilities for blockchain operations
- [Telemetry](telemetry.md) - Monitoring and metrics collection
