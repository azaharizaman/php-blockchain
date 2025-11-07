---
goal: Add Support for Additional Networks and Network Profiles
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Networks Team
status: 'Planned'
tags: [feature,networks,support]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

Plan derived from `docs/prd/07-SUPPORTED-NETWORKS-EPIC.md` to add structured support for multiple blockchains and network profiles (mainnet/testnet/custom endpoints).

## 1. Requirements & Constraints

- **REQ-001**: Provide a network profile registry `src/Config/NetworkProfiles.php` mapping logical names (ethereum.mainnet, solana.mainnet) to driver config templates.
- **REQ-002**: Support quick switching via `BlockchainManager->setDriver()` with predefined profiles.
- **CON-001**: Profiles must be serializable and validated via `ConfigLoader`.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Implement profile registry and sample profiles.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Create `src/Config/NetworkProfiles.php` with built-in profiles for `solana.mainnet`, `solana.devnet`, `ethereum.mainnet`, `ethereum.goerli` (or equivalent). |  |  | Profiles accessible via `NetworkProfiles::get('solana.mainnet')` and valid per `ConfigLoader::validateConfig`. |
| TASK-002 | Add CLI helper `bin/switch-network.php` for local dev to output config and optionally write to `config/active.json`. |  |  | CLI prints JSON config and exits 0 in dry-run mode. |

### Implementation Phase 2

- GOAL-002: Add discovery and validation for custom endpoints.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-003 | Implement `src/Utils/EndpointValidator.php` that checks URL reachability via optional ping or curl (dry-run, optional). |  |  | Validator runs in dry-run mode and returns valid/invalid without network calls by default. |

## 3. Alternatives

- **ALT-001**: Rely on external config management systems — deferred.

## 4. Dependencies

- **DEP-001**: None required for basic profiles; optional network probing uses curl or PHP streams.

## 5. Files

- **FILE-001**: `src/Config/NetworkProfiles.php`
- **FILE-002**: `bin/switch-network.php`
- **FILE-003**: `src/Utils/EndpointValidator.php`

## 6. Testing

- **TEST-001**: Unit tests for profile retrieval and validation.

## 7. Risks & Assumptions

- **RISK-001**: Profiles must be maintained as networks evolve; include a doc maintenance task.

## 8. Related Specifications / Further Reading

- `docs/prd/07-SUPPORTED-NETWORKS-EPIC.md`
