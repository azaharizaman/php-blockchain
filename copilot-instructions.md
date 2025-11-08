# GitHub Copilot Instructions for PHP Blockchain Integration Layer

## Project Overview

This is a **PHP 8.2+ Blockchain Integration Layer** that provides a unified interface for integrating multiple blockchain networks (EVM and non-EVM) into PHP applications. The project is designed to be **agentic-ready**, meaning it can be automatically extended and maintained through AI agents and automation tools.

### Core Principles

1. **Unified Interface**: All blockchain networks expose the same API through `BlockchainDriverInterface`
2. **Modular Architecture**: Driver-based system with hot-swappable implementations
3. **SOLID Principles**: Single responsibility, open/closed, interface segregation, dependency inversion
4. **PSR Standards**: PSR-4 autoloading, PSR-12 coding standards, PSR-3 logging (planned)
5. **Test-Driven**: Comprehensive unit tests with mocked network calls, optional integration tests
6. **Type Safety**: Strict types, full type hints, PHPStan level 7 analysis
7. **Extensibility**: Plugin system, event-driven architecture, middleware patterns (planned Phase 1)
8. **Runtime Resilience**: Automatic retry, circuit breaker, fallback mechanisms (planned Phase 1)

---

## Architecture & Key Components

### 1. Core Components

#### BlockchainManager (`src/BlockchainManager.php`)
- **Purpose**: Main entry point and orchestrator for blockchain operations
- **Responsibility**: Driver lifecycle management, operation delegation
- **Pattern**: Facade pattern - provides simplified interface to complex driver subsystem
- **Usage**:
  ```php
  $manager = new BlockchainManager('solana', ['endpoint' => 'https://api.mainnet-beta.solana.com']);
  $balance = $manager->getBalance($address);
  ```
- **Key Methods**: `setDriver()`, `switchDriver()`, `getSupportedDrivers()`, all interface methods

#### BlockchainDriverInterface (`src/Contracts/BlockchainDriverInterface.php`)
- **Purpose**: Contract that all blockchain drivers must implement
- **Core Methods**:
  - `connect(array $config): void` - Initialize driver with configuration
  - `getBalance(string $address): float` - Get native token balance
  - `sendTransaction(string $from, string $to, float $amount, array $options = []): string` - Submit transaction
  - `getTransaction(string $hash): array` - Get transaction details
  - `getBlock(int|string $blockIdentifier): array` - Get block information
  - `estimateGas(...)`: ?int` - Estimate gas/fees (optional)
  - `getTokenBalance(string $address, string $tokenAddress): ?float` - Get token balance (optional)
  - `getNetworkInfo(): ?array` - Get network metadata (optional)
- **Design**: Minimal required methods, optional methods return null for unsupported features

#### DriverRegistry (`src/Registry/DriverRegistry.php`)
- **Purpose**: Runtime registration and discovery of blockchain drivers
- **Pattern**: Registry pattern - central repository for driver instances
- **Key Methods**:
  - `registerDriver(string $name, string $driverClass): void` - Register new driver
  - `getDriver(string $name): BlockchainDriverInterface` - Retrieve driver instance
  - `hasDriver(string $name): bool` - Check driver availability
  - `getRegisteredDrivers(): array` - List all registered drivers
- **Validation**: Ensures driver classes implement `BlockchainDriverInterface`

### 2. Driver Implementations

#### Current Drivers
- **SolanaDriver** (`src/Drivers/SolanaDriver.php`): Solana blockchain support
- **EthereumDriver** (`src/Drivers/EthereumDriver.php`): Ethereum and EVM-compatible chains

#### Driver Implementation Checklist
When creating a new driver:
1. ✅ Implement `BlockchainDriverInterface`
2. ✅ Use dependency injection for HTTP client (`GuzzleAdapter`)
3. ✅ Support optional caching via `CachePool`
4. ✅ Throw appropriate exceptions (see Exception System)
5. ✅ Use `declare(strict_types=1)` at top of file
6. ✅ Add comprehensive PHPDoc comments
7. ✅ Include usage examples in class docblock
8. ✅ Write unit tests with mocked HTTP responses
9. ✅ Register driver in `DriverRegistry::registerDefaultDrivers()`
10. ✅ Create driver documentation in `docs/drivers/{name}.md`
11. ✅ Update README.md with driver information

#### Driver Design Pattern
```php
<?php

