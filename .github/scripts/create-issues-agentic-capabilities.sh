#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Agentic Capabilities epic
# Usage: ./create-issues-agentic-capabilities.sh [REPO]
# Example: ./create-issues-agentic-capabilities.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Agentic Capabilities"

echo "Creating GitHub issues for Agentic Capabilities..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Deliver agentic task automation: driver generation, docs/tests upkeep, audits, refactoring suggestions, guardrails, and operator workflow" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "agentic"
    "automation"
    "documentation"
    "testing"
    "security"
    "refactoring"
    "operations"
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

# Issue 1: Agent Task Registry
print_issue_header "1" "Agent Task Registry"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Establish agent task registry and configuration loader" \
    --milestone "$MILESTONE" \
    --label "feature,agentic,operations,phase-1" \
    --body "## Overview
Introduce the core scaffolding for agentic automation: centralized task registry, configuration loader, and operator workflow definitions stored under the \`.copilot/\` directory.

## Requirements
- Central registry enumerating agent tasks and metadata (REQ-019, REQ-020, REQ-021)
- YAML task definitions under \`.copilot/tasks/*.yaml\`
- CLI or script entry points to invoke tasks with operator approval workflow

## Implementation Checklist

### 1. Directory and configuration setup
- [ ] Create \`.copilot/tasks/\` directory with README explaining agent task format
- [ ] Add \`.copilot/tasks/registry.yaml\` describing task ids, descriptions, required scopes, and safety flags
- [ ] Implement \`tools/agent/TaskRegistry.php\` capable of loading YAML definitions (use Symfony YAML or native parser)

### 2. Operator workflow guardrails
- [ ] Implement \`tools/agent/OperatorConsole.php\` prompting for approval before network or filesystem operations
- [ ] Record approvals in \`storage/agent-audit.log\` with timestamp, task id, operator, and outcome
- [ ] Enforce allow-list for directories each task may touch; deny access otherwise

### 3. Tests & validation
- [ ] Add \`tests/Agent/TaskRegistryTest.php\` verifying registry loading, error cases, and metadata integrity
- [ ] Add \`tests/Agent/OperatorConsoleTest.php\` using mocks to simulate operator approval/denial
- [ ] Run \`composer run phpstan\` to confirm static analysis passes

## Acceptance Criteria
- [x] Task registry loads and exposes metadata for at least four agent tasks
- [x] Operator workflow requires explicit approval before executing privileged operations
- [x] Audit log records all approvals/denials

## Files Created
- \`.copilot/tasks/registry.yaml\`
- \`tools/agent/TaskRegistry.php\`
- \`tools/agent/OperatorConsole.php\`
- \`tests/Agent/TaskRegistryTest.php\`
- \`tests/Agent/OperatorConsoleTest.php\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Automated Driver Generator
print_issue_header "2" "Automated Driver Generator"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Implement automated driver generation task" \
    --milestone "$MILESTONE" \
    --label "feature,agentic,automation,testing,phase-1" \
    --body "## Overview
Build the \`create-new-driver\` agent task capable of consuming RPC specifications and producing scaffolded driver code, tests, and documentation aligned with project conventions.

## Requirements
- Support EVM and non-EVM driver templates
- Generate driver class, configuration stub, tests, and README snippets
- Ensure generated assets pass PHPStan and PHPUnit when specification includes sample responses

## Implementation Checklist

### 1. Task definition
- [ ] Add \`.copilot/tasks/create-new-driver.yaml\` describing inputs (spec URL, driver name, auth tokens)
- [ ] Document safety guardrails (restricted directories, required approvals)

### 2. Code generation engine
- [ ] Implement \`tools/agent/Generator/DriverScaffolder.php\` that
  - [ ] Parses OpenAPI/JSON-RPC specification and maps endpoints to interface methods
  - [ ] Produces files: \`src/Drivers/{{Name}}Driver.php\`, \`tests/Drivers/{{Name}}DriverTest.php\`, docs snippet
  - [ ] Inserts TODO annotations for unsupported operations
- [ ] Reuse serialization helpers from Core Utilities where available

### 3. Post-generation validation
- [ ] Add \`tools/agent/Tasks/CreateDriverTask.php\` orchestrating scaffold, phpstan, and phpunit runs
- [ ] Emit summary report to operator console with next steps

### 4. Tests
- [ ] Add fixture spec \`tests/fixtures/agent/solana-rpc.json\`
- [ ] Write \`tests/Agent/CreateDriverTaskTest.php\` verifying generated artifacts match snapshots (use Mockery or built-in spies)

## Acceptance Criteria
- [x] Agent can generate a sample driver end-to-end using fixture spec
- [x] Generated code includes tests and docs and passes CI checks
- [x] Operator receives readable summary and follow-up instructions

## Files Created
- \`.copilot/tasks/create-new-driver.yaml\`
- \`tools/agent/Generator/DriverScaffolder.php\`
- \`tools/agent/Tasks/CreateDriverTask.php\`
- \`tests/Agent/CreateDriverTaskTest.php\`
- \`tests/fixtures/agent/solana-rpc.json\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Documentation & Test Maintenance Task
print_issue_header "3" "Documentation & Test Maintenance"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Deliver doc/test maintenance automation" \
    --milestone "$MILESTONE" \
    --label "feature,agentic,documentation,testing,phase-1" \
    --body "## Overview
Create the \`update-readme\` and \`test-driver\` agent tasks that keep documentation synchronized and ensure generated drivers have matching tests.

## Requirements
- Auto-update README and driver documentation sections with latest API surface
- Run targeted PHPUnit suites based on changed drivers
- Update changelog or release notes drafts as part of automation when requested

## Implementation Checklist

### 1. Task specifications
- [ ] Add \`.copilot/tasks/update-readme.yaml\` and \`.copilot/tasks/test-driver.yaml\`
- [ ] Document triggers and inputs (changed driver names, doc template paths)

### 2. Implementation
- [ ] Implement \`tools/agent/Tasks/UpdateReadmeTask.php\` leveraging templates from \`docs/templates/\`
- [ ] Implement \`tools/agent/Tasks/TestDriverTask.php\` to run PHPUnit subsets via CLI helper
- [ ] Add diff summarizer that previews documentation changes for operator approval

### 3. Tests & validation
- [ ] Add \`tests/Agent/UpdateReadmeTaskTest.php\` verifying rendered markdown matches expected snapshots
- [ ] Add \`tests/Agent/TestDriverTaskTest.php\` ensuring correct phpunit commands are invoked (mock process runner)
- [ ] Ensure tasks respect guardrails configured in registry

## Acceptance Criteria
- [x] Tasks update documentation and run relevant tests without manual editing
- [x] Operator approves doc diffs before commit suggestions are produced
- [x] Test task reports pass/fail status clearly

## Files Created
- \`.copilot/tasks/update-readme.yaml\`
- \`.copilot/tasks/test-driver.yaml\`
- \`tools/agent/Tasks/UpdateReadmeTask.php\`
- \`tools/agent/Tasks/TestDriverTask.php\`
- \`tests/Agent/UpdateReadmeTaskTest.php\`
- \`tests/Agent/TestDriverTaskTest.php\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Security Audit Automation
print_issue_header "4" "Security Audit Automation"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Implement security audit agent task" \
    --milestone "$MILESTONE" \
    --label "feature,agentic,security,phase-2" \
    --body "## Overview
Deliver the \`security-audit\` agent task that performs static analysis, dependency review, and configuration checks with actionable remediation guidance.

## Requirements
- Integrate with existing security tools (PHPStan, Psalm, Composer audit, custom validators)
- Redact sensitive findings before reporting
- Store audit summary in agent audit log and surface via operator console

## Implementation Checklist

### 1. Task specification & orchestration
- [ ] Add \`.copilot/tasks/security-audit.yaml\` capturing required permissions and report outputs
- [ ] Implement \`tools/agent/Tasks/SecurityAuditTask.php\` orchestrating analysis commands
- [ ] Provide configuration for severity thresholds and failure handling

### 2. Reporting
- [ ] Generate markdown/JSON report under \`storage/agent-reports/security/\`
- [ ] Emit structured events to AuditRecorder (from Security & Reliability milestone)
- [ ] Ensure no secrets or private keys appear in report payloads

### 3. Tests & validation
- [ ] Add \`tests/Agent/SecurityAuditTaskTest.php\` mocking tool invocations and verifying report creation
- [ ] Confirm audit logs capture task metadata and outcomes

## Acceptance Criteria
- [x] Security audit task runs end-to-end and produces sanitized reports
- [x] Operator console highlights critical findings and suggested follow-ups
- [x] Audit entries stored with traceability to task execution

## Files Created
- \`.copilot/tasks/security-audit.yaml\`
- \`tools/agent/Tasks/SecurityAuditTask.php\`
- \`tests/Agent/SecurityAuditTaskTest.php\`
- \`storage/agent-reports/security/README.md\`
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Refactoring & Optimization Suggestions
print_issue_header "5" "Refactoring & Optimization"
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add intelligent refactoring and optimization agent" \
    --milestone "$MILESTONE" \
    --label "feature,agentic,refactoring,phase-2" \
    --body "## Overview
Implement the \`refactor-suggestions\` task that proposes code improvements, dead code cleanup, and performance optimizations while respecting safety guardrails.

## Requirements
- Analyze codebase metrics (cyclomatic complexity, hotspots)
- Generate patch suggestions stored as git patches without applying automatically
- Provide operator review workflow and integration with audit log

## Implementation Checklist

### 1. Task definition and analyzers
- [ ] Add \`.copilot/tasks/refactor-suggestions.yaml\`
- [ ] Implement analyzers in \`tools/agent/Analysis/\` (complexity scanner, unused code detector)

### 2. Suggestion generation
- [ ] Implement \`tools/agent/Tasks/RefactorSuggestionsTask.php\` producing patch files under \`storage/agent-reports/refactor/\`
- [ ] Include summary markdown with rationale and risk scoring

### 3. Operator workflow integration
- [ ] Provide CLI command to apply selected suggestions after approval
- [ ] Log outcomes (applied/skipped) to agent audit log

### 4. Tests
- [ ] Add \`tests/Agent/RefactorSuggestionsTaskTest.php\` verifying patch generation with fixture projects
- [ ] Ensure analyzers respect directory allow-lists and do not traverse forbidden paths

## Acceptance Criteria
- [x] Refactor suggestions generated with supporting rationale and patch files
- [x] Operators can inspect and apply suggestions selectively
- [x] All activity recorded in audit log

## Files Created
- \`.copilot/tasks/refactor-suggestions.yaml\`
- \`tools/agent/Analysis/*\`
- \`tools/agent/Tasks/RefactorSuggestionsTask.php\`
- \`tests/Agent/RefactorSuggestionsTaskTest.php\`
- \`storage/agent-reports/refactor/README.md\`
" || echo "⚠ Issue 5 may already exist"

print_issue_header "✓" "All Agentic Capabilities issues attempted"
echo "Done. Review output above for any warnings about existing issues."
