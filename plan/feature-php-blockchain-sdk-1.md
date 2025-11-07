---
goal: Implement core PHP Blockchain SDK and drivers (agent-aware) as specified by PRDs
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Core Team
status: 'Planned'
tags: [feature,agent,drivers,sdk,architecture]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This implementation plan is deterministic, machine-readable, and executable with no human interpretation. It maps PRD epics in `docs/prd/` to concrete, atomic tasks with file paths, expected edits, and verification steps. Each task uses standardized identifiers (REQ-, TASK-, GOAL-, DEP-, FILE-, TEST-, RISK-, ALT-) and includes exact validation criteria.

## 1. Requirements & Constraints

- **REQ-001**: Provide a `BlockchainManager` orchestrator with pluggable drivers implementing `Blockchain\Contracts\BlockchainDriverInterface`.
- **REQ-002**: Ensure drivers implement methods: `connect(array $config): void`, `getBalance(string $address): float`, `sendTransaction(string $from, string $to, float $amount, array $options = []): string`, `getTransaction(string $txHash): array`, `getBlock(int|string $blockNumber): array`, `estimateGas(...): ?int`, `getTokenBalance(...): ?float`, `getNetworkInfo(): ?array`.
- **REQ-003**: Use PSR-4 autoloading and PHP 8.2 strict types; static analysis target: PHPStan level 7.
- **SEC-001**: Agent network access disabled by default; toggleable via `scripts/toggle-agent-network.sh` and `.copilot/agent.yml`.
- **GUD-001**: Tests must use Guzzle MockHandler for RPC responses; integration tests that hit public endpoints must be gated behind `RUN_INTEGRATION_TESTS` secret.
- **PAT-001**: All drivers must have documentation files under `docs/drivers/{driver}.md`; generator and checker scripts exist at `scripts/generate-driver-docs.php` and `scripts/check-driver-docs.php`.
- **CON-001**: CI must run phpstan via composer script `phpstan` and run unit tests via `composer test` on PRs.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Stabilize existing codebase so PHPStan (level 7) reports no errors and unit tests run for current drivers.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Add precise iterable/value types and phpdoc annotations to interfaces and implementations. Files to edit: `src/Contracts/BlockchainDriverInterface.php`, `src/BlockchainManager.php`, `src/Drivers/SolanaDriver.php`, `src/Registry/DriverRegistry.php`. Exact edits: add `@param array<string,mixed>` for array params, `@return array<string,mixed>` for array returns, annotate `@var array<string,string>` for registry maps. | ✅ | 2025-11-06 | `composer run phpstan` returns [OK] No errors. |
| TASK-002 | Fix tests to ensure `GuzzleHttp\Psr7\Response` constructor body arg is a string (cast `json_encode()` outputs). File: `tests/SolanaDriverTest.php`. Exact edits: wrap `json_encode(...)` with `(string) json_encode(...)` for all Response bodies. | ✅ | 2025-11-06 | `composer run phpstan` returns [OK] No errors; `composer test` executes relevant tests without type-related failures. |
| TASK-003 | Add `phpstan/phpstan` to `require-dev` and add composer script `phpstan`. File: `composer.json`. Exact edits: add `"phpstan/phpstan": "^1.10"` to `require-dev` and script entry `"phpstan": "vendor/bin/phpstan analyse --memory-limit=1G --level=7 src tests"`. | ✅ | 2025-11-06 | `composer install` installs phpstan and `composer run phpstan` executes successfully. |

Phase 1 Completion Criteria: All tasks completed and verified locally; CI phpstan job passes on PRs.

### Implementation Phase 2

- GOAL-002: Implement additional drivers, documentation, and CI wiring for long-term maintenance and agentic tasks.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-004 | Implement `src/Drivers/EthereumDriver.php` as a skeleton implementing `Blockchain\Contracts\BlockchainDriverInterface`. Exact methods and phpdoc as specified in REQ-002. |  |  | Unit tests `tests/EthereumDriverTest.php` added; `composer test` passes. |
| TASK-005 | Add `docs/drivers/ethereum.md` and ensure `scripts/check-driver-docs.php` passes. |  |  | `php scripts/check-driver-docs.php` returns success. |
| TASK-006 | Register new drivers in `src/Registry/DriverRegistry.php` within `registerDefaultDrivers()` by adding `registerDriver('ethereum', \Blockchain\Drivers\EthereumDriver::class)`. |  |  | `BlockchainManager->getSupportedDrivers()` includes `ethereum`. |
| TASK-007 | Add phpstan and phpunit execution to `.github/workflows/agent-tasks.yml` with integration tests gated by `RUN_INTEGRATION_TESTS` env var. Exact YAML change: add step `run: composer run phpstan` and ensure conditional `if: env.RUN_INTEGRATION_TESTS == 'true'` around integration-test job. |  |  | CI passes on PRs with static analysis and unit tests. |

