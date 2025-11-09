# Coding Guidelines for PHP Blockchain Integration Layer

This document provides coding standards and best practices for contributing to the PHP Blockchain Integration Layer project. These guidelines ensure consistency, maintainability, and high code quality across the codebase.

## Table of Contents

1. [Code Organization](#code-organization)
2. [Interface Design](#interface-design)
3. [Testing Best Practices](#testing-best-practices)
4. [Documentation Standards](#documentation-standards)
5. [Security Considerations](#security-considerations)
6. [Common Pitfalls](#common-pitfalls)

## Code Organization

### Unreachable Code

**Issue**: Code placed after unconditional throw/return statements will never execute.

**Rule**: Always place comments and documentation BEFORE throw statements, not after.

**Bad Example**:
```php
throw new SecurityException('Not implemented');

// This comment is unreachable
// Example implementation:
// 1. Do something
```

**Good Example**:
```php
// Example implementation:
// 1. Do something
// 2. Return result

throw new SecurityException('Not implemented');
```

## Interface Design

### Liskov Substitution Principle (LSP)

**Issue**: Adding public methods to concrete classes that aren't in the interface breaks polymorphism.

**Rule**: Public methods that are part of the class's core contract should be defined in an interface. If a method is implementation-specific, document why it's not in the interface.

**Bad Example**:
```php
interface SecretProviderInterface {
    public function get(string $name): string;
}

class HsmSecretProvider implements SecretProviderInterface {
    public function get(string $name): string { /* ... */ }
    
    // This breaks LSP - can't be used polymorphically
    public function sign(string $key, string $data): string { /* ... */ }
}
```

**Good Example**:
```php
interface SecretProviderInterface {
    public function get(string $name): string;
}

interface SigningProviderInterface extends SecretProviderInterface {
    public function sign(string $key, string $data): string;
}

class HsmSecretProvider implements SigningProviderInterface {
    public function get(string $name): string { /* ... */ }
    public function sign(string $key, string $data): string { /* ... */ }
}
```

**Alternative** (for truly implementation-specific methods):
```php
class HsmSecretProvider implements SecretProviderInterface {
    public function get(string $name): string { /* ... */ }
    
    /**
     * Get sanitized configuration.
     * 
     * NOTE: This method is HSM-specific and not part of SecretProviderInterface
     * because it's for debugging/logging HSM connections. Other providers like
     * EnvSecretProvider don't have configuration objects, making this method
     * inappropriate for the general interface.
     */
    public function getConfig(): array { /* ... */ }
}
```

## Testing Best Practices

### Avoid Testing Interface Compliance

**Issue**: Tests that verify interface implementation are redundant because PHP's type system enforces this at compile-time.

**Rule**: Don't write tests that only check if methods exist. Test behavioral aspects instead.

**Bad Example**:
```php
public function testAllProvidersImplementGet(): void
{
    $providers = [new EnvSecretProvider(), new HsmSecretProvider()];
    
    foreach ($providers as $provider) {
        // This is redundant - PHP already enforces this
        $this->assertTrue(method_exists($provider, 'get'));
    }
}
```

**Good Example**:
```php
public function testAllProvidersThrowExceptionForMissingSecrets(): void
{
    $providers = [new EnvSecretProvider(), new HsmSecretProvider(['endpoint' => 'test'])];
    
    foreach ($providers as $provider) {
        $this->expectException(SecurityException::class);
        $provider->get('NONEXISTENT_SECRET');
    }
}
```

### Avoid Test Duplication

**Issue**: Duplicating tests across test files reduces maintainability.

**Rule**: Test each behavior in one place. Use the most appropriate test file:
- Exception-specific tests → `tests/Exceptions/ExceptionTest.php`
- Provider-specific tests → `tests/Security/SecretProviderTest.php`

**Bad Example**:
```php
// In tests/Security/SecretProviderTest.php
public function testSecurityExceptionCanBeThrown(): void {
    $this->expectException(SecurityException::class);
    throw new SecurityException('Test');
}

// Also in tests/Exceptions/ExceptionTest.php (DUPLICATE!)
public function testSecurityExceptionCanBeThrown(): void {
    $this->expectException(SecurityException::class);
    throw new SecurityException('Test');
}
```

**Good Example**:
```php
// ONLY in tests/Exceptions/ExceptionTest.php
public function testSecurityExceptionCanBeThrown(): void {
    $this->expectException(SecurityException::class);
    throw new SecurityException('Test');
}

// In tests/Security/SecretProviderTest.php - test provider behavior
public function testProviderThrowsSecurityExceptionForMissingSecret(): void {
    $provider = new EnvSecretProvider();
    $this->expectException(SecurityException::class);
    $provider->get('MISSING');
}
```

## Documentation Standards

### PHPDoc Comments

**Rule**: All public methods must have complete PHPDoc comments including:
- Brief description
- `@param` for each parameter with type and description
- `@return` with type and description
- `@throws` for each exception that can be thrown
- Usage examples for complex APIs

**Example**:
```php
/**
 * Retrieve a secret value from the HSM/KeyVault.
 *
 * This method returns a key reference/identifier that can be used with
 * HSM signing operations. It does NOT return the actual private key.
 *
 * @param string $name The key identifier in the HSM/KeyVault
 *
 * @throws SecurityException If the key reference cannot be retrieved
 *
 * @return string The key reference/identifier (not the actual key material)
 */
public function get(string $name): string
{
    // Implementation
}
```

### Implementation-Specific Documentation

**Rule**: When a public method is NOT part of an interface, document why.

**Example**:
```php
/**
 * Get the HSM/KeyVault configuration (sanitized).
 *
 * Returns configuration with sensitive values redacted.
 * 
 * NOTE: This method is HSM-specific and not part of SecretProviderInterface
 * because it's primarily used for debugging and logging HSM connection details.
 * Other providers like EnvSecretProvider don't have configuration objects to
 * expose, making this method inappropriate for the general interface.
 *
 * @return array<string,mixed> Sanitized configuration
 */
public function getConfig(): array { /* ... */ }
```

## Security Considerations

### Secret Handling

**Rule**: Never log, print, or expose secrets in error messages, stack traces, or debug output.

**Bad Example**:
```php
throw new SecurityException("Failed to load secret: " . $secretValue);
```

**Good Example**:
```php
throw new SecurityException("Failed to load secret: " . $secretName);
```

### Configuration Sanitization

**Rule**: Always sanitize configuration before logging or returning it for debugging.

**Example**:
```php
public function getConfig(): array
{
    $sanitized = $this->config;
    
    // Redact sensitive fields
    $sensitiveFields = ['client_secret', 'password', 'api_key', 'token'];
    foreach ($sensitiveFields as $field) {
        if (isset($sanitized[$field])) {
            $sanitized[$field] = '***REDACTED***';
        }
    }
    
    return $sanitized;
}
```

### HSM/KeyVault Integration

**Rule**: When implementing HSM/KeyVault integrations:
1. Never return raw private key material if possible
2. Use key references/identifiers instead
3. Perform cryptographic operations within the HSM boundary
4. Document security assumptions clearly

**Example**:
```php
/**
 * IMPORTANT SECURITY NOTES:
 * - This provider should NOT return raw private key material when possible
 * - Instead, it should provide signing operations that use keys stored in the HSM
 * - Private keys should remain within the HSM/KeyVault boundary
 * - Use key identifiers or references instead of actual key material
 */
```

## Common Pitfalls

### 1. Unreachable Code After Throw
- ❌ Placing comments after unconditional throw statements
- ✅ Place all documentation before the throw

### 2. Breaking Interface Contracts
- ❌ Adding public methods to implementations without considering the interface
- ✅ Extend interfaces or document why a method is implementation-specific

### 3. Redundant Tests
- ❌ Testing that methods exist (PHP's type system already enforces this)
- ✅ Test behavioral aspects and edge cases

### 4. Duplicate Tests
- ❌ Testing the same behavior in multiple test files
- ✅ Test each behavior once in the most appropriate location

### 5. Exposing Secrets
- ❌ Logging secret values in error messages or debug output
- ✅ Only log secret names/identifiers, never values

### 6. Missing Documentation
- ❌ Public methods without PHPDoc comments
- ✅ Complete PHPDoc for all public APIs

### 7. Regex Pattern Escaping
- ❌ Using `preg_quote()` without specifying the delimiter parameter
- ✅ Always specify the delimiter when using `preg_quote()` for regex patterns

**Bad Example**:
```php
$escapedFields = array_map('preg_quote', $this->fields);
$pattern = '/(' . implode('|', $escapedFields) . ')/';
```

**Good Example**:
```php
$escapedFields = array_map(fn($field) => preg_quote($field, '/'), $this->fields);
$pattern = '/(' . implode('|', $escapedFields) . ')/';
```

**Why**: Without specifying the delimiter, special regex characters in field names won't be properly escaped for use within the regex delimiters, potentially causing regex compilation errors or security issues.

### 8. Type Safety with Array Keys
- ❌ Assuming array keys are always strings
- ✅ Check key types before passing to string-typed parameters

**Bad Example**:
```php
foreach ($context as $key => $value) {
    if ($this->shouldRedactField($key)) { // $key might be int
        // ...
    }
}

private function shouldRedactField(string $fieldName): bool { /* ... */ }
```

**Good Example**:
```php
foreach ($context as $key => $value) {
    if (is_string($key) && $this->shouldRedactField($key)) {
        // ...
    }
}

private function shouldRedactField(string $fieldName): bool { /* ... */ }
```

**Why**: In PHP, array keys can be integers. When strict types are enabled, passing an integer to a string-typed parameter causes a TypeError.

### 9. Sleep in Unit Tests
- ❌ Using `sleep()` to create time delays in unit tests
- ✅ Mock timestamps or write data with predetermined timestamps

**Bad Example**:
```php
public function testTimeBasedOperation(): void
{
    $recorder->record('event.1', 'actor-1', []);
    sleep(2); // Slow test!
    $recorder->record('event.2', 'actor-2', []);
    
    // Test time-based retrieval
}
```

**Good Example**:
```php
public function testTimeBasedOperation(): void
{
    // Write events with specific timestamps
    $event1 = json_encode([
        'timestamp' => '2025-01-01T10:00:00.000+00:00',
        'event_type' => 'event.1',
        'actor' => 'actor-1',
    ]);
    
    $event2 = json_encode([
        'timestamp' => '2025-02-01T10:00:00.000+00:00',
        'event_type' => 'event.2',
        'actor' => 'actor-2',
    ]);
    
    file_put_contents($file, $event1 . PHP_EOL . $event2 . PHP_EOL);
    
    // Test time-based retrieval
}
```

**Why**: Using `sleep()` makes tests unnecessarily slow and can lead to flaky tests. Setting timestamps programmatically is faster and more reliable.

### 10. Directory Cleanup in Tests
- ❌ Attempting to remove system directories or non-existent directories
- ✅ Only remove directories you created and verify they exist and are empty

**Bad Example**:
```php
$tempFile = sys_get_temp_dir() . '/test-' . uniqid() . '.log';
// ... use file ...
unlink($tempFile);
rmdir(dirname($tempFile)); // Trying to remove /tmp!
```

**Good Example**:
```php
$tempFile = sys_get_temp_dir() . '/test-' . uniqid() . '.log';
// ... use file ...
unlink($tempFile);

// Only remove directory if it's not the system temp and is empty
$dir = dirname($tempFile);
if ($dir !== sys_get_temp_dir() && is_dir($dir) && count(scandir($dir)) == 2) {
    rmdir($dir);
}
```

**Why**: Attempting to remove system directories will fail with permission errors. Always verify the directory is one you created before attempting cleanup.

### 11. Misleading Test Result Messages
- ❌ Printing success messages regardless of test results
- ✅ Track test results and report accurately

**Bad Example**:
```php
// Run tests...
echo "All tests passed!\n"; // Always prints, even if tests failed
```

**Good Example**:
```php
$testsPassed = 0;
$testsFailed = 0;

// Run tests, incrementing counters...

if ($testsFailed === 0) {
    echo "All $testsPassed tests passed successfully!\n";
} else {
    echo "Results: $testsPassed passed, $testsFailed failed\n";
}
```

**Why**: Misleading messages make it difficult to identify test failures. Always accurately report test results.

## PSR Standards

This project follows:
- **PSR-4**: Autoloading standard
- **PSR-12**: Extended coding style guide

Ensure all code complies with these standards by running:
```bash
composer run phpcs  # Check coding standards
composer run phpcbf # Auto-fix coding standards
```

## Static Analysis

Run static analysis before committing:
```bash
composer run phpstan  # PHPStan static analysis
```

Fix all errors and warnings reported by static analysis tools.

## Testing

Run the full test suite:
```bash
composer test              # Unit tests
composer integration-test  # Integration tests
```

Ensure all tests pass before committing code.

## Summary

Following these guidelines will help maintain a high-quality, consistent, and secure codebase. When in doubt:

1. **Check existing code** for patterns and conventions
2. **Run static analysis** to catch type and design issues
3. **Write tests** for behavioral aspects, not just interface compliance
4. **Document thoroughly** especially security considerations
5. **Ask for review** when uncertain about design decisions

## References

- [PSR-4: Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Liskov Substitution Principle](https://en.wikipedia.org/wiki/Liskov_substitution_principle)
