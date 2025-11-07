---
goal: Implement Core Utilities for PHP Blockchain SDK
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Core Team
status: 'Planned'
tags: [feature,core,utilities,architecture]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This implementation plan specifies atomic, deterministic tasks to implement the Core Utilities epic described in `docs/prd/01-CORE-UTILITIES-EPIC.md`. Each task includes exact file paths, required edits, and machine-verifiable validation criteria.

## 1. Requirements & Constraints

- **REQ-001**: Implement `Blockchain\Contracts\BlockchainDriverInterface` with core methods and typed phpdoc.
- **REQ-002**: Implement `BlockchainManager` for driver lifecycle and unified API.
- **REQ-003**: Implement `DriverRegistry` for runtime registration and default driver management.
- **REQ-004**: Implement structured exception classes under `src/Exceptions/`.
- **GUD-001**: Use PSR-4 autoloading; enforce PSR-12 coding standards.
- **SEC-001**: No network calls during unit tests; use MockHandler for HTTP.
- **CON-001**: Target PHP 8.2+, static analysis: PHPStan level 7.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Create core contracts, manager, registry, and exceptions, and add basic unit tests.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Create `src/Contracts/BlockchainDriverInterface.php` with methods: `connect(array $config): void`, `getBalance(string $address): float`, `sendTransaction(...): string`, `getTransaction(...): array`, `getBlock(...): array`, `estimateGas(...): ?int`, `getTokenBalance(...): ?float`, `getNetworkInfo(): ?array`. Add phpdoc typed iterables `array<string,mixed>`. |  |  | File exists and `composer run phpstan` reports no interface typing errors. |
| TASK-002 | Implement `src/BlockchainManager.php` with driver switching methods: constructor, `setDriver(string $name, array $config)`, `getBalance`, `sendTransaction`, `getTransaction`, `getBlock`, `estimateGas`, `getTokenBalance`, `getNetworkInfo`, `getSupportedDrivers`. Add phpdoc annotations matching interface. |  |  | Unit tests validate driver switching and exception thrown when driver not configured. |
| TASK-003 | Implement `src/Registry/DriverRegistry.php` with `registerDriver`, `getDriver`, `hasDriver`, `getRegisteredDrivers`, and `registerDefaultDrivers()` registering `'solana' => \Blockchain\Drivers\SolanaDriver::class`. Annotate `@var array<string,string> $drivers`. |  |  | `BlockchainManager->getSupportedDrivers()` returns `['solana']` and phpstan passes. |
| TASK-004 | Create exception classes: `src/Exceptions/ConfigurationException.php`, `UnsupportedDriverException.php`, `TransactionException.php`, `ValidationException.php`. Each extend `\Exception` and live in namespace `Blockchain\Exceptions`. |  |  | Exceptions autoloadable and referenced types resolved by phpstan. |
| TASK-005 | Add utilities skeleton under `src/Utils/`: `AddressValidator.php`, `Serializer.php`, `HttpClientAdapter.php` (Guzzle wrapper). Provide minimal implementations and unit tests under `tests/Utils/`. |  |  | Unit tests for AddressValidator pass with mocked inputs. |

Phase 1 Completion Criteria: All files created, phpstan returns no errors, and unit tests for core utilities pass.

### Implementation Phase 2

- GOAL-002: Harden configuration loader, caching helpers, and DI-friendly transport layer.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-006 | Implement `src/Config/ConfigLoader.php` to load driver configs from array, env, or file. Add schema validation method `validateConfig(array $config): bool`. |  |  | `ConfigLoader::validateConfig` returns true for a valid Solana config example. |
| TASK-007 | Implement `src/Utils/CachePool.php` (PSR-6 or simple array-backed cache) and integrate into drivers via constructor injection. |  |  | Drivers accept optional cache dependency and unit tests demonstrate cached response usage. |
| TASK-008 | Add `src/Transport/GuzzleAdapter.php` implementing `HttpClientAdapter` interface to centralise HTTP settings and error handling. |  |  | Adapter used by Solana driver and unit tests for HTTP error mapping pass. |

Phase 2 Completion Criteria: ConfigLoader, CachePool, and GuzzleAdapter implemented and covered by tests.

## 3. Alternatives

- **ALT-001**: Use Symfony Config component for schema validation — rejected to keep core lightweight.
- **ALT-002**: Adopt PSR-18 HTTP Client interface — considered for future refactor (deferred to Phase 3).

## 4. Dependencies

- **DEP-001**: `guzzlehttp/guzzle` for HTTP transport.
- **DEP-002**: `phpunit/phpunit` and `phpstan/phpstan` for testing and static analysis.

## 5. Files

- **FILE-001**: `src/Contracts/BlockchainDriverInterface.php` — driver contract.
- **FILE-002**: `src/BlockchainManager.php` — orchestrator.
- **FILE-003**: `src/Registry/DriverRegistry.php` — registry and defaults.
- **FILE-004**: `src/Exceptions/*.php` — exception classes.
- **FILE-005**: `src/Utils/*` — utilities (AddressValidator, Serializer, HttpClientAdapter).
- **FILE-006**: `src/Config/ConfigLoader.php` — config loader (Phase 2).

## 6. Testing

- **TEST-001**: Unit tests for `BlockchainManager` driver switching and error flows.
- **TEST-002**: Unit tests for `DriverRegistry` registration and retrieval.
- **TEST-003**: Unit tests for AddressValidator and Serializer.
- **TEST-004**: PHPStan: `composer run phpstan` level 7 returns [OK] No errors.

## 7. Risks & Assumptions

- **RISK-001**: Early tight typing may require iterative refactoring for driver authors.
- **ASSUMPTION-001**: All tests will use MockHandler for HTTP; no external network calls during unit tests.

## 8. Related Specifications / Further Reading

- `docs/prd/01-CORE-UTILITIES-EPIC.md`
- `docs/prd/00-PRD-BREAKDOWN.md`

--
Identifiers: REQ-, TASK-, GOAL-, DEP-, FILE-, TEST-, RISK-, ALT-
