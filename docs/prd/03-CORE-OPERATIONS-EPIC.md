## Epic: Core Operations

### 🎯 Goal & Value Proposition
Provide a unified set of core blockchain operations available across drivers (balance queries, transaction submission and status, token operations, gas estimation). This Epic ensures application developers can rely on consistent behavior and predictable performance for critical operations across networks.

### ⚙️ Features & Requirements
1. Account balance retrieval across all supported networks (REQ-009).
2. Transaction sending with network-specific optimizations and options (REQ-010).
3. Transaction status and history queries (REQ-011).
4. Token balance and transfer operations (REQ-012).
5. Gas estimation and fee calculation helpers (REQ-013).
6. Standardized response shapes and error codes for operation results.
7. Pagination helpers and history retrieval utilities for large accounts.
8. Safe default timeouts and retry policies for RPC calls related to core ops.
9. Hooks for instrumentation (metrics, traces) on core operation calls.

### 🤝 Module Mapping & Dependencies
- PHP Namespace / Module: `Blockchain\Core\Operations` (implementations under `src/` as Core Operations helpers and services)
- Depends on: Core Utilities (interfaces, HTTP adapter), Driver Architecture (driver implementations), Security & Reliability (for key handling and retries).

### ✅ Acceptance Criteria
- Drivers expose consistent `getBalance`, `sendTransaction`, `getTransaction`, and token helpers with unit tests.
- Gas estimation returns sensible defaults and can be overridden by driver-specific strategies.
- Transaction history and pagination return consistent and well-documented data shapes.
- Instrumentation hooks emit basic metrics in CI test runs (mockable).

---

### Changelog

#### 2025-11-08: Runtime Recovery & Service Layer Integration

**Changes**:
- **Automatic Error Recovery**: All core operations (getBalance, sendTransaction, getTransaction) will be wrapped with retry policies, circuit breakers, and fallback mechanisms from Exception Handling Epic (Epic 11).
- **Service Layer Architecture**: Core operations will be exposed through service interfaces (TransactionServiceInterface, BalanceServiceInterface) as defined in Integration API Epic (Epic 12).
- **Data Transfer Objects**: Operation requests and responses will use standardized DTOs (TransactionRequest, TransactionResponse, BalanceQuery, BalanceResult) from Epic 12.
- **Middleware Pipeline**: Operations will pass through middleware pipeline for logging, validation, caching, and metrics from Epic 12.
- **Event Emission**: Operations will emit events (TransactionSentEvent, BalanceFetchedEvent) for extensibility and monitoring.
- **Strategy Pattern**: Gas estimation and fee calculation will use strategy pattern allowing pluggable implementations.

**Impact**:
- Operations become more resilient with automatic retry and fallback
- Clear service boundaries improve testability and maintainability
- Consistent DTOs improve type safety and API clarity
- Middleware enables cross-cutting concerns without code duplication
- Events enable monitoring and custom business logic

**Related Epics**:
- Epic 11: Exception Handling & Error Management (retry, circuit breaker, fallback)
- Epic 12: Integration API & Internal Extensibility (services, DTOs, middleware, events)

**Action Required**:
- Review existing operation implementations for service extraction
- Prepare for DTO-based request/response patterns
- Update tests to work with service layer and middleware
- Consider custom strategies for gas estimation if needed