declare(strict_types=1);

namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Utils\CachePool;
use Blockchain\Exceptions\ConfigurationException;

class ExampleDriver implements BlockchainDriverInterface
{
    protected ?GuzzleAdapter $httpClient = null;
    protected array $config = [];
    protected CachePool $cache;

    public function __construct(?GuzzleAdapter $httpClient = null, ?CachePool $cache = null)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache ?? new CachePool();
    }

    public function connect(array $config): void
    {
        if (!isset($config['endpoint'])) {
            throw new ConfigurationException('Endpoint is required');
        }
        $this->config = $config;
        
        if ($this->httpClient === null) {
            $this->httpClient = new GuzzleAdapter(null, [
                'base_uri' => $config['endpoint'],
                'timeout' => $config['timeout'] ?? 30,
            ]);
        }
    }

    private function ensureConnected(): void
    {
        if ($this->httpClient === null) {
            throw new ConfigurationException('Driver not connected');
        }
    }

    // Implement all interface methods...
}
```

### 3. Exception System

#### Exception Hierarchy
```
Exception (PHP base)
└── BlockchainException (planned base - Epic 11)
    ├── ConfigurationException - Invalid/missing configuration
    ├── UnsupportedDriverException - Driver not available
    ├── TransactionException - Transaction failures (has getTransactionHash())
    ├── ValidationException - Input validation failures (has getErrors())
    ├── ConnectionException - Network connectivity issues (planned)
    ├── TimeoutException - Request timeouts (planned)
    ├── RateLimitException - API rate limiting (planned)
    └── ContractException - Smart contract errors (planned)
```

#### Exception Usage Patterns

**Configuration Errors**:
```php
if (!isset($config['endpoint'])) {
    throw new ConfigurationException('Endpoint is required in configuration.');
}
```

**Transaction Errors**:
```php
$exception = new TransactionException('Transaction failed: insufficient funds');
$exception->setTransactionHash($txHash);
throw $exception;
```

**Validation Errors**:
```php
$errors = ['address' => 'Invalid address format'];
throw new ValidationException('Validation failed', $errors);
```

**Driver Errors**:
```php
if (!$registry->hasDriver($name)) {
    throw new UnsupportedDriverException("Driver '{$name}' is not supported.");
}
```

#### Exception Best Practices
- Always use specific exception types (don't throw generic `Exception`)
- Include actionable error messages (what went wrong + how to fix)
- Add context to exceptions (transaction hash, address, config key)
- Never log or include sensitive data (private keys, secrets)
- Document exceptions in PHPDoc `@throws` tags
- Test exception scenarios in unit tests

### 4. Utilities & Helpers

#### AddressValidator (`src/Utils/AddressValidator.php`)
- **Purpose**: Validate and normalize blockchain addresses
- **Methods**:
  - `isValid(string $address, string $network): bool` - Validate address format
  - `normalize(string $address): string` - Normalize address (trim, lowercase for hex)
- **Supported Networks**: Solana (base58), Ethereum (hex with 0x prefix - planned)

#### ConfigLoader (`src/Config/ConfigLoader.php`)
- **Purpose**: Load and validate driver configurations from multiple sources
- **Methods**:
  - `fromArray(array $config): array` - Direct array config
  - `fromEnv(string $prefix): array` - Environment variables
  - `fromFile(string $path): array` - PHP or JSON files
  - `validateConfig(array $config, string $driver): void` - Validate config schema

#### CachePool (`src/Utils/CachePool.php`)
- **Purpose**: Simple in-memory caching for blockchain queries
- **Methods**:
  - `get(string $key): mixed` - Retrieve cached value
  - `set(string $key, mixed $value, int $ttl = 300): void` - Cache value with TTL
  - `has(string $key): bool` - Check cache existence
  - `delete(string $key): void` - Remove cached value
  - `generateKey(string $method, array $params): string` - Generate cache key

#### GuzzleAdapter (`src/Transport/GuzzleAdapter.php`)
- **Purpose**: HTTP client wrapper for RPC calls
- **Methods**:
  - `post(string $uri, array $data): array` - POST JSON-RPC request
  - `get(string $uri, array $params): array` - GET request
- **Features**: JSON encoding/decoding, error handling, configurable timeout

### 5. Testing Infrastructure

#### Test Organization
```
tests/
├── Unit tests (mocked, no network)
│   ├── BlockchainManagerTest.php
│   ├── Drivers/
│   │   ├── SolanaDriverTest.php
│   │   ├── EthereumDriverTest.php
│   ├── Registry/DriverRegistryTest.php
│   ├── Utils/
│   ├── Exceptions/
│   └── ...
└── Integration/ (real network, optional)
    ├── EthereumIntegrationTest.php
    └── ...
