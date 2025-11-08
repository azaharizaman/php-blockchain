#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Core Operations Implementation
# Usage: ./create-issues-core-operations.sh [REPO]
# Example: ./create-issues-core-operations.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Core Operations"

echo "Creating GitHub issues for Core Operations Implementation..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement wallet abstraction, transaction workflows, queueing, batching, idempotency, and telemetry for the PHP Blockchain SDK" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "operations"
    "transactions"
    "wallets"
    "batching"
    "idempotency"
    "telemetry"
    "testing"
    "security"
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

# Issue 1: WalletInterface
print_issue_header() {
    echo "Creating Issue $1: $2..."
}

print_issue_header "1" "WalletInterface"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Define WalletInterface contract" \
    --milestone "$MILESTONE" \
    --label "feature,operations,wallets,security,phase-1" \
    --body "## Overview
Introduce a wallet abstraction that encapsulates key management and signing so drivers and higher level workflows can work with HSMs or software keys through a single contract.

## Requirements
- **REQ-002**: Provide wallet abstraction (WalletInterface) and HSM-friendly key provider adapter
- **SEC-001**: Private keys must never be logged; key material handled via secure interfaces only
- **CON-001**: All artifacts must follow PSR-12 and pass PHPStan level 7

## Implementation Checklist

