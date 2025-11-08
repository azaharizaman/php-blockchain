#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Exception Handling & Error Management
# Usage: ./create-issues-exception-handling.sh [REPO]
# Example: ./create-issues-exception-handling.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Exception Handling"

echo "Creating GitHub issues for Exception Handling & Error Management..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement comprehensive exception handling with runtime recovery mechanisms (retry, circuit breaker, fallback)" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "error-handling"
    "reliability"
    "runtime-recovery"
    "phase-1"
    "documentation"
    "testing"
)

for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" 2>/dev/null | grep -q "^$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO" 2>/dev/null || echo "  (label may already exist)"
    fi
done
echo "✓ All required labels ensured"
echo ""

# Issue 1: Extended Exception Hierarchy
echo "Creating Issue 1: Extended Exception Hierarchy..."
gh issue create \
    --repo "$REPO" \
    --title "EH-001: Implement Extended Exception Hierarchy with Error Codes" \
    --milestone "$MILESTONE" \
    --label "feature,error-handling,phase-1" \
    --body "## Overview
Create comprehensive exception hierarchy with error codes (1000-9999 taxonomy) and rich context for runtime recovery and developer debugging.

## Requirements
- **REQ-017**: Comprehensive error handling and structured exceptions
- **REQ-003**: Network-agnostic error handling and exceptions
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Base BlockchainException Class
- [ ] Create \`src/Exceptions/BlockchainException.php\`
- [ ] Extend standard PHP \`Exception\`
- [ ] Add \`protected int \$errorCode\` property
- [ ] Add \`protected ?ErrorContext \$context = null\` property
- [ ] Implement \`getErrorCode(): int\` method
- [ ] Implement \`getErrorCategory(): string\` method (based on code range)
- [ ] Implement \`getContext(): ?ErrorContext\` method
- [ ] Implement \`setContext(ErrorContext \$context): self\` method
- [ ] Add \`getHttpStatusCode(): int\` method for API mapping
- [ ] Add PHPDoc with error code taxonomy documentation

### 2. ErrorContext Class
- [ ] Create \`src/Exceptions/ErrorContext.php\`
- [ ] Add properties: timestamp, requestId, driverName, networkName
- [ ] Add \`parameters\` array for sanitized context data
- [ ] Add \`remediationSteps\` array for suggested fixes
- [ ] Add \`originalException\` for nested exception context
- [ ] Implement \`isDebugMode\` flag for context filtering
- [ ] Add \`toArray(): array\` method for serialization
- [ ] Add \`sanitize(array \$data): array\` method to remove secrets
- [ ] All properties should be readonly or have private setters
- [ ] Add PHPDoc for all properties and methods

### 3. Network Exception Classes
- [ ] Create \`src/Exceptions/Network/ConnectionException.php\` (error code: 1000-1099)
  - Network connectivity failures, DNS resolution errors
- [ ] Create \`src/Exceptions/Network/TimeoutException.php\` (error code: 1100-1199)
  - Request timeout scenarios, with elapsed time in context
- [ ] Create \`src/Exceptions/Network/RateLimitException.php\` (error code: 7000-7099)
  - API rate limiting, include retry-after in context
- [ ] Create \`src/Exceptions/Network/NetworkException.php\` (error code: 1200-1299)
  - Blockchain network-specific errors (node unavailable, wrong chain)
- [ ] All extend \`BlockchainException\`
- [ ] Add specific context properties for each exception type

### 4. Transaction Exception Classes
- [ ] Enhance existing \`src/Exceptions/TransactionException.php\` (error code: 3000-3099)
  - Add error code support
  - Add context support
  - Keep existing \`transactionHash\` property
- [ ] Create \`src/Exceptions/Transaction/InsufficientFundsException.php\` (error code: 3100-3199)
  - Balance-related failures, include required and available amounts
- [ ] Create \`src/Exceptions/Transaction/ContractException.php\` (error code: 3200-3299)
  - Smart contract interaction errors, include contract address
- [ ] Create \`src/Exceptions/Transaction/SignatureException.php\` (error code: 3300-3399)
  - Transaction signing errors, no private key data in context

### 5. Configuration Exception Classes
- [ ] Enhance existing \`src/Exceptions/ConfigurationException.php\` (error code: 2000-2099)
  - Add error code support
  - Add context support with missing/invalid parameter details
- [ ] Enhance existing \`src/Exceptions/ValidationException.php\` (error code: 4000-4099)
  - Add error code support
  - Add validation error details in context

### 6. Driver Exception Classes
- [ ] Enhance existing \`src/Exceptions/UnsupportedDriverException.php\` (error code: 5000-5099)
  - Add error code support
  - Add list of available drivers in context
- [ ] Create \`src/Exceptions/Driver/UnsupportedOperationException.php\` (error code: 5100-5199)
  - Operation not supported by driver, include operation name and driver name

### 7. Internal Exception Classes
- [ ] Create \`src/Exceptions/Internal/InternalException.php\` (error code: 8000-8099)
  - Unexpected internal errors
- [ ] Create \`src/Exceptions/Internal/AssertionException.php\` (error code: 8100-8199)
  - Assertion failures, include assertion details

### 8. Error Code Constants
- [ ] Create \`src/Exceptions/ErrorCodes.php\` class with constants
  - Network errors: 1000-1999
  - Configuration errors: 2000-2999
  - Transaction errors: 3000-3999
  - Validation errors: 4000-4999
  - Driver errors: 5000-5999
  - Security errors: 6000-6999
  - Rate limit errors: 7000-7999
  - Internal errors: 8000-8999
- [ ] Add PHPDoc explaining each error code range

### 9. Unit Tests
- [ ] Create \`tests/Exceptions/BlockchainExceptionTest.php\`
  - Test error code getter
  - Test error category mapping
  - Test context attachment
  - Test HTTP status code mapping
- [ ] Create \`tests/Exceptions/ErrorContextTest.php\`
  - Test context creation and serialization
  - Test sensitive data sanitization
  - Test debug vs production mode filtering
- [ ] Create tests for all exception types
- [ ] Test exception inheritance hierarchy
- [ ] Test error code ranges and uniqueness

### 10. Documentation
- [ ] Create \`docs/error-codes.md\` with complete error code reference
- [ ] Add usage examples for each exception type
- [ ] Document best practices for exception handling
- [ ] Add migration guide from basic exceptions

## Acceptance Criteria
- [x] All exception types implement consistent error code interface
- [x] ErrorContext properly sanitizes sensitive data
- [x] Error codes are unique and follow taxonomy
- [x] All exceptions have comprehensive PHPDoc
- [x] Unit tests achieve 95%+ coverage
- [x] PHPStan reports no errors
- [x] Documentation includes all error codes with descriptions

## Files Created/Modified
- \`src/Exceptions/BlockchainException.php\`
- \`src/Exceptions/ErrorContext.php\`
- \`src/Exceptions/ErrorCodes.php\`
- \`src/Exceptions/Network/*.php\` (4 files)
- \`src/Exceptions/Transaction/*.php\` (3 new + 1 enhanced)
- Enhanced: \`ConfigurationException.php\`, \`ValidationException.php\`, \`UnsupportedDriverException.php\`
- \`tests/Exceptions/\` (comprehensive test suite)
- \`docs/error-codes.md\`

## Dependencies
- Core Utilities (base exception classes)

## Related
- Plan: \`plan/feature-exception-handling.md\` (Task 1)
- PRD: \`docs/prd/11-EXCEPTION-HANDLING-EPIC.md\`
- Epic: Exception Handling & Error Management
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Retry Strategy Framework
echo "Creating Issue 2: Retry Strategy Framework..."
gh issue create \
    --repo "$REPO" \
    --title "EH-002: Implement Retry Strategy Framework with Multiple Strategies" \
    --milestone "$MILESTONE" \
    --label "feature,runtime-recovery,reliability,phase-1" \
    --body "## Overview
Implement configurable retry mechanism with multiple strategies (exponential backoff, linear, fixed delay, conditional) for automatic recovery from transient failures.

## Requirements
- **REQ-018**: Connection pooling and retry mechanisms
- **REQ-016**: Rate limiting and retry strategies
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. RetryPolicy Interface
- [ ] Create \`src/ErrorHandling/Retry/RetryPolicy.php\` interface
- [ ] Define \`shouldRetry(\\Exception \$e, int \$attempt): bool\` method
- [ ] Define \`getDelay(int \$attempt): int\` method (returns delay in milliseconds)
- [ ] Define \`getMaxAttempts(): int\` method
- [ ] Define \`getName(): string\` method for debugging
- [ ] Add comprehensive PHPDoc explaining retry policy contract

### 2. RetryableException Interface
- [ ] Create \`src/ErrorHandling/Retry/RetryableException.php\` marker interface
- [ ] Define \`isTransient(): bool\` method
- [ ] Add PHPDoc explaining transient vs permanent failures
- [ ] Update relevant exception classes to implement this interface

### 3. Exponential Backoff Strategy
- [ ] Create \`src/ErrorHandling/Retry/Strategies/ExponentialBackoffRetry.php\`
- [ ] Implement RetryPolicy interface
- [ ] Constructor: \`__construct(int \$maxAttempts = 3, int \$baseDelay = 1000, int \$maxDelay = 30000)\`
- [ ] Implement exponential backoff: delay = min(baseDelay * 2^attempt, maxDelay)
- [ ] Add jitter: randomize delay ±25% to prevent thundering herd
- [ ] Implement \`shouldRetry()\`: check attempt count and if exception is retryable
- [ ] Add unit tests for delay calculation and jitter

### 4. Linear Backoff Strategy
- [ ] Create \`src/ErrorHandling/Retry/Strategies/LinearBackoffRetry.php\`
- [ ] Implement RetryPolicy interface
- [ ] Constructor: \`__construct(int \$maxAttempts = 3, int \$baseDelay = 1000, int \$increment = 1000)\`
- [ ] Implement linear backoff: delay = baseDelay + (attempt * increment)
- [ ] Implement \`shouldRetry()\`: check attempt count and exception type
- [ ] Add unit tests

### 5. Fixed Delay Strategy
- [ ] Create \`src/ErrorHandling/Retry/Strategies/FixedDelayRetry.php\`
- [ ] Implement RetryPolicy interface
- [ ] Constructor: \`__construct(int \$maxAttempts = 3, int \$delay = 1000)\`
- [ ] Implement fixed delay: always return same delay
- [ ] Implement \`shouldRetry()\`: check attempt count
- [ ] Add unit tests

### 6. Conditional Retry Strategy
- [ ] Create \`src/ErrorHandling/Retry/Strategies/ConditionalRetry.php\`
- [ ] Implement RetryPolicy interface
- [ ] Constructor: \`__construct(array \$retryableExceptions, RetryPolicy \$innerPolicy)\`
- [ ] Implement \`shouldRetry()\`: check if exception type is in retryable list
- [ ] Delegate delay calculation to inner policy
- [ ] Support exception class hierarchy checking
- [ ] Add unit tests with various exception types

### 7. RetryExecutor Service
- [ ] Create \`src/ErrorHandling/Retry/RetryExecutor.php\`
- [ ] Method: \`execute(callable \$operation, RetryPolicy \$policy, ?callable \$onRetry = null): mixed\`
- [ ] Track retry attempts and timing
- [ ] Call \`\$onRetry\` callback before each retry (for logging/metrics)
- [ ] Respect timeout limits
- [ ] Throw last exception if all retries exhausted
- [ ] Add idempotency check support (optional callable)
- [ ] Emit events: RetryAttemptedEvent, RetrySuccessEvent, RetryExhaustedEvent

### 8. RetryConfig Class
- [ ] Create \`src/ErrorHandling/Retry/RetryConfig.php\`
- [ ] Properties: default policy, per-operation policies, retry budget
- [ ] Method: \`getPolicyForOperation(string \$operation): RetryPolicy\`
- [ ] Method: \`setDefaultPolicy(RetryPolicy \$policy): void\`
- [ ] Method: \`setPolicyForOperation(string \$operation, RetryPolicy \$policy): void\`
- [ ] Support loading from configuration array
- [ ] Add validation for configuration values

### 9. Integration with Operations
- [ ] Update \`ConnectionException\`, \`TimeoutException\`, \`NetworkException\` to implement RetryableException
- [ ] Mark \`InsufficientFundsException\`, \`SignatureException\` as non-retryable
- [ ] Add retry support to HTTP client wrapper
- [ ] Create \`ResilientOperations\` wrapper class for core operations

### 10. Unit Tests
- [ ] Test all retry strategies with mock operations
- [ ] Test retry with transient failures (1st attempt fails, 2nd succeeds)
- [ ] Test retry exhaustion (all attempts fail)
- [ ] Test delay calculations and jitter
- [ ] Test conditional retry with different exception types
- [ ] Test RetryExecutor with callbacks
- [ ] Test idempotency checking
- [ ] Mock sleep/delay for fast tests

### 11. Integration Tests
- [ ] Test retry with simulated network failures
- [ ] Test retry with timeout scenarios
- [ ] Test retry metrics collection
- [ ] Verify retry respects max attempts

### 12. Documentation
- [ ] Create \`docs/retry-strategies.md\`
- [ ] Document each retry strategy with examples
- [ ] Explain when to use each strategy
- [ ] Add configuration examples
- [ ] Document best practices (idempotency, timeouts)

## Acceptance Criteria
- [x] All retry strategies implement RetryPolicy interface
- [x] Exponential backoff includes jitter
- [x] RetryExecutor successfully recovers from transient failures
- [x] Non-retryable exceptions skip retry logic
- [x] Retry metrics are tracked (attempt count, success rate)
- [x] Unit tests achieve 95%+ coverage
- [x] Integration tests validate real-world scenarios
- [x] PHPStan reports no errors

## Files Created
- \`src/ErrorHandling/Retry/RetryPolicy.php\`
- \`src/ErrorHandling/Retry/RetryableException.php\`
- \`src/ErrorHandling/Retry/Strategies/*.php\` (4 strategies)
- \`src/ErrorHandling/Retry/RetryExecutor.php\`
- \`src/ErrorHandling/Retry/RetryConfig.php\`
- \`tests/ErrorHandling/Retry/\` (comprehensive tests)
- \`docs/retry-strategies.md\`

## Dependencies
- Task EH-001 (Extended Exception Hierarchy)

## Related
- Plan: \`plan/feature-exception-handling.md\` (Task 2)
- PRD: \`docs/prd/11-EXCEPTION-HANDLING-EPIC.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Circuit Breaker Pattern
echo "Creating Issue 3: Circuit Breaker Pattern..."
gh issue create \
    --repo "$REPO" \
    --title "EH-003: Implement Circuit Breaker for Cascading Failure Prevention" \
    --milestone "$MILESTONE" \
    --label "feature,runtime-recovery,reliability,phase-1" \
    --body "## Overview
Implement circuit breaker pattern with state management (Closed, Open, Half-Open) to prevent cascading failures and enable automatic recovery.

## Requirements
- **REQ-016**: Rate limiting and DDoS protection
- **REQ-018**: Retry mechanisms and connection pooling
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. CircuitBreakerState Enum
- [ ] Create \`src/ErrorHandling/CircuitBreaker/CircuitBreakerState.php\`
- [ ] Define states: CLOSED, OPEN, HALF_OPEN
- [ ] Use PHP 8.2 enum or constants class
- [ ] Add PHPDoc explaining each state

### 2. CircuitBreakerConfig Class
- [ ] Create \`src/ErrorHandling/CircuitBreaker/CircuitBreakerConfig.php\`
- [ ] Properties: failureThreshold, successThreshold, timeout, halfOpenMaxAttempts
- [ ] Default: 5 failures to open, 2 successes to close, 60s timeout
- [ ] Validation in constructor
- [ ] Immutable design (readonly properties or no setters)

### 3. CircuitBreaker Class
- [ ] Create \`src/ErrorHandling/CircuitBreaker/CircuitBreaker.php\`
- [ ] Constructor: \`__construct(string \$name, CircuitBreakerConfig \$config, ?StorageInterface \$storage = null)\`
- [ ] Properties: name, state, failureCount, successCount, lastFailureTime
- [ ] Method: \`execute(callable \$operation): mixed\`
- [ ] Method: \`getState(): CircuitBreakerState\`
- [ ] Method: \`reset(): void\` - manually reset circuit
- [ ] Method: \`forceOpen(): void\` - manually open circuit
- [ ] Implement state machine logic:
  - CLOSED: Execute operation, track failures, open on threshold
  - OPEN: Fail fast, check timeout for half-open transition
  - HALF_OPEN: Test with limited requests, close or reopen based on results

### 4. State Transition Logic
- [ ] CLOSED → OPEN: When failure count >= threshold
- [ ] OPEN → HALF_OPEN: After timeout period elapsed
- [ ] HALF_OPEN → CLOSED: When success count >= successThreshold
- [ ] HALF_OPEN → OPEN: On any failure during test period
- [ ] Emit events on state transitions
- [ ] Log state changes with context

### 5. Storage Interface
- [ ] Create \`src/ErrorHandling/CircuitBreaker/Storage/StorageInterface.php\`
- [ ] Methods: getState, setState, getFailureCount, incrementFailureCount, resetCounters
- [ ] Support persistence for circuit breaker state
- [ ] Enable distributed circuit breaker (future)

### 6. InMemory Storage Implementation
- [ ] Create \`src/ErrorHandling/CircuitBreaker/Storage/InMemoryStorage.php\`
- [ ] Implement StorageInterface
- [ ] Store state in PHP array (per-process state)
- [ ] Default storage implementation
- [ ] Add unit tests

### 7. CircuitBreakerRegistry
- [ ] Create \`src/ErrorHandling/CircuitBreaker/CircuitBreakerRegistry.php\`
- [ ] Method: \`get(string \$name, ?CircuitBreakerConfig \$config = null): CircuitBreaker\`
- [ ] Singleton per circuit name
- [ ] Support per-driver and per-operation circuit breakers
- [ ] Method: \`getAllCircuitBreakers(): array\`
- [ ] Method: \`reset(string \$name): void\`

### 8. Health Check Integration
- [ ] Method in CircuitBreaker: \`setHealthCheck(callable \$healthCheck): void\`
- [ ] Run health check before transitioning from OPEN to HALF_OPEN
- [ ] Configurable health check timeout
- [ ] Health check result influences state transition

### 9. Events
- [ ] Create \`src/ErrorHandling/CircuitBreaker/Events/CircuitBreakerEvent.php\` base event
- [ ] Create \`CircuitOpenedEvent\`, \`CircuitClosedEvent\`, \`CircuitHalfOpenedEvent\`
- [ ] Include circuit name, previous state, new state, timestamp in events
- [ ] Integrate with event dispatcher from Integration API epic

### 10. Metrics Integration
- [ ] Track: state duration, failure rate, success rate, state transition count
- [ ] Method: \`getMetrics(): array\` returning circuit breaker statistics
- [ ] Prepare for integration with Performance Monitoring epic

### 11. Unit Tests
- [ ] Test state transitions (CLOSED → OPEN → HALF_OPEN → CLOSED)
- [ ] Test fail fast in OPEN state
- [ ] Test timeout-based transition to HALF_OPEN
- [ ] Test success/failure tracking
- [ ] Test manual reset and force open
- [ ] Test storage interface implementations
- [ ] Mock time for deterministic testing

### 12. Integration Tests
- [ ] Test circuit breaker with real operation failures
- [ ] Test recovery after timeout period
- [ ] Test concurrent access (if applicable)
- [ ] Verify event emission

### 13. Documentation
- [ ] Create \`docs/circuit-breaker.md\`
- [ ] Explain circuit breaker pattern and states
- [ ] Configuration examples
- [ ] Usage examples with operations
- [ ] Troubleshooting guide (why is circuit open?)
- [ ] Best practices (threshold tuning, timeout selection)

## Acceptance Criteria
- [x] Circuit breaker implements all three states correctly
- [x] State transitions happen based on thresholds and timeouts
- [x] OPEN state fails fast without executing operation
- [x] Circuit recovers automatically through HALF_OPEN state
- [x] Registry manages multiple circuit breakers
- [x] Events are emitted on state changes
- [x] Unit tests achieve 95%+ coverage
- [x] PHPStan reports no errors

## Files Created
- \`src/ErrorHandling/CircuitBreaker/CircuitBreaker.php\`
- \`src/ErrorHandling/CircuitBreaker/CircuitBreakerState.php\`
- \`src/ErrorHandling/CircuitBreaker/CircuitBreakerConfig.php\`
- \`src/ErrorHandling/CircuitBreaker/CircuitBreakerRegistry.php\`
- \`src/ErrorHandling/CircuitBreaker/Storage/*.php\` (interface + in-memory)
- \`src/ErrorHandling/CircuitBreaker/Events/*.php\`
- \`tests/ErrorHandling/CircuitBreaker/\` (comprehensive tests)
- \`docs/circuit-breaker.md\`

## Dependencies
- Task EH-001 (Exception Hierarchy)
- Task EH-002 (Retry Framework)

## Related
- Plan: \`plan/feature-exception-handling.md\` (Task 3)
- PRD: \`docs/prd/11-EXCEPTION-HANDLING-EPIC.md\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Fallback Mechanisms
echo "Creating Issue 4: Fallback Mechanisms..."
gh issue create \
    --repo "$REPO" \
    --title "EH-004: Implement Fallback Handlers for Graceful Degradation" \
    --milestone "$MILESTONE" \
    --label "feature,runtime-recovery,reliability,phase-1" \
    --body "## Overview
Implement fallback mechanism to provide graceful degradation when primary operations fail, including endpoint fallback, cached responses, and default values.

## Requirements
- **REQ-018**: Connection pooling and retry mechanisms
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. FallbackHandler Interface
- [ ] Create \`src/ErrorHandling/Fallback/FallbackHandler.php\` interface
- [ ] Method: \`handle(\\Exception \$e, Context \$context): mixed\`
- [ ] Method: \`canHandle(\\Exception \$e): bool\`
- [ ] Method: \`getPriority(): int\` for ordering
- [ ] Add PHPDoc explaining fallback contract

### 2. FallbackContext Class
- [ ] Create \`src/ErrorHandling/Fallback/FallbackContext.php\`
- [ ] Properties: operationName, parameters, metadata, attempt count
- [ ] Immutable design
- [ ] Method: \`toArray(): array\` for serialization
- [ ] Add PHPDoc for all properties

### 3. Endpoint Fallback Strategy
- [ ] Create \`src/ErrorHandling/Fallback/Strategies/EndpointFallback.php\`
- [ ] Implement FallbackHandler interface
- [ ] Constructor: \`__construct(array \$endpoints)\` - list of backup endpoints
- [ ] Method: \`handle()\` - switch to next available endpoint
- [ ] Track failed endpoints to avoid reusing
- [ ] Reset endpoint list after all tried or timeout
- [ ] Add unit tests with mock endpoints

### 4. Cache Fallback Strategy
- [ ] Create \`src/ErrorHandling/Fallback/Strategies/CacheFallback.php\`
- [ ] Implement FallbackHandler interface
- [ ] Constructor: \`__construct(CacheInterface \$cache, int \$staleDataTolerance = 3600)\`
- [ ] Method: \`handle()\` - return cached data if available
- [ ] Support stale data tolerance (use old data within time limit)
- [ ] Add cache key generation from context
- [ ] Add unit tests with mock cache

### 5. Default Value Fallback Strategy
- [ ] Create \`src/ErrorHandling/Fallback/Strategies/DefaultValueFallback.php\`
- [ ] Implement FallbackHandler interface
- [ ] Constructor: \`__construct(mixed \$defaultValue)\`
- [ ] Method: \`handle()\` - return configured default value
- [ ] Support callable default value for dynamic defaults
- [ ] Add unit tests

### 6. Null Fallback Strategy
- [ ] Create \`src/ErrorHandling/Fallback/Strategies/NullFallback.php\`
- [ ] Implement FallbackHandler interface
- [ ] Method: \`handle()\` - return null for non-critical operations
- [ ] Only use for optional operations
- [ ] Log warning when used
- [ ] Add unit tests

### 7. FallbackChain Class
- [ ] Create \`src/ErrorHandling/Fallback/FallbackChain.php\`
- [ ] Method: \`addHandler(FallbackHandler \$handler): self\`
- [ ] Method: \`execute(\\Exception \$e, Context \$context): mixed\`
- [ ] Execute handlers in priority order
- [ ] Stop on first successful fallback
- [ ] Track which handler succeeded
- [ ] Throw exception if no handler can handle

### 8. FallbackExecutor Service
- [ ] Create \`src/ErrorHandling/Fallback/FallbackExecutor.php\`
- [ ] Method: \`executeWithFallback(callable \$operation, FallbackChain \$chain, Context \$context): mixed\`
- [ ] Integrate with retry and circuit breaker
- [ ] Track fallback usage metrics
- [ ] Configurable fallback timeout
- [ ] Emit events: FallbackAttemptedEvent, FallbackSuccessEvent, FallbackFailedEvent

### 9. FallbackConfig Class
- [ ] Create \`src/ErrorHandling/Fallback/FallbackConfig.php\`
- [ ] Properties: default chain, per-operation chains, timeout
- [ ] Method: \`getChainForOperation(string \$operation): FallbackChain\`
- [ ] Method: \`setChainForOperation(string \$operation, FallbackChain \$chain): void\`
- [ ] Support loading from configuration array

### 10. Integration with Core Operations
- [ ] Add fallback support to balance queries (cache fallback)
- [ ] Add endpoint fallback to driver connections
- [ ] Add default value fallback to optional operations
- [ ] Create \`ResilientOperations\` wrapper integrating retry, circuit breaker, and fallback

### 11. Unit Tests
- [ ] Test each fallback strategy independently
- [ ] Test fallback chain execution order
- [ ] Test fallback with different exception types
- [ ] Test endpoint rotation in EndpointFallback
- [ ] Test stale cache tolerance in CacheFallback
- [ ] Test fallback timeout enforcement
- [ ] Test fallback metrics tracking

### 12. Integration Tests
- [ ] Test fallback after retry exhaustion
- [ ] Test fallback with circuit breaker open
- [ ] Test endpoint failover in real scenarios
- [ ] Test cache fallback with real cache

### 13. Documentation
- [ ] Create \`docs/fallback-mechanisms.md\`
- [ ] Document each fallback strategy with examples
- [ ] Explain when to use each strategy
- [ ] Show how to combine fallback with retry and circuit breaker
- [ ] Configuration examples
- [ ] Best practices (stale data tolerance, endpoint selection)

## Acceptance Criteria
- [x] All fallback strategies implement FallbackHandler interface
- [x] Fallback chain executes handlers in correct order
- [x] Endpoint fallback successfully switches to backup endpoints
- [x] Cache fallback returns stale data within tolerance
- [x] Fallback integrates with retry and circuit breaker
- [x] Fallback metrics are tracked
- [x] Unit tests achieve 95%+ coverage
- [x] PHPStan reports no errors

## Files Created
- \`src/ErrorHandling/Fallback/FallbackHandler.php\`
- \`src/ErrorHandling/Fallback/FallbackContext.php\`
- \`src/ErrorHandling/Fallback/FallbackChain.php\`
- \`src/ErrorHandling/Fallback/FallbackExecutor.php\`
- \`src/ErrorHandling/Fallback/FallbackConfig.php\`
- \`src/ErrorHandling/Fallback/Strategies/*.php\` (4 strategies)
- \`tests/ErrorHandling/Fallback/\` (comprehensive tests)
- \`docs/fallback-mechanisms.md\`

## Dependencies
- Task EH-001 (Exception Hierarchy)
- Task EH-002 (Retry Framework)
- Task EH-003 (Circuit Breaker)

## Related
- Plan: \`plan/feature-exception-handling.md\` (Task 4)
- PRD: \`docs/prd/11-EXCEPTION-HANDLING-EPIC.md\`
" || echo "⚠ Issue 4 may already exist"

echo ""
echo "✓ All Exception Handling issues created successfully!"
echo ""
echo "Summary:"
echo "  - Milestone: $MILESTONE"
echo "  - Issues created: 4"
echo "  - Labels: error-handling, reliability, runtime-recovery, phase-1"
echo ""
echo "Next steps:"
echo "  1. Review issues in GitHub: https://github.com/$REPO/issues"
echo "  2. Assign to team members"
echo "  3. Start implementation following the plan"
echo ""