```

#### Unit Test Pattern
```php
<?php

declare(strict_types=1);

namespace Tests\Drivers;

use PHPUnit\Framework\TestCase;
use Blockchain\Drivers\ExampleDriver;
use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ExampleDriverTest extends TestCase
{
    public function testGetBalanceSuccess(): void
    {
        // Mock HTTP responses
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['value' => 1000000000],
                'id' => 1
            ]))
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        $adapter = new GuzzleAdapter($client);

        // Create driver with mocked adapter
        $driver = new ExampleDriver($adapter);
        $driver->connect(['endpoint' => 'https://example.com']);

        $balance = $driver->getBalance('address');
        
        $this->assertEquals(1.0, $balance);
    }
}
```

#### Integration Test Pattern
```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Blockchain\BlockchainManager;

class ExampleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!getenv('RUN_INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests disabled');
        }
    }

    public function testRealNetworkConnection(): void
    {
        $endpoint = getenv('ETHEREUM_RPC_ENDPOINT');
        $manager = new BlockchainManager('ethereum', [
            'endpoint' => $endpoint
        ]);

        $networkInfo = $manager->getNetworkInfo();
        $this->assertIsArray($networkInfo);
        $this->assertArrayHasKey('chainId', $networkInfo);
    }
}
```

#### Test Commands
```bash
# Run all unit tests
composer test

# Run integration tests (requires RUN_INTEGRATION_TESTS=true)
composer run integration-test

# Run static analysis
composer analyse

# Run specific test file
vendor/bin/phpunit tests/Drivers/SolanaDriverTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

---

## Code Style & Standards

### PHP Version & Type Safety
- **PHP Version**: 8.2+
- **Strict Types**: Always use `declare(strict_types=1);` at the top of every file
- **Type Hints**: Use type hints for all parameters and return types
- **Nullable Types**: Use `?Type` or `Type|null` for nullable types
- **Array Types**: Use PHPDoc `@param array<string,mixed>` for complex arrays
- **Return Types**: Always declare return types (including `void`)

### PSR-12 Coding Standards
```php
<?php

declare(strict_types=1);

namespace Blockchain\Example;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\ConfigurationException;

/**
 * ExampleClass demonstrates PSR-12 coding standards.
 *
 * Class description with detailed explanation of purpose,
 * responsibilities, and usage patterns.
 *
 * @package Blockchain\Example
 */
class ExampleClass implements BlockchainDriverInterface
{
    private const DEFAULT_TIMEOUT = 30;

    private string $endpoint;
    private ?array $config = null;

    /**
     * Constructor with dependency injection.
     *
     * @param string $endpoint RPC endpoint URL
     * @param array<string,mixed> $config Optional configuration
     */
    public function __construct(string $endpoint, array $config = [])
    {
        $this->endpoint = $endpoint;
        $this->config = $config;
    }

    /**
     * Process a blockchain operation.
     *
     * Detailed method description explaining what it does,
     * when to use it, and any important considerations.
     *
     * @param string $address Blockchain address to process
     * @param array<string,mixed> $options Processing options
     * @return array<string,mixed> Result data
     * @throws ConfigurationException If configuration is invalid
     * @throws ValidationException If address is invalid
     *
     * @example
     * ```php
     * $result = $example->process('address123', ['timeout' => 60]);
     * ```
     */
    public function process(string $address, array $options = []): array
    {
        // Method body with proper spacing and formatting
        if (empty($address)) {
            throw new ValidationException('Address is required');
        }

        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;

        return [
            'address' => $address,
            'timeout' => $timeout,
            'timestamp' => time(),
        ];
    }
}
```

