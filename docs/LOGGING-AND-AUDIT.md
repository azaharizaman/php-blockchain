# Secure Logging and Audit Trails

This document explains how to use the secure logging and audit trail features in the PHP Blockchain Integration Layer.

## Overview

The package provides two key security features:

1. **Redacting Logger**: A PSR-3 compliant logger that automatically masks sensitive data before logging
2. **Audit Recorder**: A system for recording critical operations for security and compliance auditing

## Redacting Logger

### Purpose

The `RedactingLogger` prevents accidental exposure of sensitive information like private keys, passwords, API tokens, and secrets in log files. It acts as a wrapper around any PSR-3 logger and redacts sensitive fields before passing log messages to the underlying logger.

### Usage

```php
use Blockchain\Logging\RedactingLogger;
use Blockchain\Logging\NullLogger;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create an underlying PSR-3 logger (e.g., Monolog)
$monolog = new Logger('blockchain');
$monolog->pushHandler(new StreamHandler('app.log', Logger::INFO));

// Wrap with RedactingLogger
$logger = new RedactingLogger($monolog);

// Log with sensitive data - it will be automatically redacted
$logger->info('Transaction signed', [
    'transaction_id' => 'tx-123',
    'private_key' => '0x1234567890abcdef',  // Will be masked as ***REDACTED***
    'amount' => 1000,                        // Will remain visible
]);

// For non-production environments where logging is disabled
$nullLogger = new NullLogger();
```

### Default Redacted Fields

The following field names are automatically redacted (case-insensitive):

- `private_key`, `privateKey`, `private-key`
- `secret`, `client_secret`, `api_secret`
- `password`, `pass`, `pwd`
- `token`, `api_key`, `access_token`, `refresh_token`
- `seed`, `mnemonic`, `seed_phrase`, `recovery_phrase`
- `signature`
- `auth_token`, `authorization`, `bearer`

### Custom Redaction

You can add additional fields to redact:

```php
// Add custom fields to the redaction list
$logger = new RedactingLogger($monolog, ['custom_secret', 'internal_token']);

// Or add fields dynamically
$logger->addRedactedFields(['another_secret']);

// Use a custom redaction mask
$logger = new RedactingLogger($monolog, [], '[HIDDEN]');
```

### Configuration

```php
use Blockchain\Logging\RedactingLogger;

$logger = new RedactingLogger(
    $underlyingLogger,
    additionalFields: ['custom_field'],  // Additional fields to redact
    redactionMask: '***REDACTED***',     // Mask for redacted values
    deepRedaction: true                   // Enable nested array redaction
);
```

## Audit Recorder

### Purpose

The `AuditRecorderInterface` provides a contract for recording critical operations in your blockchain application. This is essential for:

- Security monitoring and incident response
- Compliance requirements (SOC 2, GDPR, etc.)
- Troubleshooting and debugging
- Tracking key lifecycle operations

### Usage

```php
use Blockchain\Audit\FileAuditRecorder;
use Blockchain\Audit\NullAuditRecorder;

// For production: Use file-based recorder (or implement custom DB recorder)
$auditor = new FileAuditRecorder('/var/log/blockchain-audit.log');

// For testing/development: Use null recorder
$auditor = new NullAuditRecorder();

// Record audit events
$auditor->record('key.created', 'user-123', [
    'key_id' => 'key-abc',
    'algorithm' => 'secp256k1',
    'created_at' => date('Y-m-d H:i:s'),
]);

$auditor->record('transaction.sent', 'api-service', [
    'transaction_id' => 'tx-456',
    'to_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'amount' => 1000,
    'network' => 'ethereum',
]);
```

### Event Types

Common audit event types:

- **Key Operations**: `key.created`, `key.imported`, `key.rotated`, `key.deleted`
- **Transaction Operations**: `transaction.sent`, `transaction.signed`, `transaction.batch.created`
- **Configuration**: `config.updated`, `config.imported`
- **Idempotency**: `idempotency.key.created`, `idempotency.key.invalidated`
- **Authentication**: `auth.login`, `auth.logout`, `auth.failed`

### Retrieving Audit Events

```php
// Get events within a time range
$startTime = new DateTimeImmutable('-7 days');
$endTime = new DateTimeImmutable('now');

$events = $auditor->getEvents($startTime, $endTime);

// Filter by event type
$keyEvents = $auditor->getEvents($startTime, $endTime, 'key.created');

// Filter by actor
$userEvents = $auditor->getEvents($startTime, $endTime, null, 'user-123');

// Combined filters
$specificEvents = $auditor->getEvents(
    $startTime,
    $endTime,
    'transaction.sent',
    'api-service'
);
```

### Audit Log Retention

```php
// Purge events older than 90 days (for compliance)
$retentionDate = new DateTimeImmutable('-90 days');
$purgedCount = $auditor->purgeOldEvents($retentionDate);

echo "Purged {$purgedCount} old audit events\n";
```

## Integration Examples

### Integrating with BlockchainManager

```php
use Blockchain\BlockchainManager;
use Blockchain\Logging\RedactingLogger;
use Blockchain\Audit\FileAuditRecorder;

// Create logger and auditor
$logger = new RedactingLogger($monolog);
$auditor = new FileAuditRecorder('/var/log/blockchain-audit.log');

// Create blockchain manager
$blockchain = new BlockchainManager('ethereum', [
    'rpc_url' => 'https://mainnet.infura.io/v3/YOUR_KEY',
    'logger' => $logger,      // Optional: inject logger
    'auditor' => $auditor,    // Optional: inject auditor
]);

// Operations will be logged and audited automatically
$balance = $blockchain->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
```

