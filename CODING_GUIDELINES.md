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

### Use expectNotToPerformAssertions() for No-Op Tests

**Issue**: Using `$this->assertTrue(true)` is redundant and unclear about the test's intent.

**Rule**: When testing that a method completes without throwing exceptions (especially for no-op implementations), use `$this->expectNotToPerformAssertions()` at the beginning of the test method to clearly communicate intent.

**Bad Example**:
```php
public function testExportDoesNotThrowException(): void
{
    $exporter->export(['metric' => 'value']);
    
    // If we reach here, the test passes
    $this->assertTrue(true); // Redundant and unclear
}
```

**Good Example**:
```php
public function testExportDoesNotThrowException(): void
{
    $this->expectNotToPerformAssertions();
    $exporter->export(['metric' => 'value']);
}
```

**Why**: 
- `$this->assertTrue(true)` always passes and provides no value
- `expectNotToPerformAssertions()` explicitly documents that the test verifies no exception is thrown
- It makes the test's purpose clear: ensuring the method can be called safely
- PHPUnit will properly track this as an intentional design (not a forgotten assertion)

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

### 12. Explicit Exception Class References
- ❌ Using exception classes without leading backslash in global namespace
- ✅ Use explicit `use` statements or leading backslash for exception classes

**Bad Example**:
```php
try {
    $breaker->call(function () {
        throw new RuntimeException("Service unavailable");
    });
} catch (RuntimeException $e) {
    // Code...
}
```

**Good Example (Option 1 - use statement)**:
```php
use RuntimeException;

try {
    $breaker->call(function () {
        throw new RuntimeException("Service unavailable");
    });
} catch (RuntimeException $e) {
    // Code...
}
```

**Good Example (Option 2 - leading backslash)**:
```php
try {
    $breaker->call(function () {
        throw new \RuntimeException("Service unavailable");
    });
} catch (\RuntimeException $e) {
    // Code...
}
```

**Why**: Explicit namespace usage makes it clear that the class is from the global namespace and prevents ambiguity. It's a PHP best practice to make namespace usage explicit.

### 13. Accurate Terminology in Documentation
- ❌ Using imprecise language like "exceeded" when the condition is "greater than or equal to"
- ✅ Use accurate terminology matching the actual implementation

**Bad Example**:
```php
/**
 * Opens circuit when threshold exceeded
 */
// Implementation: if ($failures >= $threshold)
```

**Good Example**:
```php
/**
 * Opens circuit when threshold reached or exceeded
 */
// Implementation: if ($failures >= $threshold)
```

**Why**: Documentation should accurately reflect the implementation. Using "exceeded" when the code uses `>=` is misleading. Better to say "reached" or "reached or exceeded".

### 14. Documentation vs Implementation Alignment
- ❌ Documenting features as implemented when they're only planned for future
- ✅ Clearly document what's implemented vs what's planned

**Bad Example**:
```php
/**
 * Execute with bulkhead protection.
 * If queueing is enabled, it will wait for a slot to become available.
 */
public function execute(callable $operation)
{
    // Implementation: return false immediately, no queueing
}
```

**Good Example**:
```php
/**
 * Execute with bulkhead protection.
 * 
 * If queueing is enabled, operations would be queued (not yet implemented).
 * Currently rejects immediately when at capacity.
 */
public function execute(callable $operation)
{
    // Implementation: return false immediately
}
```

**Why**: Documentation must accurately reflect current behavior. Documenting unimplemented features as if they exist misleads users and can cause bugs.

### 15. Thread-Safety Claims
- ❌ Claiming thread-safety without proper synchronization mechanisms
- ✅ Be honest about concurrency limitations in typical PHP environments

**Bad Example**:
```php
/**
 * Features:
 * - Thread-safe design: Safe for concurrent usage
 */
class Bulkhead {
    private int $activeCount = 0; // No locking mechanism
}
```

**Good Example**:
```php
/**
 * Features:
 * - Concurrency-aware design: Suitable for typical PHP request-per-process model
 * - For true thread/process safety in multi-threaded environments, additional
 *   synchronization mechanisms (locks, semaphores) would be required
 */
class Bulkhead {
    private int $activeCount = 0;
}
```

**Why**: PHP typically runs in a request-per-process model where true thread-safety isn't needed. Claiming thread-safety without proper locking mechanisms is misleading. Be honest about the design assumptions.

### 16. Avoid Environment-Dependent Performance Assertions

**Issue**: Performance assertions with tight thresholds can cause flaky test failures on slower CI environments or under system load.

**Rule**: When testing performance characteristics (especially for no-op implementations), use generous thresholds or focus on behavioral tests instead.

**Bad Example**:
```php
public function testExportHasNoObservableEffects(): void
{
    $startTime = microtime(true);
    
    for ($i = 0; $i < 1000; $i++) {
        $exporter->export(['iteration' => $i]);
    }
    
    $duration = microtime(true) - $startTime;
    
    // This may fail on slow CI environments
    $this->assertLessThan(0.01, $duration, 'Should be very fast');
}
```

**Good Example**:
```php
public function testExportHasNoObservableEffects(): void
{
    $startTime = microtime(true);
    
    for ($i = 0; $i < 1000; $i++) {
        $exporter->export(['iteration' => $i]);
    }
    
    $duration = microtime(true) - $startTime;
    
    // Use a generous threshold (1 second for 1000 calls)
    // This ensures test reliability across different environments
    $this->assertLessThan(1.0, $duration, 'NoopExporter should have zero overhead');
}
```

