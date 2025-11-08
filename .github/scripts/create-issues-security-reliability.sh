#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Security & Reliability Implementation
# Usage: ./create-issues-security-reliability.sh [REPO]
# Example: ./create-issues-security-reliability.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Security & Reliability"

echo "Creating GitHub issues for Security & Reliability..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Improve security posture and runtime reliability: secrets handling, logging, rate-limits, retries, circuit breakers, and resilience testing" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "security"
    "reliability"
    "ops"
    "logging"
    "secrets"
    "testing"
    "resilience"
    "phase-1"
    "phase-2"
)

for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" 2>/dev/null | grep -q "^$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO" 2>/dev/null || echo "  (label may already exist)"
    fi
done
echo "✓ All required labels ensured"
echo ""

print_issue_header() {
    echo "Creating Issue $1: $2..."
}

# Issue 1: Secrets & Key Handling
print_issue_header "1" "Secrets & Key Handling"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Secure secrets, key handling and HSM adapters" \
    --milestone "$MILESTONE" \
    --label "security,secrets,phase-1" \
    --body "## Overview
Harden secret handling across the SDK: avoid in-memory plaintext retention when possible, provide HSM-friendly providers, and ensure configuration secrets are loaded from secure sources.

## Requirements
- Ensure private keys and tokens are never logged or exposed
- Provide HSM/KeyVault adapter patterns for signing
- Ensure config loading supports env/secret managers and explicit file paths with strict permissions

## Implementation Checklist