### Naming Conventions
- **Classes**: PascalCase (`BlockchainManager`, `SolanaDriver`)
- **Methods**: camelCase (`getBalance()`, `sendTransaction()`)
- **Variables**: camelCase (`$httpClient`, `$driverName`)
- **Constants**: SCREAMING_SNAKE_CASE (`LAMPORTS_PER_SOL`, `DEFAULT_TIMEOUT`)
- **Interfaces**: PascalCase with `Interface` suffix (`BlockchainDriverInterface`)
- **Exceptions**: PascalCase with `Exception` suffix (`ConfigurationException`)
- **Namespaces**: Match directory structure (`Blockchain\Drivers`)

### Documentation Standards

#### Class-Level Documentation
```php
/**
 * Brief one-line description of the class.
 *
 * Detailed multi-line description explaining:
 * - Purpose and responsibility
 * - When to use this class
 * - Key features and capabilities
 * - Usage patterns and examples
 *
 * @package Blockchain\Namespace
 *
 * @example
 * ```php
 * // Usage example code here
 * $instance = new ClassName($params);
 * $result = $instance->doSomething();
 * ```
 */
```

#### Method-Level Documentation
```php
/**
 * Brief one-line description of what the method does.
 *
 * Detailed explanation of:
 * - What the method does
 * - When to use it
 * - Side effects or state changes
 * - Performance considerations
 *
 * @param string $param1 Description of parameter 1
 * @param array<string,mixed> $param2 Description of parameter 2 with type details
 * @param bool $param3 Optional parameter description (default: false)
 * @return array<string,mixed> Description of return value structure
 * @throws ExceptionType If specific condition occurs
 * @throws AnotherException If another condition occurs
 *
 * @example
 * ```php
 * $result = $object->methodName('value', ['key' => 'value']);
 * ```
 */
```

#### Property Documentation
```php
/**
 * Brief description of property purpose.
 *
 * @var Type|null Detailed description of what this property stores
 */
private ?Type $propertyName = null;

/**
 * @var array<string,BlockchainDriverInterface> Map of driver names to instances
 */
private array $drivers = [];
```

---

## Planned Features (Phase 1)

### Exception Handling & Error Management (Epic 11)
**Status**: PRD Complete, Implementation Pending

**Key Features**:
- Extended exception hierarchy with error codes (1000-9999 taxonomy)
- `ErrorContext` class for rich exception metadata
- Retry framework with exponential backoff, jitter, conditional retry
- Circuit breaker pattern for cascading failure prevention
- Fallback mechanisms for graceful degradation
- Timeout management across all operations
- Structured error logging with PSR-3 integration
- Exception translation layer for network-specific errors

**Usage Pattern** (Planned):
```php
use Blockchain\ErrorHandling\Retry\RetryPolicy;
use Blockchain\ErrorHandling\CircuitBreaker\CircuitBreaker;

// Automatic retry with exponential backoff
$retryPolicy = new ExponentialBackoffRetry(maxAttempts: 3, baseDelay: 100);
$result = $retryExecutor->execute(
    fn() => $blockchain->getBalance($address),
    $retryPolicy
);

// Circuit breaker prevents cascading failures
$circuitBreaker = new CircuitBreaker($config);
$result = $circuitBreaker->execute(
    fn() => $blockchain->sendTransaction($from, $to, $amount)
);
```