**Why**: 
- CI environments and virtualized systems have variable performance
- Tight performance thresholds (e.g., 10ms) can cause sporadic test failures
- The goal is to verify no-op behavior, not to measure exact performance
- Generous thresholds (e.g., 1 second) still catch performance regressions while avoiding flakiness
- For critical performance tests, consider separate benchmark suites with controlled environments

### 17. File Write Error Handling

**Issue**: Functions like `file_put_contents()` can fail silently if not checked, leading to data loss or misleading success messages.

**Rule**: Always check the return value of file write operations and handle failures appropriately.

**Bad Example**:
```php
// Script reports success even if write fails
file_put_contents($outputFile, $data);
echo "Results written to: {$outputFile}\n";
```

**Good Example**:
```php
if (file_put_contents($outputFile, $data) === false) {
    fwrite(STDERR, "Error writing to file: {$outputFile}\n");
    exit(1);
}
echo "Results written to: {$outputFile}\n";
```

**Why**:
- File operations can fail due to permissions, disk space, or I/O errors
- `file_put_contents()` returns `false` on failure, not an exception
- Silent failures lead to data loss and confusing bug reports
- Users expect error messages when operations fail
- Exit codes signal failure to calling scripts and CI/CD pipelines

**When to Apply**:
- Any use of `file_put_contents()`, `fwrite()`, `fputs()`
- Directory creation with `mkdir()`
- File operations like `copy()`, `rename()`, `unlink()`
- Any I/O operation that can fail

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

### 17. Using GuzzleAdapter API Correctly
- ❌ Using `$adapter->request()` method which doesn't exist
- ✅ Use `$adapter->get()` or `$adapter->post()` methods

**Bad Example**:
```php
// request() method doesn't exist in GuzzleAdapter
$response = $adapter->request('POST', 'http://localhost:8545', [
    'json' => ['method' => 'eth_blockNumber']
]);

// Assuming response has 'status' and 'body' keys
if ($response['status'] === 200) {
    $body = json_decode($response['body'], true);
}
```

**Good Example**:
```php
// Use post() method - it returns decoded JSON directly
$response = $adapter->post('http://localhost:8545', [
    'method' => 'eth_blockNumber'
]);

// Response is already decoded JSON array
if (isset($response['result'])) {
    // Process result
}
```

**Why**: 
- `GuzzleAdapter` only provides `get()` and `post()` methods, not a generic `request()` method
- These methods return the decoded JSON response directly as an array, not an object with 'status' and 'body' keys
- HTTP errors (4xx, 5xx) are thrown as exceptions (`ValidationException` for 4xx, `TransactionException` for 5xx) rather than returned in the response
- This design provides consistent error handling and simplifies response processing

**Using MockHandler with GuzzleAdapter**:
```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use Blockchain\Transport\GuzzleAdapter;

// Create mock handler
$handler = new MockHandler([
    new Response(200, [], json_encode(['result' => '0x123']))
]);

// Wrap in HandlerStack
$handlerStack = HandlerStack::create($handler);

// Pass to Client, then to GuzzleAdapter
$client = new Client(['handler' => $handlerStack]);
$adapter = new GuzzleAdapter($client);

// Use adapter methods
$response = $adapter->post('http://localhost:8545', ['method' => 'eth_blockNumber']);
```

### 18. Dependency Injection for Testability
- ❌ Hardcoding dependencies without allowing injection
- ✅ Accept optional dependencies in constructor for testing

**Bad Example**:
```php
class OpenTelemetryExporter implements ExporterInterface
{
    private GuzzleAdapter $adapter;
    
    public function __construct(array $config)
    {
        // Hardcoded - impossible to inject mock for testing
        $this->adapter = new GuzzleAdapter(new Client(), $config);
    }
}
```

**Good Example**:
```php
class OpenTelemetryExporter implements ExporterInterface
{
    private GuzzleAdapter $adapter;
    
    public function __construct(
        array $config, 
        ?LoggerInterface $logger = null,
        ?GuzzleAdapter $adapter = null  // Optional for dependency injection
    ) {
        if ($adapter === null) {
            // Create default if not provided
            $this->adapter = new GuzzleAdapter(new Client(), $config);
        } else {
            // Use injected adapter (useful for testing)
            $this->adapter = $adapter;
        }
    }
}
```

**Testing with Injected Mock**:
```php
public function testExportWithMockAdapter(): void
{
    // Create mock handler
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['status' => 'success']))
    ]);
    
    $handlerStack = HandlerStack::create($mockHandler);
    $client = new Client(['handler' => $handlerStack]);
    $adapter = new \Blockchain\Transport\GuzzleAdapter($client);
    
    // Inject mock adapter
    $exporter = new OpenTelemetryExporter([
        'endpoint' => 'http://localhost:4318/v1/metrics',
    ], null, $adapter);
    
    // Test with mocked HTTP calls
    $exporter->export(['metric' => 123]);
}
```

**Why**: 
- Enables dependency injection for testing without reflection
- Maintains simplicity for production use (dependencies auto-created)
- Follows SOLID principles (Dependency Inversion Principle)
- Makes tests faster and more reliable by avoiding real network calls
- Allows testing error conditions that would be hard to trigger with real services

**When to Use**:
- For any class that makes external network calls (HTTP, database, etc.)
- For classes that interact with file systems or other I/O
- For classes with dependencies that are expensive or slow to create
- When you want to test error conditions without relying on external systems

**Pattern Summary**:
1. Add optional parameter(s) for dependencies at the end of constructor
2. Check if parameter is null
3. If null, create default dependency
4. If provided, use injected dependency
5. Document the injection parameter with `@param` in PHPDoc
6. Provide example of testing with injection in PHPDoc or tests

## References

- [PSR-4: Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Liskov Substitution Principle](https://en.wikipedia.org/wiki/Liskov_substitution_principle)