Phase 2 Completion Criteria: New drivers and docs in place, CI updated, and checks pass on PRs.

## 3. Alternatives

- **ALT-001**: Stronger typed signatures using generics phpdoc templates — rejected for initial phase due to increased complexity for driver authors.
- **ALT-002**: Runtime schema validation for driver configs (e.g., using opis/json-schema) — rejected to avoid additional runtime dependencies in core.

## 4. Dependencies

- **DEP-001**: composer packages: `guzzlehttp/guzzle`, `phpunit/phpunit`, `phpstan/phpstan`, `squizlabs/php_codesniffer`.
- **DEP-002**: GitHub Actions runner with PHP and composer available.
- **DEP-003**: Repository secret `RUN_INTEGRATION_TESTS` for gated integration tests.

## 5. Files

- **FILE-001**: `src/Contracts/BlockchainDriverInterface.php` — interface defining driver contract; add phpdoc type annotations.
- **FILE-002**: `src/BlockchainManager.php` — orchestrator; add phpdoc and ensure typed returns.
- **FILE-003**: `src/Registry/DriverRegistry.php` — registry; annotate types and class-string for drivers.
- **FILE-004**: `src/Drivers/SolanaDriver.php` — existing driver; ensure phpdoc types.
- **FILE-005**: `src/Drivers/EthereumDriver.php` — new file to implement in Phase 2.
- **FILE-006**: `tests/SolanaDriverTest.php` — adjust Response bodies to string; maintained.
- **FILE-007**: `tests/EthereumDriverTest.php` — new tests to add in Phase 2.
- **FILE-008**: `composer.json` — add dev deps and scripts.
- **FILE-009**: `scripts/generate-driver-docs.php`, `scripts/check-driver-docs.php` — docs automation.
- **FILE-010**: `docs/drivers/*.md` — driver docs.

## 6. Testing

- **TEST-001**: PHPStan static analysis (level 7). Command: `composer run phpstan`. Validation: returns [OK] No errors.
- **TEST-002**: Unit tests: `composer test` executes PHPUnit tests. Validation: Solana unit tests pass; newly added driver tests pass.
- **TEST-003**: Docs presence check: `php scripts/check-driver-docs.php`. Validation: exits 0 when all drivers have docs.

## 7. Risks & Assumptions

- **RISK-001**: High phpstan level may reveal many typing issues across future drivers — mitigated by phased phpdoc upgrades.
- **RISK-002**: Integration tests against public RPC endpoints are flaky — mitigated by gating and using MockHandler for unit tests.
- **ASSUMPTION-001**: CI runners provide PHP 8.2+ and composer.

## 8. Related Specifications / Further Reading

- `docs/prd/00-PRD-BREAKDOWN.md`
- `docs/prd/01-CORE-UTILITIES-EPIC.md`
- `docs/prd/02-ETHEREUM-DRIVER-EPIC.md`
- `docs/prd/03-CORE-OPERATIONS-EPIC.md`
- `docs/prd/04-SECURITY-RELIABILITY-EPIC.md`
- `docs/prd/05-AGENTIC-CAPABILITIES-EPIC.md`
- `docs/prd/06-PERFORMANCE-MONITORING-EPIC.md`
- `docs/prd/07-SUPPORTED-NETWORKS-EPIC.md`
- `docs/prd/08-DOCUMENTATION-TESTING-QA-EPIC.md`
- `docs/prd/09-DEPLOYMENT-DISTRIBUTION-EPIC.md`
- `docs/prd/10-ENTERPRISE-FEATURES-EPIC.md`

--
Standardized identifiers used in this plan:
- REQ-xxx: Requirements
- TASK-xxx: Implementation tasks
- GOAL-xxx: Phase goals
- DEP-xxx: Dependencies
- FILE-xxx: Files referenced
- TEST-xxx: Test cases
- RISK-xxx: Risks
- ALT-xxx: Alternatives
---
goal: Implement core PHP Blockchain SDK and drivers (agent-aware) as specified by PRDs
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Core Team
status: 'Planned'
tags: ["feature","agent","drivers","sdk","architecture"]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This implementation plan provides an explicit, machine-parseable, and deterministic set of phases and tasks to implement the core PHP Blockchain SDK (agent-aware) defined in the repository PRDs located under `docs/prd/`. The plan is intended to be executed by automated agents or humans with no additional interpretation. Each task includes exact file paths, target functions/methods, and validation criteria.

