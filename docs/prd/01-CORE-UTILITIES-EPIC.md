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

---

### Changelog

#### 2025-11-08: Enhanced Exception Handling & Extensibility Integration

**Changes**:
- **Extended Exception Requirements**: Core exception classes (ConfigurationException, UnsupportedDriverException, TransactionException, ValidationException) will be enhanced with error codes, context enrichment, and standardized error messages as part of the Exception Handling Epic (Epic 11).
- **Service Abstraction**: Core utilities will integrate with the Service Layer architecture from the Integration API Epic (Epic 12), providing service interfaces for core operations.
- **Event System Integration**: Core components will emit events (DriverRegistered, DriverConnected) through the event system defined in Epic 12.
- **DI Container**: Core utilities will support dependency injection through the PSR-11 container from Epic 12.
- **Configuration Enhancement**: Configuration system will be enhanced with hierarchical configuration and validation from Epic 12.

**Impact**:
- Exception classes remain backward compatible but gain new capabilities
- Core interfaces remain stable, implementation enhanced with service layer
- Driver registration triggers events for extensibility
- Configuration system becomes more robust and validatable

**Related Epics**:
- Epic 11: Exception Handling & Error Management
- Epic 12: Integration API & Internal Extensibility

**Action Required**:
- Review exception usage in existing drivers
- Prepare for event system integration in driver lifecycle
- Update configuration to use new hierarchical format (backward compatible)