### 1. Create Wallet namespace
- [ ] Create directory \`src/Wallet/\` if it does not already exist
- [ ] Create file \`src/Wallet/WalletInterface.php\`
- [ ] Add namespace declaration \`namespace Blockchain\\Wallet;\`
- [ ] Add \`declare(strict_types=1);\` at the top of the file

### 2. Declare interface and methods
- [ ] Declare interface \`WalletInterface\`
- [ ] Add \`public function getPublicKey(): string;\`
- [ ] Add \`public function sign(string \$payload): string;\` and document \`\$payload\` as raw bytes coming from transaction builders
- [ ] Add \`public function getAddress(): string;\`
- [ ] Add \`@throws CryptoException\` (or appropriate domain exception) annotations for \`sign()\`

### 3. Document security and expectations
- [ ] Add interface-level docblock describing responsibilities and HSM support expectations
- [ ] Document that implementations must never leak or log private key material (SEC-001)
- [ ] Describe expected return formats for \`getPublicKey()\` (hex/base58 as applicable) and \`getAddress()\`
- [ ] Reference that signing implementations should work with queue and batcher workflows

### 4. Validation
- [ ] Ensure interface is registered in composer autoload (PSR-4) if new namespace is introduced
- [ ] Run \`composer run phpstan\` and confirm no errors
- [ ] Run \`composer run lint\` (or PHPCS equivalent) to confirm PSR-12 compliance

## Acceptance Criteria
- [x] \`WalletInterface\` file exists under \`src/Wallet/\`
- [x] Methods \`getPublicKey()\`, \`sign()\`, and \`getAddress()\` are defined with strict typing and docblocks
- [x] Security note preventing key leakage is captured in documentation
- [x] Static analysis and coding standards pass without regressions

## Files Created
- \`src/Wallet/WalletInterface.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-001)
- PRD: \`docs/prd/03-CORE-OPERATIONS-EPIC.md\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: TransactionBuilder
print_issue_header "2" "TransactionBuilder"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Implement TransactionBuilder workflow primitives" \
    --milestone "$MILESTONE" \
    --label "feature,operations,transactions,testing,phase-1" \
    --body "## Overview
Create the transaction workflow helper responsible for assembling driver-ready payloads, normalizing metadata, and coordinating signing with the wallet abstraction.

## Requirements
- **REQ-001**: Implement transaction orchestration helpers for preparing, signing, and broadcasting transactions
- **REQ-002**: Integrate with \`WalletInterface\` for signing workflows
- **CON-001**: All network interactions must be mockable and covered by unit tests with MockHandler

## Implementation Checklist

### 1. Create TransactionBuilder class
- [ ] Create file \`src/Operations/TransactionBuilder.php\`
- [ ] Add namespace \`namespace Blockchain\\Operations;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Import \`Blockchain\\Contracts\\BlockchainDriverInterface\` and \`Blockchain\\Wallet\\WalletInterface\`

### 2. Configure dependencies and state
- [ ] Inject \`BlockchainDriverInterface\` and \`WalletInterface\` via constructor
- [ ] Store optional defaults (fee payer, memo, gas options) via immutable configuration methods (e.g., \`withFeePayer()\`)
- [ ] Ensure builder is immutable or clearly documents mutation semantics
- [ ] Prevent direct exposure of private key material per SEC-001

### 3. Provide build methods
- [ ] Implement \`public function buildTransfer(string \$to, float \$amount, array \$options = []): array\`
- [ ] Implement \`public function buildContractCall(string \$method, array \$params, array \$options = []): array\`
- [ ] Normalize payloads into associative arrays with keys \`driver\`, \`payload\`, \`metadata\`, and \`signatures\`
- [ ] Attach wallet address and public key metadata to the payload
- [ ] Ensure optional memo/gas/nonce data is propagated into \`metadata\`
- [ ] Add hook method to attach idempotency tokens (placeholder to be wired in TASK-005)

### 4. Signing integration
- [ ] Provide \`private function signPayload(array \$payload): string\` that delegates to \`WalletInterface::sign()\`
- [ ] Attach signature outputs to \`signatures\` element in the build result
- [ ] Make signing behavior optional (allow \`options['skipSign']\` for pre-signed operations)

### 5. Test scaffolding
- [ ] Create \`tests/Operations/TransactionBuilderTest.php\`
- [ ] Add Solana stub driver returning expected instruction format (e.g., array with program id, accounts, data)
- [ ] Add Ethereum stub driver returning JSON-RPC call array
- [ ] Mock \`WalletInterface\` to verify signing integration without exposing keys
- [ ] Use Guzzle MockHandler or simple stubs to ensure no live network calls occur
- [ ] Assert builder returns expected payload shapes for both Solana and Ethereum scenarios defined in the plan

### 6. Validation
- [ ] Run \`vendor/bin/phpunit tests/Operations/TransactionBuilderTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure docblocks reference \`@param array<string,mixed>\` and \`@return array<string,mixed>\`

## Acceptance Criteria
- [x] TransactionBuilder produces deterministic payload arrays for Solana and Ethereum stubs
- [x] Signing occurs via \`WalletInterface\` with no key leakage
- [x] Tests cover transfer and contract call flows using mocks
- [x] Static analysis and unit tests pass

## Files Created
- \`src/Operations/TransactionBuilder.php\`
- \`tests/Operations/TransactionBuilderTest.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-002)
- Depends on: TASK-001 (WalletInterface)
" || echo "⚠ Issue 2 may already exist"

# Issue 3: TransactionQueue
print_issue_header "3" "TransactionQueue"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement TransactionQueue with retry and backoff" \
    --milestone "$MILESTONE" \
    --label "feature,operations,transactions,testing,phase-1" \
    --body "## Overview
Deliver an in-memory transaction queue that supports enqueue, dequeue, retry scheduling, and exponential backoff so high-volume clients can orchestrate broadcasts deterministically.

## Requirements
- **REQ-003**: Support batching, retries, idempotency tokens, and transaction queuing for high-throughput clients
- **CON-001**: Queue interactions must be fully testable with mocked timers or clock helpers
- **SEC-001**: Ensure sensitive payloads are not logged when retries occur

## Implementation Checklist

### 1. Create TransactionQueue class
- [ ] Create file \`src/Operations/TransactionQueue.php\`
- [ ] Add namespace \`namespace Blockchain\\Operations;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Use \`SplQueue\` or custom data structure to manage pending jobs

### 2. Define queue item structure
- [ ] Create value object \`TransactionJob\` (inline class or associative array with typed accessors) with fields: id, payload, attempts, nextAvailableAt, metadata
- [ ] Ensure idempotency token slot is available but optional until TASK-005 wires it in
- [ ] Document that payloads originate from \`TransactionBuilder\`

### 3. Queue operations
- [ ] Implement \`enqueue(TransactionJob \$job): void\`
- [ ] Implement \`dequeue(): ?TransactionJob\` that respects \`nextAvailableAt\`
- [ ] Implement \`recordFailure(TransactionJob \$job, Throwable \$reason): void\` to update attempts and schedule backoff
- [ ] Implement \`acknowledge(TransactionJob \$job): void\` to mark job complete and remove from queue
- [ ] Support configurable max attempts and base backoff via constructor options

### 4. Backoff policy
- [ ] Implement exponential backoff (e.g., baseSeconds * 2^attempt)
- [ ] Allow jitter function injection for variability (default none)
- [ ] Ensure \`nextAvailableAt\` is calculated using clock abstraction (inject callable returning current time)
- [ ] Guard against overflows and cap maximum delay to configurable limit

### 5. Observability hooks
- [ ] Emit tracer callbacks (placeholder to integrate with OperationTracer in TASK-006)
- [ ] Provide PSR-3 compatible logger injection for debug-level events without leaking payload content

### 6. Testing
- [ ] Create \`tests/Operations/TransactionQueueTest.php\`
- [ ] Use fake clock to control timing and assert dequeue respects \`nextAvailableAt\`
- [ ] Test retry scheduling increments attempts and delays according to configuration
- [ ] Test queue handles empty state and max attempt exhaustion
- [ ] Verify no sensitive data is logged during retries (mock logger assertions)

### 7. Validation
- [ ] Run \`vendor/bin/phpunit tests/Operations/TransactionQueueTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure docblocks use generic array typing where necessary

## Acceptance Criteria
- [x] In-memory queue manages pending transactions with retries and backoff
- [x] Queue operations are deterministic under fake clock testing
- [x] Security requirements honored by avoiding key exposure in logs
- [x] Tests and static analysis pass

## Files Created
- \`src/Operations/TransactionQueue.php\`
- \`tests/Operations/TransactionQueueTest.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-003)
- Depends on: TASK-002 (TransactionBuilder)
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Batcher
print_issue_header "4" "Batcher"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Implement Batcher for grouped transaction submission" \
    --milestone "$MILESTONE" \
    --label "feature,operations,batching,transactions,testing,phase-2" \
    --body "## Overview
Add a batching component that groups compatible transactions and submits them through drivers supporting batched RPC calls while falling back to single dispatch when necessary.

## Requirements
- **REQ-003**: Support batching and retries for high-throughput clients
- **DEP-001**: Continue to use GuzzleHTTP abstractions so calls remain mockable
- **CON-001**: Provide comprehensive unit tests simulating driver behavior

## Implementation Checklist

### 1. Create Batcher class
- [ ] Create file \`src/Operations/Batcher.php\`
- [ ] Add namespace \`namespace Blockchain\\Operations;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Accept dependencies: \`BlockchainDriverInterface\`, \`TransactionQueue\`, optional batch size config, telemetry hooks

### 2. Grouping logic
- [ ] Implement \`collectReadyJobs(int \$max = null): array\` to pull eligible jobs from queue
- [ ] Group jobs by driver capability or network requirements
- [ ] Allow custom grouping strategy injection for future persistence adapters
- [ ] Ensure idempotency tokens (TASK-005) are preserved per job

### 3. Dispatch flow
- [ ] Implement \`dispatch(): BatchResult\` that processes available batches
- [ ] For drivers supporting batch RPC, make a single request carrying multiple payloads
- [ ] For drivers lacking batch support, iterate sequentially but return aggregated result
- [ ] Handle partial failures by re-enqueueing failed jobs with backoff
- [ ] Ensure exceptions do not expose sensitive payload data (mask addresses when necessary)

### 4. Telemetry integration
- [ ] Emit OperationTracer events for batch start, per-job success, and per-job failure
- [ ] Collect metrics such as batch size, duration, and success rate for future observability

### 5. Testing
- [ ] Create \`tests/Operations/BatcherTest.php\`
- [ ] Implement mock driver with \`supportsBatching(): bool\` toggle and assertion helpers
- [ ] Use queue fixture populated with TransactionBuilder payloads
- [ ] Test batch submission success path and partial failure path with retries scheduled
- [ ] Assert telemetry hooks receive expected calls via mocks

### 6. Validation
- [ ] Run \`vendor/bin/phpunit tests/Operations/BatcherTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure PSR-12 compliance and document driver capability checks

## Acceptance Criteria
- [x] Batcher groups ready jobs and dispatches them respecting driver capabilities
- [x] Failed jobs are retried via TransactionQueue backoff rules
- [x] Telemetry hooks fire for batch lifecycle events
- [x] Tests and static analysis pass

## Files Created
- \`src/Operations/Batcher.php\`
- \`tests/Operations/BatcherTest.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-004)
- Depends on: TASK-003 (TransactionQueue)
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Idempotency support
print_issue_header "5" "Idempotency Support"
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add idempotency tokens and storage adapter" \
    --milestone "$MILESTONE" \
    --label "feature,operations,idempotency,security,testing,phase-2" \
    --body "## Overview
Introduce idempotency token generation and optional persistence adapters so duplicate transaction broadcasts can be prevented across retries and batch dispatches.

## Requirements
- **REQ-003**: Ensure idempotency tokens are available for queue and batch flows
- **SEC-001**: Handle tokens securely without exposing transaction payloads
- **DEP-002**: Allow integration with storage adapters (Redis/DB) without hard dependency

## Implementation Checklist

### 1. Create Idempotency utility
- [ ] Create file \`src/Operations/Idempotency.php\`
- [ ] Add namespace \`namespace Blockchain\\Operations;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Implement \`public static function generate(?string \$hint = null): string\` using cryptographically secure random bytes
- [ ] Support deterministic token derivation when hint is provided (hash of wallet address + payload fingerprint)

### 2. Define storage adapter contract
- [ ] Create interface \`src/Storage/IdempotencyStoreInterface.php\` with methods \`record(string \$token, array \$context): void\` and \`has(string \$token): bool\`
- [ ] Document that implementations may wrap Redis, SQL, or in-memory stores
- [ ] Provide basic in-memory implementation under \`src/Storage/InMemoryIdempotencyStore.php\`

### 3. Wire into queue and builder
- [ ] Update \`TransactionBuilder\` to attach generated idempotency token to payload metadata when none is provided
- [ ] Update \`TransactionQueue\` to persist/retrieve idempotency tokens via adapter when configured
- [ ] Ensure queue prevents enqueueing duplicates by consulting store
- [ ] Mask token values in logs (store only hashed form if logging is required)

### 4. Testing
- [ ] Create \`tests/Operations/IdempotencyTest.php\`
- [ ] Cover token generation randomness and hint determinism
- [ ] Cover in-memory store behavior (record, has, duplicate prevention)
- [ ] Extend existing queue tests to assert duplicate payloads are skipped when token matches
- [ ] Use mocks to confirm adapters are optional and injectable

### 5. Validation
- [ ] Run \`vendor/bin/phpunit tests/Operations/IdempotencyTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure storage namespace adheres to PSR-4 autoload configuration

## Acceptance Criteria
- [x] Idempotency tokens generated securely and deterministically when hints are provided
- [x] Queue and builder integrate tokens to prevent duplicate broadcasts
- [x] Optional persistence adapter contract and in-memory implementation exist
- [x] Tests and static analysis pass

## Files Created
- \`src/Operations/Idempotency.php\`
- \`src/Storage/IdempotencyStoreInterface.php\`
- \`src/Storage/InMemoryIdempotencyStore.php\`
- \`tests/Operations/IdempotencyTest.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-005)
- Depends on: TASK-002, TASK-003
" || echo "⚠ Issue 5 may already exist"

# Issue 6: OperationTracer
print_issue_header "6" "OperationTracer"
gh issue create \
    --repo "$REPO" \
    --title "TASK-006: Expose telemetry hooks via OperationTracer" \
    --milestone "$MILESTONE" \
    --label "feature,operations,telemetry,testing,phase-2" \
    --body "## Overview
Expose telemetry hooks that instrument the transaction lifecycle so agents and monitoring stacks can observe queue, batch, and broadcast events without coupling to specific drivers.

## Requirements
- **REQ-001**: Provide monitoring hooks across transaction lifecycle
- **GOAL-002**: Extend batching, idempotency, and queue flows with tracer callbacks
- **CON-001**: Hooks must be no-op by default and fully testable

## Implementation Checklist

### 1. Create OperationTracer class
- [ ] Create file \`src/Telemetry/OperationTracer.php\`
- [ ] Add namespace \`namespace Blockchain\\Telemetry;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Define hook methods such as \`onEnqueued(array \$job): void\`, \`onBatchDispatched(array \$batch): void\`, and \`onBroadcastResult(array \$result): void\`
- [ ] Provide default no-op implementations so instrumentation is optional

### 2. Provide interface/trait for custom tracers
- [ ] Consider defining \`OperationTracerInterface\` to allow alternative implementations
- [ ] Document expected payload structure per hook (job metadata, timing, outcomes)

### 3. Integrate tracer into operations workflow
- [ ] Update \`TransactionBuilder\`, \`TransactionQueue\`, and \`Batcher\` to accept tracer dependency (constructor or setter)
- [ ] Emit tracer events at critical lifecycle points (enqueue, dequeue, dispatch, success, failure)
- [ ] Ensure tracer receives copies or sanitized views of payloads to avoid leaking secrets

### 4. Testing
- [ ] Create \`tests/Telemetry/OperationTracerTest.php\` verifying default tracer is no-op
- [ ] Add integration-style test (e.g., \`tests/Operations/OperationLifecycleTest.php\`) using a spy tracer to assert hooks fire in correct order
- [ ] Use MockHandler or fake drivers to simulate dispatch success and failure

### 5. Documentation & validation
- [ ] Update relevant docblocks to reference tracer usage patterns
- [ ] Run \`vendor/bin/phpunit tests/Telemetry/OperationTracerTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure PSR-12 compliance and avoid circular dependencies when injecting tracer

## Acceptance Criteria
- [x] OperationTracer provides lifecycle hooks with safe defaults
- [x] Builder, queue, and batcher emit tracer events without leaking secrets
- [x] Tests confirm hook ordering and no-op behavior
- [x] Static analysis and unit tests pass

## Files Created
- \`src/Telemetry/OperationTracer.php\`
- \`tests/Telemetry/OperationTracerTest.php\`
- \`tests/Operations/OperationLifecycleTest.php\`

## Related
- Plan: \`plan/feature-core-operations-1.md\` (TASK-006)
- Depends on: TASK-002, TASK-003, TASK-004, TASK-005
" || echo "⚠ Issue 6 may already exist"


print_issue_header "✓" "All Core Operations issues attempted"
echo "Done. Review output above for any warnings about existing issues."