## 1. Requirements & Constraints

- **REQ-001**: Provide a core `BlockchainManager` orchestrator with pluggable drivers implementing `Blockchain\Contracts\BlockchainDriverInterface`.
- **REQ-002**: Implement at least a Solana driver (`src/Drivers/SolanaDriver.php`) and prepare scaffolding for Ethereum and other drivers.
- **REQ-003**: Use PSR-4 autoloading (existing `composer.json`), PHP 8.2+ type strictness, and PSR-12 coding standards.
- **SEC-001**: Agent network access must be disabled by default and toggleable via `./scripts/toggle-agent-network.sh` and `.copilot/agent.yml` (safety constraint).
- **GUD-001**: Tests must use Guzzle MockHandler for RPC responses (see `tests/SolanaDriverTest.php`).
- **CON-001**: CI integration tests must be gated behind repository secret `RUN_INTEGRATION_TESTS` (see `.github/workflows/agent-tasks.yml`).
- **PAT-001**: All drivers must have driver docs in `docs/drivers/{driver}.md` and generation/check scripts must exist (`scripts/generate-driver-docs.php`, `scripts/check-driver-docs.php`).

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Stabilize core types, static analysis, and unit tests so the repository is CI-clean at phpstan level 7 and PHPUnit passes for existing tests.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Add precise iterable/value types and phpdoc annotations to interfaces and implementations to satisfy phpstan level 7. Files: `src/Contracts/BlockchainDriverInterface.php`, `src/BlockchainManager.php`, `src/Drivers/SolanaDriver.php`, `src/Registry/DriverRegistry.php`. Exact edits: add `@param array<string,mixed>` where `array $config` is used, add `@return array<string,mixed>` for functions returning arrays, annotate `@var array<string,string>` for registry maps. | ✅ | 2025-11-06 | `composer run phpstan` returns [OK] No errors. |
| TASK-002 | Fix test Response body types to avoid `json_encode` false return types. File: `tests/SolanaDriverTest.php`. Exact edit: cast `json_encode(...)` to `(string) json_encode(...)` for all `new Response(200, [], ...)` calls. | ✅ | 2025-11-06 | `composer run phpstan` returns [OK] No errors and `composer test` runs tests (see Phase 1 Validation). |
| TASK-003 | Install and pin phpstan as a dev dependency and add composer script `phpstan`. File: `composer.json`. Exact edit: add `phpstan/phpstan` to `require-dev` and a script entry `"phpstan": "vendor/bin/phpstan analyse --memory-limit=1G --level=7 src tests"`. | ✅ | 2025-11-06 | `composer install` installs phpstan and `composer run phpstan` executes. |

Phase 1 Completion Criteria: All tasks marked ✅ and CI-level phpstan step passes locally and in CI for PRs.

### Implementation Phase 2

- GOAL-002: Expand drivers, documentation, and CI to cover new drivers and agentic features.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-004 | Implement `src/Drivers/EthereumDriver.php` (skeleton) implementing `Blockchain\Contracts\BlockchainDriverInterface`. Exact file to create: `src/Drivers/EthereumDriver.php`. Methods to implement: `connect(array $config): void`, `getBalance(string $address): float`, `sendTransaction(string $from, string $to, float $amount, array $options = []): string`, `getTransaction(string $txHash): array`, `getBlock(int|string $blockNumber): array`, `estimateGas(...): ?int`, `getTokenBalance(...): ?float`, `getNetworkInfo(): ?array`. Include phpdoc `@param array<string,mixed>` and `@return array<string,mixed>` where applicable. |  |  | Unit tests: `tests/EthereumDriverTest.php` added; `composer test` passes. |
| TASK-005 | Add driver docs `docs/drivers/ethereum.md` using `scripts/generate-driver-docs.php` conventions. Ensure `scripts/check-driver-docs.php` passes. |  |  | `php scripts/check-driver-docs.php` returns success. |
| TASK-006 | Add driver registration to `src/Registry/DriverRegistry.php` registerDefaultDrivers: register 'ethereum' and optional networks. |  |  | `BlockchainManager->getSupportedDrivers()` includes `ethereum`. |
| TASK-007 | Wire phpstan and phpunit into `.github/workflows/agent-tasks.yml` to run on PRs and main; ensure integration tests gated by secret. Exact edits: add job step to run `composer run phpstan` and ensure `if: env.RUN_INTEGRATION_TESTS == 'true'` gating. |  |  | CI passes on a PR branch with tests and static analysis. |

