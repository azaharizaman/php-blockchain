---
goal: Implement Ethereum (EVM) Driver for PHP Blockchain SDK
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Drivers Team
status: 'Planned'
tags: [feature,drivers,ethereum,evm]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This implementation plan defines deterministic tasks to implement the Ethereum driver as described in `docs/prd/02-ETHEREUM-DRIVER-EPIC.md`. Tasks include exact file paths, method signatures, tests, and validation steps to be executed by automated agents or developers without further interpretation.

## 1. Requirements & Constraints

- **REQ-001**: Implement `src/Drivers/EthereumDriver.php` that implements `Blockchain\Contracts\BlockchainDriverInterface`.
- **REQ-002**: Connect to JSON-RPC endpoints; support Infura/Alchemy/self-hosted nodes via `endpoint` config key.
- **REQ-003**: Support ETH balance, ERC-20 token balances, sending signed raw transactions, and gas estimation.
- **REQ-004**: Provide ABI encode/decode helpers in `src/Utils/Abi.php` or similar.
- **CON-001**: Integration tests against public networks must be gated by `RUN_INTEGRATION_TESTS` secret.
- **GUD-001**: Use Guzzle MockHandler for unit tests to avoid external network calls.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Create a skeleton driver with unit-tested JSON-RPC client flows and ABI helper stubs.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Create `src/Drivers/EthereumDriver.php` implementing `Blockchain\Contracts\BlockchainDriverInterface`. Add phpdoc typed arrays where needed. Methods: `connect(array $config): void`, `getBalance(string $address): float`, `sendTransaction(string $from, string $to, float $amount, array $options = []): string` (placeholder that throws if not implemented), `getTransaction(string $txHash): array`, `getBlock(int|string $blockNumber): array`, `estimateGas(...): ?int`, `getTokenBalance(...): ?float`, `getNetworkInfo(): ?array`. |  |  | File exists and `composer run phpstan` reports no typing errors. |
| TASK-002 | Add `src/Utils/Abi.php` with stubbed `encodeFunctionCall(string $signature, array $params): string` and `decodeResponse(string $signature, string $data): array`. |  |  | Unit tests for encoding/decoding basic ERC-20 `balanceOf` call pass (mocked expectations). |
| TASK-003 | Add unit tests `tests/EthereumDriverTest.php` covering connect, getBalance (mocked RPC return), getTransaction (mocked), getBlock (mocked), and not-implemented sendTransaction exception. Use Guzzle MockHandler. |  |  | `composer test` runs and Ethereum tests pass locally. |

Phase 1 Completion Criteria: Skeleton driver + ABI helpers created, unit tests pass, phpstan clean.

### Implementation Phase 2

- GOAL-002: Implement ERC-20/721 helpers, contract call/send helpers, gas strategies, and integrate caching.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-004 | Implement `estimateGas` using eth_estimateGas JSON-RPC and provide fallback heuristics. |  |  | Unit tests for estimateGas using mock RPC responses pass. |
| TASK-005 | Implement `getTokenBalance` that calls `eth_call` with ERC-20 `balanceOf` and decodes result via `Abi` helper. |  |  | Unit tests for ERC-20 balanceOf mock responses pass. |
| TASK-006 | Add `docs/drivers/ethereum.md` with usage examples and config schema. |  |  | `php scripts/check-driver-docs.php` returns success. |
| TASK-007 | Add optional integration tests under `tests/Integration/EthereumIntegrationTest.php` gated by `RUN_INTEGRATION_TESTS`. |  |  | Integration tests pass when `RUN_INTEGRATION_TESTS` is enabled. |

Phase 2 Completion Criteria: Full ERC-20 support in unit tests, docs present, and optional integration tests pass when enabled.

## 3. Alternatives

- **ALT-001**: Use an external ABI/eth client library (e.g., web3.php) — considered but deferred to reduce dependencies; implement minimal ABI helpers first.
- **ALT-002**: Use JSON-RPC over websockets for subscription events — deferred to Phase 3.

## 4. Dependencies

- **DEP-001**: `guzzlehttp/guzzle` for JSON-RPC transport.
- **DEP-002**: `phpunit/phpunit` and `phpstan/phpstan`.

## 5. Files

- **FILE-001**: `src/Drivers/EthereumDriver.php` — main driver file to create.
- **FILE-002**: `src/Utils/Abi.php` — ABI encoder/decoder helpers.
- **FILE-003**: `tests/EthereumDriverTest.php` — unit tests using MockHandler.
- **FILE-004**: `docs/drivers/ethereum.md` — driver documentation.

## 6. Testing

- **TEST-001**: Unit tests for getBalance, getTransaction, getBlock, estimateGas, getTokenBalance (mocked RPC responses). Command: `composer test`.
- **TEST-002**: PHPStan static analysis. Command: `composer run phpstan` (level 7). Validation: no errors.
- **TEST-003**: Optional integration tests (gated). Command: `composer run integration-test` with `RUN_INTEGRATION_TESTS=true` in CI env.

## 7. Risks & Assumptions

- **RISK-001**: ABI encoding/decoding correctness is critical; mistakes can produce invalid contract calls — mitigate by unit tests with known vectors.
- **ASSUMPTION-001**: Driver will use hex/wei conversions consistently; helper utilities will centralise conversions.

## 8. Related Specifications / Further Reading

- `docs/prd/02-ETHEREUM-DRIVER-EPIC.md`
- `docs/prd/01-CORE-UTILITIES-EPIC.md`

--
Identifiers: REQ-, TASK-, GOAL-, DEP-, FILE-, TEST-, RISK-, ALT-
