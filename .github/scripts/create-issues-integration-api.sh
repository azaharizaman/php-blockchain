#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Integration API & Internal Extensibility
# Usage: ./create-issues-integration-api.sh [REPO]
# Example: ./create-issues-integration-api.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Integration API"

echo "Creating GitHub issues for Integration API & Internal Extensibility..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement internal extensibility with plugin system, service layer, event system, and SOLID patterns" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "architecture"
    "extensibility"
    "internal"
    "design-patterns"
    "phase-1"
    "documentation"
    "testing"
    "refactoring"
)

for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" 2>/dev/null | grep -q "^$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO" 2>/dev/null || echo "  (label may already exist)"
    fi
done
echo "✓ All required labels ensured"
echo ""

# Issue 1: Service Layer Architecture
echo "Creating Issue 1: Service Layer Architecture..."
gh issue create \
    --repo "$REPO" \
    --title "INT-001: Implement Service Layer with Interfaces and DTOs" \
    --milestone "$MILESTONE" \
    --label "feature,architecture,extensibility,phase-1" \
    --body "## Overview
Create service layer abstraction with clear interfaces, DTOs, and service registry to enable swappable implementations and clean separation of concerns.

## Requirements
- **REQ-001**: Single API for all supported blockchain networks
- **REQ-002**: Consistent method signatures across all drivers
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Service Interfaces
- [ ] Create \`src/Core/Services/TransactionServiceInterface.php\`
  - Methods: submitTransaction, getTransaction, getTransactionStatus
  - Type hints: use DTOs for parameters and returns
- [ ] Create \`src/Core/Services/BalanceServiceInterface.php\`
  - Methods: getBalance, getTokenBalance
  - Type hints: use BalanceQuery and BalanceResult DTOs
- [ ] Create \`src/Core/Services/ValidationServiceInterface.php\`
  - Methods: validateAddress, validateAmount, validateTransaction
  - Return ValidationResult DTO
- [ ] Create \`src/Core/Services/ConfigurationServiceInterface.php\`
  - Methods: get, set, has, all
  - Support dot notation access
- [ ] Create \`src/Core/Services/CacheServiceInterface.php\`
  - Methods: get, set, has, delete, clear
  - PSR-16 SimpleCache compatible
- [ ] All interfaces have comprehensive PHPDoc

### 2. Data Transfer Objects (DTOs)
- [ ] Create \`src/Core/DTOs/TransactionRequest.php\`
  - Properties: from, to, amount, data, gasLimit, gasPrice, nonce
  - Immutable design (readonly properties or no setters)
  - Method: \`toArray(): array\`
  - Method: \`validate(): ValidationResult\`
- [ ] Create \`src/Core/DTOs/TransactionResponse.php\`
  - Properties: hash, status, blockNumber, blockHash, timestamp, gasUsed
  - Immutable design
  - Method: \`toArray(): array\`
- [ ] Create \`src/Core/DTOs/BalanceQuery.php\`
  - Properties: address, tokenAddress, blockNumber
  - Immutable design
- [ ] Create \`src/Core/DTOs/BalanceResult.php\`
  - Properties: address, balance, tokenAddress, blockNumber
  - Method: \`toArray(): array\`
- [ ] Create \`src/Core/DTOs/BlockInfo.php\`
  - Properties: number, hash, parentHash, timestamp, transactions
  - Immutable design
- [ ] Create \`src/Core/DTOs/ValidationResult.php\`
  - Properties: isValid, errors (array)
  - Method: \`hasErrors(): bool\`
  - Method: \`getErrors(): array\`

### 3. DTO Builders
- [ ] Create builder classes for complex DTOs
- [ ] \`TransactionRequestBuilder\` with fluent API
- [ ] \`BalanceQueryBuilder\` for balance queries
- [ ] Validate on build()
- [ ] Add PHPDoc with usage examples

### 4. ServiceRegistry Class
- [ ] Create \`src/Core/Services/ServiceRegistry.php\`
- [ ] Method: \`register(string \$name, object \$service): void\`
- [ ] Method: \`get(string \$name): object\`
- [ ] Method: \`has(string \$name): bool\`
- [ ] Method: \`all(): array\`
- [ ] Singleton per service name
- [ ] Support interface-based registration
- [ ] Throw exception if service not found

### 5. ServiceProvider Pattern
- [ ] Create \`src/Core/Services/ServiceProvider.php\` interface
- [ ] Method: \`register(ServiceRegistry \$registry): void\`
- [ ] Method: \`provides(): array\` - list of service names
- [ ] Method: \`isDeferred(): bool\` - lazy loading support
- [ ] Create \`CoreServiceProvider\` implementing ServiceProvider
- [ ] Register default services (validation, configuration, cache)

### 6. Service Implementations
- [ ] Create \`src/Core/Services/DefaultTransactionService.php\`
  - Implement TransactionServiceInterface
  - Delegate to driver operations
  - Use DTOs for parameters and returns
  - Add error handling
- [ ] Create \`src/Core/Services/DefaultBalanceService.php\`
  - Implement BalanceServiceInterface
  - Support caching
  - Use DTOs
- [ ] Create \`src/Core/Services/DefaultValidationService.php\`
  - Implement ValidationServiceInterface
  - Network-agnostic address validation
  - Amount range validation

### 7. Service Lifecycle
- [ ] Add lifecycle methods to ServiceProvider
- [ ] Method: \`boot(ServiceRegistry \$registry): void\` - initialization
- [ ] Method: \`shutdown(): void\` - cleanup
- [ ] Support service initialization order
- [ ] Handle circular dependencies

### 8. Unit Tests
- [ ] Test all DTO classes (creation, immutability, validation)
- [ ] Test DTO builders
- [ ] Test ServiceRegistry (register, get, has)
- [ ] Test ServiceProvider pattern
- [ ] Test service implementations
- [ ] Mock dependencies in service tests
- [ ] Test error scenarios

### 9. Integration Tests
- [ ] Test service layer with real drivers
- [ ] Test DTO transformation through service calls
- [ ] Test service provider bootstrapping
- [ ] Test service lifecycle

### 10. Documentation
- [ ] Create \`docs/extensibility/service-layer.md\`
- [ ] Document service interfaces and contracts
- [ ] DTO design guidelines and examples
- [ ] Service registration and provider examples
- [ ] Best practices for service implementations
- [ ] Testing guide for services

## Acceptance Criteria
- [x] Service interfaces define clear contracts
- [x] DTOs are immutable and validated
- [x] Service registry manages service lifecycle
- [x] Service implementations delegate to drivers
- [x] Services can be swapped without breaking consumers
- [x] Unit tests achieve 90%+ coverage
- [x] PHPStan reports no errors
- [x] Documentation includes examples

## Files Created
- \`src/Core/Services/*.php\` (5 interfaces + registry + provider + implementations)
- \`src/Core/DTOs/*.php\` (6 DTO classes + builders)
- \`tests/Core/Services/\` (comprehensive tests)
- \`docs/extensibility/service-layer.md\`

## Dependencies
- Core Utilities (base interfaces)

## Related
- Plan: \`plan/feature-integration-api.md\` (Task 1)
- PRD: \`docs/prd/12-INTEGRATION-API-EPIC.md\`
- Epic: Integration API & Internal Extensibility
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Event System Implementation
echo "Creating Issue 2: Event System Implementation..."
gh issue create \
    --repo "$REPO" \
    --title "INT-002: Implement Event-Driven Architecture with Event Dispatcher" \
    --milestone "$MILESTONE" \
    --label "feature,architecture,extensibility,phase-1" \
    --body "## Overview
Implement internal event system for component communication, enabling plugins and extensions to hook into SDK lifecycle and operations.

## Requirements
- **CON-001**: PHP 8.2+, PHPStan level 7
- PSR-14 Event Dispatcher compliance

## Implementation Checklist

### 1. Event Base Class
- [ ] Create \`src/Core/Events/Event.php\` abstract class
- [ ] Properties: timestamp, eventName, source, correlationId
- [ ] Method: \`getName(): string\`
- [ ] Method: \`getTimestamp(): \\DateTimeImmutable\`
- [ ] Method: \`getSource(): string\`
- [ ] Method: \`getCorrelationId(): string\`
- [ ] Method: \`toArray(): array\` for serialization
- [ ] Method: \`isPropagationStopped(): bool\`
- [ ] Method: \`stopPropagation(): void\`
- [ ] Immutable design for properties

### 2. EventDispatcher Class
- [ ] Create \`src/Core/Events/EventDispatcher.php\`
- [ ] Implement PSR-14 EventDispatcherInterface
- [ ] Method: \`dispatch(object \$event): object\`
- [ ] Method: \`addListener(string \$eventName, callable \$listener, int \$priority = 0): void\`
- [ ] Method: \`removeListener(string \$eventName, callable \$listener): void\`
- [ ] Method: \`hasListeners(string \$eventName): bool\`
- [ ] Method: \`getListeners(string \$eventName): array\`
- [ ] Support priority ordering (higher priority = earlier execution)
- [ ] Support propagation stopping
- [ ] Thread-safe listener management

### 3. EventListener Interface
- [ ] Create \`src/Core/Events/EventListenerInterface.php\`
- [ ] Method: \`handle(Event \$event): void\`
- [ ] Method: \`getPriority(): int\` (default: 0)
- [ ] Method: \`supports(Event \$event): bool\`
- [ ] Add PHPDoc explaining listener contract

### 4. EventSubscriber Interface
- [ ] Create \`src/Core/Events/EventSubscriber.php\` interface
- [ ] Method: \`getSubscribedEvents(): array\` - returns eventName => method mapping
- [ ] Support multiple events per subscriber
- [ ] Support priority per event
- [ ] Method: \`subscribe(EventDispatcher \$dispatcher): void\`

### 5. Standard Event Types
- [ ] Create \`src/Core/Events/Types/DriverRegisteredEvent.php\`
  - Properties: driverName, driverClass
- [ ] Create \`src/Core/Events/Types/DriverConnectedEvent.php\`
  - Properties: driverName, endpoint, chainId
- [ ] Create \`src/Core/Events/Types/TransactionSentEvent.php\`
  - Properties: transactionHash, from, to, amount
- [ ] Create \`src/Core/Events/Types/TransactionConfirmedEvent.php\`
  - Properties: transactionHash, blockNumber, confirmations
- [ ] Create \`src/Core/Events/Types/BalanceFetchedEvent.php\`
  - Properties: address, balance, tokenAddress
- [ ] Create \`src/Core/Events/Types/ErrorOccurredEvent.php\`
  - Properties: exception, context, operation
- [ ] Create \`src/Core/Events/Types/CircuitBreakerOpenedEvent.php\`
  - Properties: circuitName, failureCount
- [ ] Create \`src/Core/Events/Types/RetryAttemptedEvent.php\`
  - Properties: operation, attempt, delay
- [ ] All events extend base Event class

### 6. Async Event Dispatch
- [ ] Add \`AsyncEventDispatcher\` class
- [ ] Queue events for asynchronous processing
- [ ] Method: \`dispatchAsync(Event \$event): void\`
- [ ] Support event buffering and batch dispatch
- [ ] Handle async event failures gracefully

### 7. Event Replay
- [ ] Add \`EventRecorder\` for debugging
- [ ] Record all dispatched events
- [ ] Method: \`replay(array \$events): void\`
- [ ] Method: \`getRecordedEvents(): array\`
- [ ] Enable/disable recording per environment

### 8. Integration with Components
- [ ] Emit DriverRegisteredEvent when driver is registered
- [ ] Emit DriverConnectedEvent when driver connects
- [ ] Emit TransactionSentEvent when transaction is sent
- [ ] Emit BalanceFetchedEvent when balance is queried
- [ ] Emit ErrorOccurredEvent on exceptions
- [ ] Integrate with circuit breaker and retry from Exception Handling epic

### 9. Unit Tests
- [ ] Test event creation and immutability
- [ ] Test event dispatcher (add listener, dispatch, priority)
- [ ] Test propagation stopping
- [ ] Test event subscriber pattern
- [ ] Test async event dispatch
- [ ] Test event replay functionality
- [ ] Mock listeners for testing

### 10. Integration Tests
- [ ] Test event flow through SDK operations
- [ ] Test multiple listeners on same event
- [ ] Test event priority ordering in practice
- [ ] Test async event processing

### 11. Documentation
- [ ] Create \`docs/extensibility/event-system.md\`
- [ ] Document event types and payloads
- [ ] Listener registration examples
- [ ] Event subscriber pattern examples
- [ ] Async event handling guide
- [ ] Debugging with event replay
- [ ] Best practices for event handling

## Acceptance Criteria
- [x] PSR-14 compliant event dispatcher
- [x] Events are dispatched to registered listeners
- [x] Priority ordering works correctly
- [x] Propagation can be stopped
- [x] All standard events are defined and emitted
- [x] Async dispatch works for non-blocking operations
- [x] Unit tests achieve 90%+ coverage
- [x] PHPStan reports no errors

## Files Created
- \`src/Core/Events/Event.php\`
- \`src/Core/Events/EventDispatcher.php\`
- \`src/Core/Events/AsyncEventDispatcher.php\`
- \`src/Core/Events/EventListenerInterface.php\`
- \`src/Core/Events/EventSubscriber.php\`
- \`src/Core/Events/EventRecorder.php\`
- \`src/Core/Events/Types/*.php\` (8 event classes)
- \`tests/Core/Events/\` (comprehensive tests)
- \`docs/extensibility/event-system.md\`

## Dependencies
- Task INT-001 (Service Layer)

## Related
- Plan: \`plan/feature-integration-api.md\` (Task 2)
- PRD: \`docs/prd/12-INTEGRATION-API-EPIC.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Middleware Pipeline
echo "Creating Issue 3: Middleware Pipeline..."
gh issue create \
    --repo "$REPO" \
    --title "INT-003: Implement Middleware Pipeline for Cross-Cutting Concerns" \
    --milestone "$MILESTONE" \
    --label "feature,architecture,extensibility,phase-1" \
    --body "## Overview
Implement middleware pattern for request/response processing, enabling cross-cutting concerns (logging, caching, validation, rate limiting) to be applied consistently.

## Requirements
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Context Object
- [ ] Create \`src/Core/Middleware/Context.php\` class
- [ ] Properties: operation, parameters, metadata, result
- [ ] Method: \`withParameter(string \$key, mixed \$value): self\` - immutable add
- [ ] Method: \`getParameter(string \$key, mixed \$default = null): mixed\`
- [ ] Method: \`withMetadata(string \$key, mixed \$value): self\`
- [ ] Method: \`getMetadata(string \$key, mixed \$default = null): mixed\`
- [ ] Method: \`withResult(mixed \$result): self\`
- [ ] Method: \`getResult(): mixed\`
- [ ] Immutable design (always return new instance)

### 2. Middleware Interface
- [ ] Create \`src/Core/Middleware/MiddlewareInterface.php\`
- [ ] Method: \`process(Context \$context, callable \$next): Context\`
- [ ] Method: \`getName(): string\` for debugging
- [ ] Add PHPDoc explaining middleware contract
- [ ] Explain \$next parameter (next middleware in chain)

### 3. Pipeline Class
- [ ] Create \`src/Core/Middleware/Pipeline.php\`
- [ ] Method: \`pipe(MiddlewareInterface \$middleware): self\` - add middleware
- [ ] Method: \`execute(Context \$context, callable \$operation): Context\`
- [ ] Build middleware chain from list
- [ ] Execute in order with next() pattern
- [ ] Handle exceptions in middleware chain
- [ ] Support middleware short-circuiting

### 4. PipelineBuilder
- [ ] Create \`src/Core/Middleware/PipelineBuilder.php\`
- [ ] Fluent API: \`add()\`, \`addWhen()\`, \`addUnless()\`
- [ ] Method: \`build(): Pipeline\`
- [ ] Method: \`addConditional(callable \$condition, MiddlewareInterface \$middleware): self\`
- [ ] Support middleware groups
- [ ] Validate middleware order

### 5. Built-in Middleware: Logging
- [ ] Create \`src/Core/Middleware/Builtin/LoggingMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Log operation start with parameters (sanitized)
- [ ] Log operation result or error
- [ ] Log execution time
- [ ] Use PSR-3 logger
- [ ] Configurable log level

### 6. Built-in Middleware: Caching
- [ ] Create \`src/Core/Middleware/Builtin/CachingMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Check cache before executing operation
- [ ] Store result in cache after execution
- [ ] Support TTL configuration
- [ ] Cache key generation from context
- [ ] Skip caching for write operations

### 7. Built-in Middleware: Validation
- [ ] Create \`src/Core/Middleware/Builtin/ValidationMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Validate context parameters before operation
- [ ] Throw ValidationException on invalid input
- [ ] Use ValidationService from service layer
- [ ] Support custom validation rules

### 8. Built-in Middleware: Rate Limiting
- [ ] Create \`src/Core/Middleware/Builtin/RateLimitingMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Check rate limit before operation
- [ ] Throw RateLimitException if exceeded
- [ ] Track operation count per time window
- [ ] Support per-operation limits
- [ ] Integration with circuit breaker

### 9. Built-in Middleware: Metrics
- [ ] Create \`src/Core/Middleware/Builtin/MetricsMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Track operation count, duration, errors
- [ ] Store metrics for monitoring
- [ ] Support custom metric collectors
- [ ] Prepare for Performance Monitoring epic integration

### 10. Built-in Middleware: Timeout
- [ ] Create \`src/Core/Middleware/Builtin/TimeoutMiddleware.php\`
- [ ] Implement MiddlewareInterface
- [ ] Enforce timeout on operation
- [ ] Throw TimeoutException if exceeded
- [ ] Use timeout from context or config
- [ ] Track elapsed time

### 11. Conditional Middleware Execution
- [ ] Add \`ConditionalMiddleware\` wrapper
- [ ] Execute wrapped middleware only if condition met
- [ ] Support environment-based conditions (dev/prod)
- [ ] Support operation-based conditions
- [ ] Support parameter-based conditions

### 12. Middleware State Isolation
- [ ] Ensure middleware doesn't share mutable state
- [ ] Context immutability prevents side effects
- [ ] Each middleware execution isolated
- [ ] State cleanup after pipeline execution

### 13. Unit Tests
- [ ] Test middleware interface implementations
- [ ] Test pipeline builder and execution
- [ ] Test middleware ordering
- [ ] Test conditional middleware
- [ ] Test all built-in middleware
- [ ] Test error handling in middleware
- [ ] Test state isolation

### 14. Integration Tests
- [ ] Test middleware pipeline with real operations
- [ ] Test multiple middleware in sequence
- [ ] Test caching middleware effectiveness
- [ ] Test validation middleware with invalid input
- [ ] Test rate limiting middleware under load

### 15. Documentation
- [ ] Create \`docs/extensibility/middleware.md\`
- [ ] Document middleware interface and contract
- [ ] Pipeline builder examples
- [ ] Built-in middleware reference
- [ ] Custom middleware development guide
- [ ] Conditional execution patterns
- [ ] Testing middleware guide

## Acceptance Criteria
- [x] Middleware interface is clean and composable
- [x] Pipeline executes middleware in correct order
- [x] Built-in middleware provides expected functionality
- [x] Conditional middleware works correctly
- [x] State isolation prevents interference
- [x] Context immutability enforced
- [x] Unit tests achieve 90%+ coverage
- [x] PHPStan reports no errors

## Files Created
- \`src/Core/Middleware/MiddlewareInterface.php\`
- \`src/Core/Middleware/Context.php\`
- \`src/Core/Middleware/Pipeline.php\`
- \`src/Core/Middleware/PipelineBuilder.php\`
- \`src/Core/Middleware/Builtin/*.php\` (6 built-in middleware)
- \`src/Core/Middleware/ConditionalMiddleware.php\`
- \`tests/Core/Middleware/\` (comprehensive tests)
- \`docs/extensibility/middleware.md\`

## Dependencies
- Task INT-001 (Service Layer)
- Task INT-002 (Event System)

## Related
- Plan: \`plan/feature-integration-api.md\` (Task 3)
- PRD: \`docs/prd/12-INTEGRATION-API-EPIC.md\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Plugin System
echo "Creating Issue 4: Plugin System..."
gh issue create \
    --repo "$REPO" \
    --title "INT-004: Implement Plugin Architecture for SDK Extensibility" \
    --milestone "$MILESTONE" \
    --label "feature,architecture,extensibility,phase-1" \
    --body "## Overview
Implement plugin system enabling SDK functionality to be extended without modifying core code, with lifecycle management and dependency resolution.

## Requirements
- **REQ-005**: Modular driver system with hot-swappable implementations
- **REQ-006**: Runtime driver registration and discovery
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. PluginInterface
- [ ] Create \`src/Integration/Plugin/PluginInterface.php\`
- [ ] Method: \`initialize(PluginContext \$context): void\` - plugin startup
- [ ] Method: \`enable(): void\` - activate plugin
- [ ] Method: \`disable(): void\` - deactivate plugin
- [ ] Method: \`teardown(): void\` - plugin cleanup
- [ ] Method: \`getMetadata(): PluginMetadata\` - plugin information
- [ ] Method: \`isEnabled(): bool\` - check if active
- [ ] Add PHPDoc explaining lifecycle

### 2. PluginMetadata Class
- [ ] Create \`src/Integration/Plugin/PluginMetadata.php\`
- [ ] Properties: name, version, description, author, dependencies
- [ ] Method: \`getName(): string\`
- [ ] Method: \`getVersion(): string\`
- [ ] Method: \`getDependencies(): array\` - list of required plugin names
- [ ] Method: \`getRequiredSDKVersion(): string\`
- [ ] Immutable design
- [ ] Validation in constructor

### 3. PluginContext Class
- [ ] Create \`src/Integration/Plugin/PluginContext.php\`
- [ ] Properties: eventDispatcher, serviceRegistry, configuration
- [ ] Method: \`getEventDispatcher(): EventDispatcher\`
- [ ] Method: \`getServiceRegistry(): ServiceRegistry\`
- [ ] Method: \`getConfiguration(): ConfigInterface\`
- [ ] Method: \`getLogger(): LoggerInterface\`
- [ ] Provide plugin access to SDK services

### 4. PluginRegistry Class
- [ ] Create \`src/Integration/Plugin/PluginRegistry.php\`
- [ ] Method: \`register(PluginInterface \$plugin): void\`
- [ ] Method: \`unregister(string \$name): void\`
- [ ] Method: \`get(string \$name): PluginInterface\`
- [ ] Method: \`has(string \$name): bool\`
- [ ] Method: \`all(): array\`
- [ ] Method: \`enabled(): array\` - only enabled plugins
- [ ] Prevent duplicate plugin names
- [ ] Dependency resolution validation

### 5. PluginManager Class
- [ ] Create \`src/Integration/Plugin/PluginManager.php\`
- [ ] Method: \`load(PluginInterface \$plugin): void\` - register and initialize
- [ ] Method: \`enable(string \$name): void\`
- [ ] Method: \`disable(string \$name): void\`
- [ ] Method: \`enableAll(): void\`
- [ ] Method: \`disableAll(): void\`
- [ ] Method: \`reload(string \$name): void\` - disable and enable
- [ ] Resolve plugin dependencies before enabling
- [ ] Calculate correct loading order
- [ ] Handle circular dependencies

### 6. Plugin Lifecycle Management
- [ ] Lifecycle: register → initialize → enable → (running) → disable → teardown
- [ ] Track plugin state (registered, initialized, enabled, disabled)
- [ ] Emit events: PluginRegisteredEvent, PluginEnabledEvent, PluginDisabledEvent
- [ ] Handle lifecycle errors gracefully
- [ ] Support hot-reload in development

### 7. Plugin Configuration
- [ ] Each plugin has own configuration section
- [ ] Method: \`PluginContext::getPluginConfig(string \$key, mixed \$default = null): mixed\`
- [ ] Configuration validation per plugin
- [ ] Support configuration hot-reload
- [ ] Configuration schema definition

### 8. Plugin Extension Points (Hooks)
- [ ] Create \`src/Integration/Plugin/Hooks/OperationHook.php\` interface
  - Methods: beforeTransaction, afterTransaction, beforeBalance, afterBalance
- [ ] Create \`src/Integration/Plugin/Hooks/DriverHook.php\` interface
  - Methods: beforeConnect, afterConnect, beforeDisconnect, afterDisconnect
- [ ] Create \`src/Integration/Plugin/Hooks/ErrorHook.php\` interface
  - Methods: onError, onRetry, onCircuitBreakerOpen
- [ ] Create \`src/Integration/Plugin/Hooks/ConfigurationHook.php\` interface
  - Methods: onConfigLoaded, onConfigChanged
- [ ] Hook registry for plugin registration

### 9. Plugin Sandboxing
- [ ] Create \`PluginExecutor\` for isolated execution
- [ ] Resource usage limits (memory, time)
- [ ] Error isolation (plugin error doesn't crash SDK)
- [ ] Method: \`executeSafe(callable \$pluginMethod): mixed\`
- [ ] Catch and log plugin exceptions
- [ ] Auto-disable misbehaving plugins

### 10. Plugin Dependency Resolution
- [ ] Build dependency graph from plugin metadata
- [ ] Topological sort for loading order
- [ ] Detect circular dependencies
- [ ] Validate required dependencies are present
- [ ] Version compatibility checking

### 11. Plugin Discovery
- [ ] Method: \`PluginManager::discover(string \$directory): array\`
- [ ] Scan directory for plugin files
- [ ] Validate plugin classes implement PluginInterface
- [ ] Auto-load discovered plugins
- [ ] Cache plugin discovery results

### 12. Unit Tests
- [ ] Test plugin registration and lifecycle
- [ ] Test dependency resolution and ordering
- [ ] Test circular dependency detection
- [ ] Test plugin configuration
- [ ] Test hook invocation
- [ ] Test plugin sandboxing and error isolation
- [ ] Test plugin discovery

### 13. Integration Tests
- [ ] Test plugin with real SDK operations
- [ ] Test multiple plugins interaction
- [ ] Test plugin dependency chain
- [ ] Test plugin hot-reload
- [ ] Test plugin error handling

### 14. Example Plugin
- [ ] Create \`examples/extensibility/LoggingPlugin.php\`
- [ ] Implement PluginInterface
- [ ] Use hooks to log all operations
- [ ] Demonstrate plugin configuration
- [ ] Full working example

### 15. Documentation
- [ ] Create \`docs/extensibility/plugin-development.md\`
- [ ] Plugin interface and lifecycle explanation
- [ ] Step-by-step plugin creation tutorial
- [ ] Hook system documentation
- [ ] Dependency resolution guide
- [ ] Plugin testing guide
- [ ] Best practices and patterns

## Acceptance Criteria
- [x] Plugins can be registered and discovered
- [x] Plugin lifecycle methods called correctly
- [x] Plugin dependencies resolved in correct order
- [x] Plugin hooks invoked at correct times
- [x] Plugin errors isolated and handled
- [x] Plugin configuration works independently
- [x] Unit tests achieve 90%+ coverage
- [x] Working example plugin included
- [x] PHPStan reports no errors

## Files Created
- \`src/Integration/Plugin/PluginInterface.php\`
- \`src/Integration/Plugin/PluginMetadata.php\`
- \`src/Integration/Plugin/PluginContext.php\`
- \`src/Integration/Plugin/PluginRegistry.php\`
- \`src/Integration/Plugin/PluginManager.php\`
- \`src/Integration/Plugin/PluginExecutor.php\`
- \`src/Integration/Plugin/Hooks/*.php\` (4 hook interfaces)
- \`tests/Integration/Plugin/\` (comprehensive tests)
- \`examples/extensibility/LoggingPlugin.php\`
- \`docs/extensibility/plugin-development.md\`

## Dependencies
- Task INT-001 (Service Layer)
- Task INT-002 (Event System)

## Related
- Plan: \`plan/feature-integration-api.md\` (Task 4)
- PRD: \`docs/prd/12-INTEGRATION-API-EPIC.md\`
" || echo "⚠ Issue 4 may already exist"

echo ""
echo "✓ All Integration API issues created successfully!"
echo ""
echo "Summary:"
echo "  - Milestone: $MILESTONE"
echo "  - Issues created: 4"
echo "  - Labels: architecture, extensibility, internal, design-patterns, phase-1"
echo ""
echo "Next steps:"
echo "  1. Review issues in GitHub: https://github.com/$REPO/issues"
echo "  2. Assign to team members"
echo "  3. Start implementation following the plan"
echo ""