Phase 2 Completion Criteria: Added drivers present, docs added, CI runs static analysis and tests for new drivers, and docs check step passes.

## 3. Alternatives

- **ALT-001**: Use stricter typing in signatures (e.g., generics via phpdoc templates) instead of broad `array<string,mixed>` — rejected because it increases initial implementation time and complexity for driver authors.
- **ALT-002**: Use a runtime schema validator for driver configs instead of phpdoc typing — rejected as it adds runtime overhead and extra dependencies; prefer static analysis first.

## 4. Dependencies

- **DEP-001**: composer packages: `guzzlehttp/guzzle`, `phpunit/phpunit`, `phpstan/phpstan`, `squizlabs/php_codesniffer`.
- **DEP-002**: GitHub Actions runner environment (ubuntu-latest) with `composer` available.
- **DEP-003**: Repository secret `RUN_INTEGRATION_TESTS` for gating integration tests in CI.

## 5. Files

- **FILE-001**: `src/Contracts/BlockchainDriverInterface.php` — Interface to implement; add phpdoc typed arrays.
- **FILE-002**: `src/BlockchainManager.php` — Orchestrator. Ensure phpdoc and error handling consistent with interface.
- **FILE-003**: `src/Registry/DriverRegistry.php` — Registry map and default registrations.
- **FILE-004**: `src/Drivers/SolanaDriver.php` — Existing Solana driver; ensure typed phpdoc and stable behavior.
- **FILE-005**: `src/Drivers/EthereumDriver.php` — New file to create as per TASK-004.
- **FILE-006**: `tests/SolanaDriverTest.php` — Tests that use Guzzle MockHandler; validated/corrected.
- **FILE-007**: `tests/EthereumDriverTest.php` — New test file to create alongside the driver.
- **FILE-008**: `composer.json` — Add phpstan dev dependency and scripts if missing.
- **FILE-009**: `scripts/generate-driver-docs.php`, `scripts/check-driver-docs.php` — Docs automation.
- **FILE-010**: `docs/drivers/*.md` — Driver documentation files (solana.md, ethereum.md, etc.).

## 6. Testing

- **TEST-001**: Unit tests for Solana driver already present: `tests/SolanaDriverTest.php` (happy path + error path + not implemented throws). Validation: `composer test` passes for this test.
- **TEST-002**: New unit tests for Ethereum driver: `tests/EthereumDriverTest.php` — use MockHandler to simulate RPC JSON-RPC responses. Include tests: connect, getBalance, getTransaction, sendTransaction stub, getBlock.
- **TEST-003**: PHPStan static analysis: run `composer run phpstan` (level 7). Validation: returns [OK] No errors.
- **TEST-004**: Docs presence check: `php scripts/check-driver-docs.php` returns success.

## 7. Risks & Assumptions

- **RISK-001**: Adding strict types and high phpstan level can surface many legacy issues — mitigated by phased fixes and phpdoc annotations.
- **RISK-002**: Integration tests that hit public RPC endpoints are flaky and can fail CI — mitigated by gating integration tests behind `RUN_INTEGRATION_TESTS` secret and using MockHandler for unit tests.
- **ASSUMPTION-001**: Composer and vendor binaries are available in CI runners (`vendor/bin/phpstan`, `vendor/bin/phpunit`).
- **ASSUMPTION-002**: Driver authors will follow phpdoc conventions established here.

## 8. Related Specifications / Further Reading

- `docs/prd/00-PRD-BREAKDOWN.md`
- `docs/prd/01-CORE-UTILITIES-EPIC.md`
- `docs/prd/02-ETHEREUM-DRIVER-EPIC.md`
- `docs/prd/03-CORE-OPERATIONS-EPIC.md`
- `docs/prd/04-SECURITY-RELIABILITY-EPIC.md`
- `docs/prd/05-AGENTIC-CAPABILITIES-EPIC.md`
- `docs/prd/06-PERFORMANCE-MONITORING-EPIC.md`
- `docs/prd/07-SUPPORTED-NETWORKS-EPIC.md`
- `docs/prd/08-DOCUMENTATION-TESTING-QA-EPIC.md`
- `docs/prd/09-DEPLOYMENT-DISTRIBUTION-EPIC.md`
- `docs/prd/10-ENTERPRISE-FEATURES-EPIC.md`
