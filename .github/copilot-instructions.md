# Custom Instructions for GitHub Copilot

## About This Project

This is a PHP Blockchain Integration Layer that provides a unified interface for integrating various blockchain networks (EVM and non-EVM) into PHP applications. The project is designed to be **agentic-ready**, meaning it can be automatically extended and maintained through AI agents.

## How to Use These Instructions

These custom instructions help GitHub Copilot understand the project's architecture, coding standards, and development patterns. They enable more accurate code suggestions and automated tasks.

## Project Context

- **Language**: PHP 8.2+
- **Architecture**: Modular blockchain driver system
- **Standards**: PSR-4 autoloading, PSR-12 coding standards
- **Testing**: PHPUnit with comprehensive test coverage
- **Agentic Features**: Automated driver generation, documentation updates, and testing

## Key Components

1. **BlockchainManager**: Main orchestrator for blockchain operations
2. **BlockchainDriverInterface**: Contract for all blockchain implementations
3. **Driver Registry**: Runtime registration and management of drivers
4. **Exception System**: Structured error handling
5. **Agent Tasks**: YAML-defined automation workflows

## Development Workflow

1. **Adding New Drivers**: Use the `create-driver` task to generate new blockchain integrations
2. **Testing**: Run `test-driver` task for comprehensive driver testing
3. **Documentation**: Use `update-readme` task to maintain current documentation
4. **Code Quality**: Follow PSR standards and run static analysis

## Common Patterns

### Driver Implementation
```php
class NewDriver implements BlockchainDriverInterface
{
    public function connect(array $config): void { /* ... */ }
    public function getBalance(string $address): float { /* ... */ }
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { /* ... */ }
    // ... other interface methods
}
```

### Error Handling
```php
try {
    $blockchain = new BlockchainManager('driver_name', $config);
    $balance = $blockchain->getBalance($address);
} catch (UnsupportedDriverException $e) {
    // Handle unsupported driver
} catch (ConfigurationException $e) {
    // Handle configuration error
}
```

### Testing Pattern
```php
public function testGetBalanceSuccess(): void
{
    // Mock HTTP responses
    $mockHandler = new MockHandler([/* ... */]);
    // Test implementation
}
```

## Agentic Capabilities

This project supports automated:
- **Driver Generation**: Create new blockchain drivers from specifications
- **Test Creation**: Generate comprehensive test suites
- **Documentation Updates**: Maintain current README and API docs
- **Code Quality**: Static analysis and linting
- **Integration Testing**: End-to-end driver validation

## Best Practices

- Always implement the full `BlockchainDriverInterface`
- Include comprehensive error handling
- Write tests for all public methods
- Follow PSR-12 coding standards
- Use type hints and return types
- Document complex logic with comments
- Keep methods focused and single-purpose