### Integrating with Key Management

```php
use Blockchain\Security\EnvSecretProvider;
use Blockchain\Logging\RedactingLogger;
use Blockchain\Audit\FileAuditRecorder;

$logger = new RedactingLogger($monolog);
$auditor = new FileAuditRecorder('/var/log/blockchain-audit.log');

$secretProvider = new EnvSecretProvider();

// Before retrieving a key, audit the operation
$auditor->record('key.accessed', 'service-worker', [
    'key_name' => 'ETHEREUM_PRIVATE_KEY',
    'purpose' => 'transaction_signing',
]);

try {
    $privateKey = $secretProvider->get('ETHEREUM_PRIVATE_KEY');
    
    // Log success (without the key value)
    $logger->info('Successfully retrieved private key', [
        'key_name' => 'ETHEREUM_PRIVATE_KEY',
    ]);
} catch (\Exception $e) {
    $logger->error('Failed to retrieve private key', [
        'key_name' => 'ETHEREUM_PRIVATE_KEY',
        'error' => $e->getMessage(),
    ]);
}
```

## Best Practices

### Logging Best Practices

1. **Always use RedactingLogger in production** - Never log sensitive data directly
2. **Use appropriate log levels** - DEBUG for development, INFO/WARNING/ERROR for production
3. **Include context** - Add relevant non-sensitive context to help with debugging
4. **Never log raw secrets** - Even with RedactingLogger, avoid including secret values in context

```php
// ❌ BAD: Logging secret directly
$logger->info("Using key: {$privateKey}");

// ✅ GOOD: Logging reference to secret
$logger->info('Using private key', ['key_name' => 'ETHEREUM_PRIVATE_KEY']);
```

### Audit Best Practices

1. **Record all critical operations** - Key creation, rotation, deletion, and high-value transactions
2. **Include sufficient context** - Enough information to reconstruct what happened
3. **Never include secrets** - Audit logs should not contain private keys, passwords, etc.
4. **Use consistent event naming** - Follow a naming convention like `resource.action`
5. **Implement retention policies** - Regularly purge old audit events based on compliance requirements

```php
// ❌ BAD: Including sensitive data in audit
$auditor->record('key.created', 'user-123', [
    'key_id' => 'key-abc',
    'private_key' => '0x1234...',  // Never include this!
]);

// ✅ GOOD: Only non-sensitive metadata
$auditor->record('key.created', 'user-123', [
    'key_id' => 'key-abc',
    'algorithm' => 'secp256k1',
    'purpose' => 'transaction_signing',
]);
```

## Production Recommendations

### For Production Use

1. **Use a robust logging backend** (not NullLogger):
   - Monolog with appropriate handlers
   - Cloud logging services (AWS CloudWatch, Azure Monitor, GCP Cloud Logging)
   - Centralized logging (ELK Stack, Splunk, Datadog)

2. **Use a robust audit backend** (not FileAuditRecorder):
   - Database-backed audit log (PostgreSQL, MySQL)
   - Immutable audit storage (AWS DynamoDB, Azure Cosmos DB)
   - Dedicated audit services (AWS CloudTrail, Azure Monitor)

3. **Configure log rotation and retention**:
   - Use logrotate for file-based logs
   - Configure retention policies in cloud services
   - Implement purging for audit logs

4. **Monitor and alert on audit events**:
   - Alert on suspicious patterns (e.g., multiple failed key accesses)
   - Monitor for high-value transactions
   - Track unusual access patterns

### Example Production Setup

```php
use Blockchain\Logging\RedactingLogger;
use Blockchain\Audit\FileAuditRecorder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Production logger with rotation
$monolog = new Logger('blockchain');
$monolog->pushHandler(
    new RotatingFileHandler(
        '/var/log/blockchain/app.log',
        30,  // Keep 30 days
        Logger::INFO
    )
);

$logger = new RedactingLogger($monolog);

// Production auditor (consider implementing DatabaseAuditRecorder)
$auditor = new FileAuditRecorder('/var/log/blockchain/audit.log');

// In a production system, implement a cron job to purge old audits
// Example: purge events older than 90 days
$retentionDate = new DateTimeImmutable('-90 days');
$auditor->purgeOldEvents($retentionDate);
```

## Testing

The package includes comprehensive test suites for both logging and audit functionality:

```bash
# Run all tests
composer test

# Run only logging tests
vendor/bin/phpunit tests/Logging/

# Run only audit tests
vendor/bin/phpunit tests/Audit/
```

## Security Considerations

1. **Log files are sensitive** - Protect log files with appropriate file permissions (e.g., 0600)
2. **Audit logs are critical** - Ensure audit logs are tamper-proof and backed up regularly
3. **Network exposure** - Never expose log files or audit logs over HTTP
4. **Access control** - Restrict access to logs and audit records to authorized personnel only
5. **Encryption** - Consider encrypting log files at rest for additional security

## License

This logging and audit system is part of the PHP Blockchain Integration Layer and is licensed under the MIT License.
