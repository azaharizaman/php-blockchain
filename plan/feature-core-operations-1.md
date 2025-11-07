---
goal: Implement Core Operations (transaction workflows, wallets, batching)
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Core Operations Team
status: 'Planned'
tags: [feature,operations,transactions,wallets]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

This plan converts the PRD `docs/prd/03-CORE-OPERATIONS-EPIC.md` into deterministic, machine-readable tasks focused on transaction lifecycle, wallet abstraction, batching, and monitoring hooks.

## 1. Requirements & Constraints

- **REQ-001**: Implement transaction orchestration helpers for preparing, signing, and broadcasting transactions across drivers.
- **REQ-002**: Provide wallet abstraction (`src/Wallet/WalletInterface.php`) and an HSM-friendly key provider adapter.
- **REQ-003**: Support batching, retries, idempotency tokens, and transaction queuing for high-throughput clients.
- **SEC-001**: Private keys must never be logged; key material handled via secure interfaces only.
- **CON-001**: All network interactions must be mockable and covered by unit tests with MockHandler.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Create transaction workflow primitives and wallet abstraction.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Add `src/Wallet/WalletInterface.php` with methods `getPublicKey(): string`, `sign(bytestring $payload): string`, `getAddress(): string`. |  |  | Interface exists and phpstan reports no issues. |
| TASK-002 | Implement `src/Operations/TransactionBuilder.php` to assemble driver-specific transactions. |  |  | Unit tests demonstrate builder producing expected payload shapes for Solana and Ethereum stubs. |
| TASK-003 | Implement `src/Operations/TransactionQueue.php` (simple in-memory queue) with retry/backoff policy. |  |  | Unit tests cover enqueue/dequeue/retry behaviors. |

Phase 1 Completion Criteria: Wallet interface, transaction builder, and queue implemented with unit tests.

### Implementation Phase 2

- GOAL-002: Add batching, idempotency, and monitoring hooks.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-004 | Implement `src/Operations/Batcher.php` that groups transactions and submits batched RPC calls when supported. |  |  | Integration-like unit tests show batch grouping for mock drivers. |
| TASK-005 | Add idempotency token support `Idempotency::generate()` and persistent store integration optional via `src/Storage/` adapter. |  |  | Idempotency keys prevent duplicate broadcasts in tests. |
| TASK-006 | Expose hooks for telemetry: `src/Telemetry/OperationTracer.php` (no-op default). |  |  | Hooks called in transaction lifecycle unit tests. |

Phase 2 Completion Criteria: Batcher, idempotency, and telemetry hooks implemented and covered by tests.

## 3. Alternatives

- **ALT-001**: Use external queue (Rabbit/Kafka) — deferred; initial implementation uses in-memory queue and an adapter interface for future pluggable persistence.

## 4. Dependencies

- **DEP-001**: `guzzlehttp/guzzle` for RPC transport.
- **DEP-002**: Optional persistence adapters (Redis/DB) for idempotency/store.

## 5. Files

- **FILE-001**: `src/Wallet/WalletInterface.php`
- **FILE-002**: `src/Operations/TransactionBuilder.php`
- **FILE-003**: `src/Operations/TransactionQueue.php`
- **FILE-004**: `src/Operations/Batcher.php`
- **FILE-005**: `src/Telemetry/OperationTracer.php`

## 6. Testing

- **TEST-001**: Unit tests under `tests/Operations/` for builder, queue, batcher.
- **TEST-002**: Integration test harness (mocked drivers) demonstrating end-to-end transaction lifecycle.

## 7. Risks & Assumptions

- **RISK-001**: Batching semantics differ across networks; adapter pattern mitigates risk.
- **ASSUMPTION-001**: Wallet signing can be abstracted uniformly via `WalletInterface`.

## 8. Related Specifications / Further Reading

- `docs/prd/03-CORE-OPERATIONS-EPIC.md`
