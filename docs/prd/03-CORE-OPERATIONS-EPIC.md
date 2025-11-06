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