**Implementation Guidelines**:
- All core operations should be wrapped with retry/circuit breaker
- Use `RetryableException` marker interface for transient failures
- Never retry on non-recoverable errors (invalid signature, insufficient funds)
- Always include error context in exceptions
- Sanitize sensitive data before logging

### Integration API & Internal Extensibility (Epic 12)
**Status**: PRD Complete, Implementation Pending

**Key Features**:
- Service layer architecture (TransactionService, BalanceService, ValidationService)
- Event-driven system (DriverRegistered, TransactionSent, BalanceFetched, ErrorOccurred)
- Middleware pipeline for cross-cutting concerns
- Plugin system for SDK extensibility
- Driver extension points with traits and base classes
- Design patterns: Strategy, Factory, Repository, Decorator, Observer
- PSR-11 DI container with autowiring
- Hierarchical configuration system

**Usage Pattern** (Planned):
```php
// Service layer
$transactionService = $container->get(TransactionServiceInterface::class);
$response = $transactionService->send($request);

// Event system
$dispatcher->addEventListener('transaction.sent', function($event) {
    // Custom logic on transaction sent
});

// Middleware pipeline
$pipeline = (new PipelineBuilder())
    ->add(new LoggingMiddleware())
    ->add(new CachingMiddleware())
    ->add(new ValidationMiddleware())
    ->build();

// Plugin system
$plugin = new CustomPlugin();
$pluginRegistry->register($plugin);
```

