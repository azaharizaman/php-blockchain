## Epic: Core Utilities

### 🎯 Goal & Value Proposition
Provide the foundational modules, interfaces, and utilities that all drivers and higher-level components rely on. This Epic ensures consistent integration points, shared helpers, and a stable core API that allows drivers to be hot-swapped and validated at runtime.

### ⚙️ Features & Requirements
1. Define `BlockchainDriverInterface` with core methods (connect, getBalance, sendTransaction, getTransaction, getBlock).
2. Implement `BlockchainManager` to orchestrate driver lifecycle and expose unified API.
3. Driver Registry (`DriverRegistry`) for runtime driver registration, discovery, and default driver management.
4. Exception hierarchy and structured error classes (ConfigurationException, UnsupportedDriverException, TransactionException, ValidationException).
5. Utility helpers (Address validation, Format conversion, Serialization helpers).
6. HTTP client wrapper and DI-friendly transport layer (Guzzle client adapter).
7. Configuration loader and unified config schema for drivers.
8. Basic caching and connection pooling helpers used across drivers.
9. PSR-4 autoloading and coding style enforcement (PSR-12).

### 🤝 Module Mapping & Dependencies
- PHP Namespace / Module: `Blockchain\Core` (mapped files live under `src/` top-level namespaces: `Blockchain\`, `Blockchain\Registry`, `Blockchain\Contracts`)
- Depends on: None (core), but other Epics depend on Core Utilities (Driver Architecture, Core Operations, Agentic Capabilities).

### ✅ Acceptance Criteria
- All core classes and interfaces are implemented and covered by unit tests.
- `BlockchainManager` can load and switch drivers at runtime and expose unified methods.
- Driver registration and discovery works with at least one registered driver (Solana) in CI.
- Utility helpers have unit tests and adhere to PSR-12 standards.
- Error and exception flows are predictable and documented.
