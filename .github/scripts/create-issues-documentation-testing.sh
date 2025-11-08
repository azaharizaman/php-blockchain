#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Documentation & Testing QA Implementation
# Usage: ./create-issues-documentation-testing.sh [REPO]
# Example: ./create-issues-documentation-testing.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Documentation & Testing QA"

echo "Creating GitHub issues for Documentation & Testing QA..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement documentation generation, test coverage enforcement, static analysis, and QA gating in CI pipeline" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "documentation"
    "testing"
    "qa"
    "ci"
    "automation"
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

# Issue 1: Documentation Scripts
print_issue_header "1" "Documentation Scripts"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Maintain documentation generator and checker scripts" \
    --milestone "$MILESTONE" \
    --label "documentation,automation,phase-1" \
    --body "## Overview
Ensure documentation generation and validation scripts are maintained and integrated into composer workflow.

## Requirements
- **REQ-001**: All drivers have docs under docs/drivers/*.md
- Generator and checker scripts must exist
- Composer scripts available for easy access

## Implementation Checklist

### 1. Review existing scripts
- [ ] Verify \`scripts/generate-driver-docs.php\` exists and is functional
- [ ] Verify \`scripts/check-driver-docs.php\` exists and is functional
- [ ] Update scripts if needed for current driver structure

### 2. Composer script integration
- [ ] Add \`generate-docs\` script to composer.json: \`php scripts/generate-driver-docs.php\`
- [ ] Add \`check-docs\` script to composer.json: \`php scripts/check-driver-docs.php\`
- [ ] Verify scripts run successfully: \`composer run generate-docs\`

### 3. Documentation templates
- [ ] Create \`docs/templates/driver-template.md\` if not exists
- [ ] Define standard sections: Overview, Installation, Configuration, Usage, Methods, Examples
- [ ] Ensure generator uses template for consistency

### 4. Generator implementation
- [ ] Scan \`src/Drivers/\` for driver classes
- [ ] Extract methods from BlockchainDriverInterface implementation
- [ ] Generate method signatures and parameter docs
- [ ] Include usage examples from tests if available
- [ ] Write to \`docs/drivers/{DriverName}.md\`

### 5. Checker implementation
- [ ] Scan for all drivers in \`src/Drivers/\`
- [ ] Verify corresponding doc exists in \`docs/drivers/\`
- [ ] Check doc has required sections
- [ ] Verify method signatures match driver class
- [ ] Exit with error code if checks fail

### 6. Tests
- [ ] Create \`tests/Scripts/GenerateDriverDocsTest.php\`
- [ ] Create \`tests/Scripts/CheckDriverDocsTest.php\`
- [ ] Test with fixture driver classes
- [ ] Verify generated docs match expected format
- [ ] Run \`composer run phpstan\`

### 7. Documentation
- [ ] Add guide to \`docs/contributing.md\` explaining doc generation
- [ ] Document how to update templates
- [ ] Explain when to run generator vs checker

## Acceptance Criteria
- [x] php scripts/check-driver-docs.php returns success
- [x] Composer scripts work: composer run generate-docs, composer run check-docs
- [x] Tests pass for doc generation workflow

## Files Created/Updated
- \`scripts/generate-driver-docs.php\` (verify/update)
- \`scripts/check-driver-docs.php\` (verify/update)
- \`docs/templates/driver-template.md\`
- \`tests/Scripts/GenerateDriverDocsTest.php\`
- \`tests/Scripts/CheckDriverDocsTest.php\`
- Updated \`composer.json\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: CI Quality Gates
print_issue_header "2" "CI Quality Gates"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Add PHPStan and PHPCS steps to CI workflow" \
    --milestone "$MILESTONE" \
    --label "qa,ci,automation,phase-1" \
    --body "## Overview
Integrate static analysis and code style checks into CI pipeline to enforce quality standards on all PRs.

## Requirements
- **REQ-003**: Run PHPStan and PHPCS in CI
- Fail PRs that reduce code quality
- Report results clearly

## Implementation Checklist

### 1. Update CI workflow file
- [ ] Open \`.github/workflows/agent-tasks.yml\` for editing
- [ ] Add job \`static-analysis\` with PHP setup
- [ ] Run \`composer run phpstan\` in CI
- [ ] Run \`composer run lint\` (PHPCS) in CI

### 2. PHPStan configuration
- [ ] Verify \`phpstan.neon\` exists with appropriate level (7+)
- [ ] Configure paths to analyze: src/, tests/
- [ ] Exclude vendor and generated files
- [ ] Ensure composer script exists: \`phpstan\` -> \`phpstan analyze\`

### 3. PHPCS configuration
- [ ] Verify \`phpcs.xml\` or \`.phpcs.xml.dist\` exists
- [ ] Configure PSR-12 standard
- [ ] Configure paths to check: src/, tests/
- [ ] Exclude vendor and cache directories
- [ ] Ensure composer script exists: \`lint\` -> \`phpcs\`

### 4. CI job structure
- [ ] Set job to run on: pull_request, push to main
- [ ] Use appropriate PHP version matrix (8.2+)
- [ ] Cache composer dependencies
- [ ] Run composer install before analysis
- [ ] Report failures with clear error messages

### 5. Result reporting
- [ ] Annotate PR with PHPStan errors using GitHub Actions
- [ ] Annotate PR with PHPCS violations
- [ ] Add status badge to README if not present
- [ ] Ensure failures block PR merge

### 6. Local development support
- [ ] Document how to run checks locally
- [ ] Add pre-commit hook example (optional)
- [ ] Provide fix commands where applicable (phpcbf)

### 7. Tests
- [ ] Verify CI workflow runs successfully on test branch
- [ ] Intentionally introduce error to test failure detection
- [ ] Verify PR annotations appear correctly

## Acceptance Criteria
- [x] CI runs phpstan and phpcs on every PR
- [x] Failures are clearly reported with annotations
- [x] Local development commands documented

## Files Created/Updated
- Updated \`.github/workflows/agent-tasks.yml\`
- \`phpstan.neon\` (verify/update)
- \`phpcs.xml\` or \`.phpcs.xml.dist\` (verify/update)
- Updated \`composer.json\` scripts section
- Updated \`docs/contributing.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Test Coverage Enforcement
print_issue_header "3" "Test Coverage Enforcement"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Configure PHPUnit coverage requirements and reporting" \
    --milestone "$MILESTONE" \
    --label "testing,qa,ci,phase-1" \
    --body "## Overview
Enforce test coverage thresholds and generate coverage reports in CI to maintain high code quality.

## Requirements
- **REQ-002**: Enforce PHPUnit coverage thresholds
- Upload coverage reports in CI
- Configure in phpunit.xml

## Implementation Checklist

### 1. PHPUnit configuration
- [ ] Open \`phpunit.xml\` for editing
- [ ] Add coverage section with source paths
- [ ] Configure coverage formats: html, clover, text
- [ ] Set coverage output directory: \`coverage/\`
- [ ] Add to .gitignore: \`/coverage/\`

### 2. Coverage thresholds
- [ ] Add coverage requirement to phpunit.xml or separate config
- [ ] Set line coverage minimum (e.g., 80%)
- [ ] Set method coverage minimum (e.g., 85%)
- [ ] Configure to fail build if below threshold

### 3. Composer script setup
- [ ] Add \`test\` script: \`phpunit\`
- [ ] Add \`test:coverage\` script: \`phpunit --coverage-html coverage\`
- [ ] Add \`test:coverage-text\` script: \`phpunit --coverage-text\`
- [ ] Verify scripts work locally

### 4. CI integration
- [ ] Add coverage job to \`.github/workflows/agent-tasks.yml\`
- [ ] Install coverage driver (phpdbg or xdebug)
- [ ] Run \`composer run test:coverage\`
- [ ] Generate clover report for upload

### 5. Coverage reporting
- [ ] Upload coverage to Codecov or Coveralls
- [ ] Add coverage badge to README.md
- [ ] Configure coverage service with repository
- [ ] Ensure coverage trends are tracked

### 6. Coverage exclusions
- [ ] Exclude test files from coverage
- [ ] Exclude vendor directory
- [ ] Mark intentionally untested code with annotations
- [ ] Document exclusion policy

### 7. Tests validation
- [ ] Run coverage locally: \`composer run test:coverage\`
- [ ] Verify HTML report generation
- [ ] Verify threshold enforcement works
- [ ] Test CI coverage upload

### 8. Documentation
- [ ] Add coverage guide to \`docs/testing.md\`
- [ ] Explain how to view coverage reports
- [ ] Document threshold policy
- [ ] Provide troubleshooting tips

## Acceptance Criteria
- [x] composer test with coverage generates report
- [x] Build fails if coverage below threshold
- [x] Coverage reports uploaded to service

## Files Created/Updated
- Updated \`phpunit.xml\`
- Updated \`composer.json\` scripts
- Updated \`.github/workflows/agent-tasks.yml\`
- Updated \`.gitignore\`
- \`docs/testing.md\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Documentation Publishing
print_issue_header "4" "Documentation Publishing"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Add docs publishing job with operator approval" \
    --milestone "$MILESTONE" \
    --label "documentation,ci,phase-2" \
    --body "## Overview
Implement automated documentation publishing to gh-pages or docs site, gated by manual operator approval.

## Requirements
- **REQ-001**: Publish docs/ to configured site
- **TASK-004**: Gate by operator approval
- Upload docs artifacts

## Implementation Checklist

### 1. Create publish workflow
- [ ] Create \`.github/workflows/publish-docs.yml\`
- [ ] Trigger on workflow_dispatch (manual) or tag push
- [ ] Add environment protection with required reviewers
- [ ] Use appropriate PHP version for doc generation

### 2. Documentation build
- [ ] Run \`composer run generate-docs\` to ensure latest
- [ ] Build any additional doc formats (PDF, epub) if needed
- [ ] Validate all internal links are working
- [ ] Generate API documentation if not already done

### 3. gh-pages deployment
- [ ] Use peaceiris/actions-gh-pages or similar action
- [ ] Configure to push to gh-pages branch
- [ ] Set publish_dir to docs/
- [ ] Configure CNAME if custom domain

### 4. Operator approval gate
- [ ] Configure GitHub environment 'documentation'
- [ ] Add required reviewers to environment protection
- [ ] Add approval timeout (24-48 hours)
- [ ] Document approval process

### 5. Artifact upload
- [ ] Upload docs as workflow artifacts
- [ ] Retention: 90 days
- [ ] Include API docs, coverage reports, generated markdown
- [ ] Enable download from workflow runs

### 6. Notifications
- [ ] Send notification on successful publish
- [ ] Send notification on approval request
- [ ] Include links to published docs
- [ ] Log publish activity to audit log

### 7. Tests
- [ ] Test workflow with workflow_dispatch
- [ ] Verify approval gate works
- [ ] Verify docs publish correctly to gh-pages
- [ ] Check all links work on published site

### 8. Documentation
- [ ] Add publish guide to \`docs/contributing.md\`
- [ ] Document who can approve publishes
- [ ] Explain when docs should be published
- [ ] Provide rollback procedure

## Acceptance Criteria
- [x] Manual approval required for publishing
- [x] Job uploads docs artifacts successfully
- [x] Published docs accessible and functional

## Files Created
- \`.github/workflows/publish-docs.yml\`
- Updated \`docs/contributing.md\`
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Mutation Testing
print_issue_header "5" "Mutation Testing"
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add mutation testing for critical utilities" \
    --milestone "$MILESTONE" \
    --label "testing,qa,phase-2" \
    --body "## Overview
Add optional mutation testing to validate test suite effectiveness for critical utility classes.

## Requirements
- Optional mutation testing
- CI optional job
- No mandatory pass required

## Implementation Checklist

### 1. Install Infection framework
- [ ] Add infection/infection as dev dependency
- [ ] Run \`composer require --dev infection/infection\`
- [ ] Verify installation: \`vendor/bin/infection --version\`

### 2. Infection configuration
- [ ] Create \`infection.json.dist\` configuration file
- [ ] Configure source directories to mutate: src/Utils/, src/Security/
- [ ] Exclude test files from mutation
- [ ] Set minimum MSI (Mutation Score Indicator) threshold
- [ ] Configure timeout and memory limits

### 3. Mutation scope
- [ ] Focus on critical utilities: AddressValidator, Serializer
- [ ] Include security components: SecretProvider, RedactingLogger
- [ ] Include core operations: TransactionBuilder, TransactionQueue
- [ ] Exclude drivers (too large scope initially)

### 4. Composer script
- [ ] Add \`mutation\` script: \`infection\`
- [ ] Add \`mutation:coverage\` script with coverage options
- [ ] Test script locally: \`composer run mutation\`

### 5. CI integration
- [ ] Add mutation-testing job to CI as optional
- [ ] Run only on main branch or manual trigger
- [ ] Allow job to fail without blocking
- [ ] Upload mutation report as artifact

### 6. Baseline establishment
- [ ] Run initial mutation testing
- [ ] Document baseline MSI score
- [ ] Set realistic threshold (70-80% initially)
- [ ] Plan incremental improvements

### 7. Result interpretation
- [ ] Document how to read mutation reports
- [ ] Explain common mutation operators
- [ ] Provide guidance on improving scores
- [ ] Add examples of good vs bad test coverage

### 8. Documentation
- [ ] Add mutation testing guide to \`docs/testing.md\`
- [ ] Explain when mutation testing is useful
- [ ] Document how to run locally
- [ ] Provide improvement recommendations

## Acceptance Criteria
- [x] Mutation test run completes successfully
- [x] CI optional job runs without blocking
- [x] Documentation explains mutation testing

## Files Created
- \`infection.json.dist\`
- Updated \`composer.json\`
- Updated \`.github/workflows/agent-tasks.yml\`
- Updated \`docs/testing.md\`
" || echo "⚠ Issue 5 may already exist"

print_issue_header "✓" "All Documentation & Testing QA issues attempted"
echo "Done. Review output above for any warnings about existing issues."