### 1. Secret loader and adapters
- [ ] Add \`src/Security/SecretProviderInterface.php\` describing \`get(string \$name): string\` and optional \`has(string \$name): bool\`
- [ ] Provide in-memory and environment-backed implementations (\`src/Security/EnvSecretProvider.php\`)
- [ ] Add HSM/KeyVault adapter skeleton (\`src/Security/HsmSecretProvider.php\`) with clear docs about not returning raw key material when possible

### 2. Key handling
- [ ] Audit code paths that accept private keys and ensure they accept provider wrappers instead of raw strings
- [ ] Add helper to zero-out sensitive buffers when possible
- [ ] Add domain exception \`SecurityException\` and document throw points

### 3. Tests and validation
- [ ] Create \`tests/Security/SecretProviderTest.php\` covering provider contracts and failure modes
- [ ] Run static analysis: \`composer run phpstan\`
- [ ] Ensure secrets do not appear in logs using mock logger assertions

## Acceptance Criteria
- [x] SecretProvider interface and at least two implementations exist
- [x] Code paths use providers rather than raw string keys where practical
- [x] Tests and static analysis pass

## Files Created
- \`src/Security/SecretProviderInterface.php\`
- \`src/Security/EnvSecretProvider.php\`
- \`src/Security/HsmSecretProvider.php\`
- \`tests/Security/SecretProviderTest.php\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Secure Logging & Audit
print_issue_header "2" "Secure Logging & Audit"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Implement secure logging, redaction and audit trails" \
    --milestone "$MILESTONE" \
    --label "security,logging,phase-1" \
    --body "## Overview
Implement structured logging with redaction rules to prevent sensitive data leakage. Provide audit hooks for critical operations.

## Requirements
- Structured (JSON) logging optional via PSR-3 adapter
- Redaction of secrets and private fields by default
- Audit events for key lifecycle operations (key creation/import, rotation, critical config changes)

## Implementation Checklist

### 1. Logging
- [ ] Add \`src/Logging/RedactingLogger.php\` that implements PSR-3 and masks sensitive fields
- [ ] Make logger injectable across components and default to a no-op logger in non-production
- [ ] Document redaction rules and allow customization via configuration

### 2. Audit hooks
- [ ] Add \`src/Audit/AuditRecorderInterface.php\` and a simple file-backed recorder for tests
- [ ] Wire audit recorder into critical flows (key import, rotation, idempotency key invalidation)

### 3. Tests and validation
- [ ] Create \`tests/Logging/RedactingLoggerTest.php\` to assert masking behavior
- [ ] Ensure logging integration does not expose secrets in tests (mocked logger assertions)

## Acceptance Criteria
- [x] Redacting logger and audit recorder skeletons exist
- [x] Tests verify no secret leakage via logs
- [x] Documentation explains how to configure redaction

## Files Created
- \`src/Logging/RedactingLogger.php\`
- \`src/Audit/AuditRecorderInterface.php\`
- \`tests/Logging/RedactingLoggerTest.php\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Retry & Rate Limiting Policies
print_issue_header "3" "Retry and Rate Limiting"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement retry policies and client-side rate limiting" \
    --milestone "$MILESTONE" \
    --label "reliability,ops,testing,phase-1" \
    --body "## Overview
Provide robust retry/backoff policies and client-side rate limiting knobs to improve reliability under transient failures and protect downstream RPC endpoints.

## Requirements
- Exponential backoff with jitter and configurable caps
- Client-side rate limiting (token-bucket or leaky-bucket) pluggable per-driver
- Respect HTTP 429 and relevant RPC error codes

## Implementation Checklist

### 1. Retry policies
- [ ] Add \`src/Reliability/RetryPolicy.php\` that accepts base/backoff/jitter/maxAttempts
- [ ] Provide helper to apply policy in HTTP adapter wrappers
- [ ] Ensure policies are unit-testable with fake clocks

### 2. Rate limiting
- [ ] Add \`src/Reliability/RateLimiter.php\` with in-memory token-bucket implementation
- [ ] Make rate limiter injectable per driver or transport adapter

### 3. Tests and validation
- [ ] Create \`tests/Reliability/RetryPolicyTest.php\` and \`tests/Reliability/RateLimiterTest.php\`
- [ ] Run \`composer run phpstan\` and unit tests

## Acceptance Criteria
- [x] Retry policy and rate limiter implementations exist and are test-covered
- [x] Drivers can plug policies without network-level changes

## Files Created
- \`src/Reliability/RetryPolicy.php\`
- \`src/Reliability/RateLimiter.php\`
- \`tests/Reliability/RetryPolicyTest.php\`
- \`tests/Reliability/RateLimiterTest.php\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Circuit Breaker & Bulkhead
print_issue_header "4" "Circuit Breaker & Bulkhead"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Add circuit breaker and bulkhead isolation patterns" \
    --milestone "$MILESTONE" \
    --label "reliability,ops,phase-2" \
    --body "## Overview
Add runtime protection patterns (circuit breaker and bulkhead) to prevent cascading failures when drivers or downstream services are unhealthy.

## Requirements
- Circuit breaker with failure thresholds, windowing, and cooldown
- Bulkhead isolation for concurrent dispatches to different networks/drivers

## Implementation Checklist

### 1. Circuit breaker
- [ ] Add \`src/Reliability/CircuitBreaker.php\` with configurable thresholds and windowing
- [ ] Provide interfaces to query state and force-open for maintenance

### 2. Bulkhead
- [ ] Add \`src/Reliability/Bulkhead.php\` to limit concurrent dispatches per driver
- [ ] Integrate with Batcher/TransactionQueue to respect concurrency caps

### 3. Tests and validation
- [ ] Create \`tests/Reliability/CircuitBreakerTest.php\` and \`tests/Reliability/BulkheadTest.php\`

## Acceptance Criteria
- [x] Circuit breaker and bulkhead primitives exist
- [x] Tests verify state transitions and concurrency limits

## Files Created
- \`src/Reliability/CircuitBreaker.php\`
- \`src/Reliability/Bulkhead.php\`
- \`tests/Reliability/CircuitBreakerTest.php\`
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Chaos & Resilience Testing
print_issue_header "5" "Chaos and Resilience Tests"
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add chaos testing harness and resilience scenarios" \
    --milestone "$MILESTONE" \
    --label "resilience,testing,phase-2" \
    --body "## Overview
Add a simple chaos testing harness and resilience scenarios (network partitions, high-latency, intermittent RPC failures) to validate the system's behavior under failure.

## Requirements
- Define reproducible failure scenarios for integration tests
- Allow toggling of failure modes via environment variables or test helpers

## Implementation Checklist

### 1. Chaos harness
- [ ] Add \`tests/Resilience/chaos-harness.php\` that can inject faults into driver stubs
- [ ] Provide scenarios: latency injection, rate limit spikes, intermittent errors, partial batch failures

### 2. CI integration
- [ ] Add non-blocking CI job that runs a subset of chaos scenarios nightly
- [ ] Document how to run chaos harness locally

### 3. Validation
- [ ] Create \`tests/Resilience/ResilienceScenariosTest.php\` asserting system recovers within expected windows

## Acceptance Criteria
- [x] Chaos harness and at least three scenarios exist
- [x] Documentation for running chaos locally and in CI exists

## Files Created
- \`tests/Resilience/chaos-harness.php\`
- \`tests/Resilience/ResilienceScenariosTest.php\`
" || echo "⚠ Issue 5 may already exist"

print_issue_header "✓" "All Security & Reliability issues attempted"
echo "Done. Review output above for any warnings about existing issues."
