---
goal: Implement Deployment & Distribution for PHP Blockchain SDK
version: 1.0
date_created: 2025-11-06
last_updated: 2025-11-06
owner: Releases Team
status: 'Planned'
tags: [deployment,releases,distribution]
---

# Introduction

![Status: Planned](https://img.shields.io/badge/status-Planned-blue)

Derived from `docs/prd/09-DEPLOYMENT-DISTRIBUTION-EPIC.md`, this plan covers packaging, versioning, and distribution strategies (Composer, phar, Docker images).

## 1. Requirements & Constraints

- **REQ-001**: Publish to Packagist via Composer package metadata and automated release pipeline.
- **REQ-002**: Optionally provide a PHAR and Docker image for runtime integration.
- **CON-001**: Releases must be signed or use GitHub release automation with operator token.

## 2. Implementation Steps

### Implementation Phase 1

- GOAL-001: Add release automation and packaging metadata.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-001 | Ensure `composer.json` contains accurate `name`, `description`, `license`, and `authors`. | ✅ | 2025-11-06 | `composer validate` returns OK. |
| TASK-002 | Add GitHub Actions release workflow `.github/workflows/release.yml` to tag and publish releases using a token. |  |  | A draft release can be created via workflow dispatch. |

### Implementation Phase 2

- GOAL-002: Provide optional phar and Docker artifacts.

| Task | Description | Completed | Date | Validation Criteria |
|------|-------------|-----------|------|---------------------|
| TASK-003 | Add `build/phar.sh` script to build a PHAR artifact and include minimal startup CLI `bin/blockchain`. |  |  | `build/phar.sh` produces `dist/php-blockchain.phar` in dry-run mode. |
| TASK-004 | Add `docker/` Dockerfile and `docker/build.sh` that builds a lightweight image for integration tests. |  |  | `docker build` runs in CI dry-run mode and produces an image manifest. |

## 3. Alternatives

- **ALT-001**: Only support Packagist/composer — primary approach; phar/docker optional add-ons.

## 4. Dependencies

- **DEP-001**: GitHub release token for automated publishing.

## 5. Files

- **FILE-001**: `composer.json`
- **FILE-002**: `.github/workflows/release.yml`
- **FILE-003**: `build/phar.sh`
- **FILE-004**: `docker/Dockerfile`

## 6. Testing

- **TEST-001**: Validate packaging scripts in dry-run mode; verify `composer validate` and `php -v` compatibility in CI runner.

## 7. Risks & Assumptions

- **RISK-001**: Automated publishing must be protected by tight token permissions to avoid accidental releases.

## 8. Related Specifications / Further Reading

- `docs/prd/09-DEPLOYMENT-DISTRIBUTION-EPIC.md`