**Implementation Guidelines**:
- Extract common driver functionality into `AbstractBlockchainDriver`
- Use traits for shared capabilities (`EVMDriverTrait`, `SigningTrait`)
- Services should depend on interfaces, not concrete implementations
- Events should be immutable value objects
- Plugins must be isolated (errors don't crash SDK)
- Apply SOLID principles: single responsibility, open/closed, etc.

---

## Development Workflows

### Adding a New Blockchain Driver

**Manual Process**:
1. Create driver class in `src/Drivers/{Name}Driver.php`
2. Implement `BlockchainDriverInterface`
3. Use dependency injection for `GuzzleAdapter` and `CachePool`
4. Add RPC call methods using `$this->httpClient->post()`
5. Implement caching for expensive operations
6. Handle errors with appropriate exceptions
7. Create unit tests in `tests/Drivers/{Name}DriverTest.php`
8. Mock HTTP responses using `MockHandler`
9. Register driver in `DriverRegistry::registerDefaultDrivers()`
10. Create documentation in `docs/drivers/{name}.md`
11. Update `README.md` supported blockchains table
12. Run tests: `composer test && composer analyse`

**Agentic Process** (Planned):
```bash
# Auto-generate driver using agent task
./scripts/run-agent-task.sh create-driver \
  --name="polygon" \
  --rpc-spec="https://polygon.technology/docs/api" \
  --network-type="evm" \
  --features="balance,transaction,token"
```

### Creating a GitHub Issue from PRD

**Process**:
1. Review plan file in `plan/feature-{name}.md`
2. Run issue creation script:
   ```bash
   ./.github/scripts/create-issues-{name}.sh azaharizaman/php-blockchain
   ```
3. Script creates milestone, labels, and issues in GitHub
4. Issues include detailed checklists and acceptance criteria

### Running Tests

**Unit Tests** (Always run, no network required):
```bash
composer test                    # All unit tests
composer analyse                 # PHPStan static analysis
vendor/bin/phpunit --verbose     # Verbose output
vendor/bin/phpunit tests/Drivers/SolanaDriverTest.php  # Specific test
```

**Integration Tests** (Optional, requires real network):
```bash
# Enable integration tests
export RUN_INTEGRATION_TESTS=true
export ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_KEY

# Run integration tests
composer run integration-test
```

**Code Quality**:
```bash
composer analyse                 # PHPStan level 7
vendor/bin/phpcs src/ tests/     # PSR-12 compliance
vendor/bin/phpcbf src/ tests/    # Auto-fix code style
```

### Documentation Maintenance

**Auto-Generated Documentation** (Planned):
```bash
# Generate driver documentation
php scripts/generate-driver-docs.php

# Check documentation freshness
php scripts/check-driver-docs.php

# Update README with latest info
./scripts/run-agent-task.sh update-readme
```

**Manual Documentation**:
- Keep `README.md` up to date with new drivers
- Update `docs/drivers/{name}.md` for driver-specific details
- Add examples to `examples/` directory
- Document breaking changes in `CHANGELOG.md`

---

## Best Practices & Patterns

### Dependency Injection

**DO**:
```php
class SolanaDriver implements BlockchainDriverInterface
{
    public function __construct(
        ?GuzzleAdapter $httpClient = null,
        ?CachePool $cache = null
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache ?? new CachePool();
    }
}

// Usage in tests (inject mock)
$driver = new SolanaDriver($mockAdapter, $mockCache);

// Usage in production (use defaults)
$driver = new SolanaDriver();
```

**DON'T**:
```php
class BadDriver
{
    public function __construct()
    {
        // Hard-coded dependency - can't test or swap
        $this->client = new GuzzleAdapter();
    }
}
```

### Error Handling

**DO**:
```php
// Specific exceptions with context
if (!isset($config['endpoint'])) {
    throw new ConfigurationException(
        'Endpoint is required in configuration. ' .
        'Add "endpoint" => "https://api.example.com" to config.'
    );
}

// Transaction exception with hash
$exception = new TransactionException('Transaction failed: insufficient funds');
$exception->setTransactionHash($txHash);
throw $exception;

// Validation with multiple errors
$errors = [
    'address' => 'Invalid address format',
    'amount' => 'Amount must be positive'
];
throw new ValidationException('Validation failed', $errors);
```

**DON'T**:
```php
// Generic exception, no context
throw new \Exception('Error');

// Exposing sensitive data
throw new Exception("Failed to sign with key: {$privateKey}");

// Vague error messages
throw new ConfigurationException('Invalid config');
```

### Caching

**DO**:
```php
public function getBalance(string $address): float
{
    $cacheKey = CachePool::generateKey('getBalance', ['address' => $address]);

    if ($this->cache->has($cacheKey)) {
        return $this->cache->get($cacheKey);
    }

    $balance = $this->fetchBalanceFromNetwork($address);
    $this->cache->set($cacheKey, $balance, ttl: 300); // 5 minutes

    return $balance;
}
```

**DON'T**:
```php
// Caching without TTL (stale data forever)
$this->cache->set($key, $value);

// Not generating consistent cache keys
$this->cache->set("balance_{$address}", $value);
```

### Testing

**DO**:
```php
// Mock HTTP responses for unit tests
$mockHandler = new MockHandler([
    new Response(200, [], json_encode(['result' => ['value' => 1000000000]]))
]);
$client = new Client(['handler' => HandlerStack::create($mockHandler)]);
$adapter = new GuzzleAdapter($client);
$driver = new SolanaDriver($adapter);

// Test specific scenarios
public function testInsufficientFundsError(): void
{
    // Setup mock to return insufficient funds error
    // Assert TransactionException is thrown
}

// Test edge cases
public function testZeroBalance(): void { /* ... */ }
public function testVeryLargeBalance(): void { /* ... */ }
public function testInvalidAddressFormat(): void { /* ... */ }
```

**DON'T**:
```php
// Making real network calls in unit tests
public function testGetBalance(): void
{
    $driver = new SolanaDriver();
    $driver->connect(['endpoint' => 'https://api.mainnet-beta.solana.com']);
    $balance = $driver->getBalance('real_address'); // ❌ Network call
}

// Not testing error scenarios
public function testOnlyHappyPath(): void { /* ... */ }
```

### Configuration

**DO**:
```php
// Validate configuration early
public function connect(array $config): void
{
    if (!isset($config['endpoint'])) {
        throw new ConfigurationException('endpoint is required');
    }

    if (!filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
        throw new ConfigurationException('endpoint must be a valid URL');
    }

    $this->config = $config;
}

// Provide sensible defaults
$timeout = $config['timeout'] ?? 30;
$retries = $config['retries'] ?? 3;
```

**DON'T**:
```php
// Failing late with cryptic error
public function getBalance(string $address): float
{
    // Crashes with "Undefined array key" if endpoint missing
    $result = $this->httpClient->post($this->config['endpoint'], ...);
}

// No defaults, force user to specify everything
$timeout = $config['timeout']; // ❌ What if not set?
```

---

## Common Pitfalls to Avoid

### 1. Hardcoding Dependencies
❌ **Bad**:
```php
class Driver {
    public function __construct() {
        $this->client = new GuzzleAdapter(); // Can't mock in tests
    }
}
```

✅ **Good**:
```php
class Driver {
    public function __construct(?GuzzleAdapter $client = null) {
        $this->client = $client ?? new GuzzleAdapter();
    }
}
```

### 2. Missing Type Safety
❌ **Bad**:
```php
public function getBalance($address) { // No types
    return $this->fetch($address);    // No return type
}
```

✅ **Good**:
```php
public function getBalance(string $address): float {
    return $this->fetch($address);
}
```

### 3. Poor Error Messages
❌ **Bad**:
```php
throw new Exception('Error'); // What error? How to fix?
```

✅ **Good**:
```php
throw new ConfigurationException(
    'Solana endpoint is required in configuration. ' .
    'Add ["endpoint" => "https://api.mainnet-beta.solana.com"] to connect().'
);
```

### 4. Not Testing Error Scenarios
❌ **Bad**:
```php
public function testGetBalance(): void {
    $balance = $driver->getBalance('address');
    $this->assertGreaterThan(0, $balance);
}
```

✅ **Good**:
```php
public function testGetBalanceSuccess(): void { /* ... */ }
public function testGetBalanceWithInvalidAddress(): void { /* ... */ }
public function testGetBalanceWithNetworkError(): void { /* ... */ }
public function testGetBalanceWithTimeout(): void { /* ... */ }
```

### 5. Exposing Sensitive Data
❌ **Bad**:
```php
// Logging private keys
$this->logger->info("Signing with key: {$privateKey}");

// Including secrets in exceptions
throw new Exception("API call failed with key: {$apiSecret}");
```

✅ **Good**:
```php
// Redact sensitive data
$this->logger->info("Signing transaction", [
    'tx_hash' => $hash,
    'address' => $address,
    // No private key logged
]);

throw new Exception("API call failed. Check configuration.");
```

### 6. Not Validating Input
❌ **Bad**:
```php
public function sendTransaction(string $from, string $to, float $amount): string {
    // Directly use input without validation
    return $this->rpc->send($from, $to, $amount);
}
```

✅ **Good**:
```php
public function sendTransaction(string $from, string $to, float $amount): string {
    if (!AddressValidator::isValid($from, 'solana')) {
        throw new ValidationException('Invalid from address');
    }
    if ($amount <= 0) {
        throw new ValidationException('Amount must be positive');
    }
    return $this->rpc->send($from, $to, $amount);
}
```

### 7. Ignoring PSR Standards
❌ **Bad**:
```php
// No declare(strict_types=1)
// snake_case method names
// Missing namespaces
class my_driver {
    public function get_balance($addr) { }
}
```

✅ **Good**:
```php
<?php

declare(strict_types=1);

namespace Blockchain\Drivers;

class MyDriver {
    public function getBalance(string $address): float { }
}
```

---

## Security Considerations

### 1. Never Log Sensitive Data
- ❌ Private keys, seed phrases, mnemonics
- ❌ API keys, secrets, tokens
- ❌ Transaction signatures before broadcast
- ✅ Log: addresses, transaction hashes, amounts, timestamps

### 2. Validate All Input
- Validate addresses before use
- Validate amounts (positive, within bounds)
- Validate configuration before connecting
- Sanitize user input in error messages

### 3. Use Secure Defaults
- HTTPS endpoints only (warn on HTTP)
- Reasonable timeouts (prevent hanging)
- Rate limiting (prevent abuse)
- TLS certificate validation

### 4. Handle Errors Securely
- Don't expose internal paths in errors
- Don't include credentials in error messages
- Rate limit error responses
- Log errors server-side, show generic message to users

### 5. Test Network Configuration
```php
public function connect(array $config): void {
    $endpoint = $config['endpoint'];
    
    // Validate HTTPS
    if (!str_starts_with($endpoint, 'https://')) {
        trigger_error('Using HTTP endpoint is insecure', E_USER_WARNING);
    }
    
    // Validate URL format
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
        throw new ConfigurationException('Invalid endpoint URL');
    }
}
```

---

## Performance Optimization

### 1. Use Caching Strategically
- Cache expensive RPC calls (getBalance, getTokenBalance)
- Use short TTL for frequently changing data (30-300 seconds)
- Cache immutable data indefinitely (historical blocks, transactions)
- Generate consistent cache keys

### 2. Connection Pooling (Planned)
- Reuse HTTP connections across requests
- Keep-alive for persistent connections
- Connection limits to prevent resource exhaustion

### 3. Batch Operations (Planned)
- Batch multiple RPC calls into single request
- Reduce network round-trips
- Better throughput for bulk operations

### 4. Async Operations (Planned)
- Non-blocking transaction submission
- Parallel balance queries
- Background status polling

---

## CI/CD & Automation

### GitHub Actions Workflows
- **Tests**: Run on every PR (unit tests + static analysis)
- **Integration Tests**: Gated by `RUN_INTEGRATION_TESTS` secret
- **Agent Tasks**: Automated driver generation and updates

### Pre-commit Checks
```bash
# Run before committing
composer test          # Unit tests
composer analyse       # PHPStan
vendor/bin/phpcs src/  # Code style
```

### Release Process
1. Update `CHANGELOG.md` with changes
2. Bump version in `composer.json`
3. Tag release: `git tag v1.2.3`
4. Push tag: `git push origin v1.2.3`
5. GitHub Actions publishes to Packagist

---

## Resources & References

### Documentation
- Main README: `/README.md`
- Testing Guide: `/TESTING.md`
- Contributing Guide: `/CONTRIBUTING.md`
- PRD: `/PRD.md` and `/docs/prd/*.md`
- Plan Files: `/plan/feature-*.md`

### External Resources
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [Guzzle HTTP Client](https://docs.guzzlephp.org/)

### Project Links
- GitHub: `https://github.com/azaharizaman/php-blockchain`
- Issues: Create via `.github/scripts/create-issues-*.sh`
- Packagist: `azaharizaman/php-blockchain`

---

## Quick Reference Commands

```bash
# Testing
composer test                              # Run unit tests
composer run integration-test              # Run integration tests (if enabled)
composer analyse                           # PHPStan static analysis
vendor/bin/phpunit --coverage-html coverage/  # Generate coverage report

# Code Quality
vendor/bin/phpcs src/ tests/               # Check code style
vendor/bin/phpcbf src/ tests/              # Fix code style
composer analyse                           # Static analysis

# Documentation
php scripts/generate-driver-docs.php       # Generate driver docs
php scripts/check-driver-docs.php          # Validate docs

# Development
composer install                           # Install dependencies
composer dump-autoload                     # Regenerate autoloader

# Issue Creation
./.github/scripts/create-issues-core-operations.sh azaharizaman/php-blockchain
./.github/scripts/create-issues-exception-handling.sh azaharizaman/php-blockchain
./.github/scripts/create-issues-integration-api.sh azaharizaman/php-blockchain
```

---

## Contribution Workflow

1. **Fork** the repository
2. **Create feature branch**: `git checkout -b feature/new-driver`
3. **Implement** following patterns in this guide
4. **Write tests** (unit tests required, integration tests optional)
5. **Run quality checks**: `composer test && composer analyse`
6. **Commit** with clear messages
7. **Push** and create Pull Request
8. **Address** review feedback
9. **Merge** after approval

---

*This document is maintained as part of the PHP Blockchain Integration Layer project. Last updated: 2025-11-08*
