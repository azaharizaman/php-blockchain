#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Deployment & Distribution Implementation
# Usage: ./create-issues-deployment-distribution.sh [REPO]
# Example: ./create-issues-deployment-distribution.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Deployment & Distribution"

echo "Creating GitHub issues for Deployment & Distribution..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement packaging, versioning, release automation, PHAR builds, Docker images, and Packagist publishing" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "deployment"
    "distribution"
    "releases"
    "packaging"
    "ci"
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

# Issue 1: Composer Package Metadata
print_issue_header "1" "Composer Package Metadata"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Ensure composer.json has accurate package metadata" \
    --milestone "$MILESTONE" \
    --label "deployment,packaging,phase-1" \
    --body "## Overview
Verify and update composer.json with complete and accurate metadata for Packagist publishing.

## Requirements
- **REQ-001**: Publish to Packagist
- **TASK-001**: Ensure metadata is accurate
- composer validate must pass

## Implementation Checklist

### 1. Review composer.json metadata
- [ ] Verify \`name\` field follows vendor/package format
- [ ] Verify \`description\` is clear and concise
- [ ] Verify \`license\` field (MIT or appropriate)
- [ ] Verify \`authors\` array with name and email

### 2. Package details
- [ ] Add \`type\`: \"library\"
- [ ] Add \`keywords\`: [\"blockchain\", \"php\", \"ethereum\", \"solana\", \"web3\"]
- [ ] Add \`homepage\` URL if available
- [ ] Add \`readme\` field pointing to README.md

### 3. Repository information
- [ ] Add \`support\` section with issues, source, docs URLs
- [ ] Verify \`require\` section has accurate version constraints
- [ ] Verify \`require-dev\` section is complete
- [ ] Add \`suggest\` section for optional dependencies

### 4. Autoloading configuration
- [ ] Verify PSR-4 autoload paths are correct
- [ ] Verify autoload-dev includes test namespaces
- [ ] Add \`files\` autoload if needed for helpers
- [ ] Exclude unnecessary files from distribution

### 5. Scripts and configuration
- [ ] Review \`scripts\` section for completeness
- [ ] Add \`config\` section with sort-packages, optimize-autoloader
- [ ] Configure \`platform\` requirements if needed
- [ ] Add \`archive\` exclusions for smaller packages

### 6. Version and stability
- [ ] Set appropriate \`minimum-stability\`: \"stable\" or \"dev\"
- [ ] Set \`prefer-stable\`: true
- [ ] Review branch-alias if using dev-main
- [ ] Plan semantic versioning strategy

### 7. Validation
- [ ] Run \`composer validate\` and fix any warnings
- [ ] Run \`composer validate --strict\`
- [ ] Run \`composer normalize\` if using composer-normalize
- [ ] Ensure JSON is properly formatted

### 8. Documentation
- [ ] Update README.md with installation instructions
- [ ] Document minimum PHP version requirements
- [ ] Add Packagist badge to README
- [ ] Explain version compatibility

## Acceptance Criteria
- [x] composer validate returns OK
- [x] All required metadata fields populated
- [x] Package ready for Packagist submission

## Files Updated
- \`composer.json\`
- \`README.md\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: Release Automation Workflow
print_issue_header "2" "Release Automation Workflow"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Add GitHub Actions release workflow" \
    --milestone "$MILESTONE" \
    --label "deployment,releases,ci,phase-1" \
    --body "## Overview
Implement automated release workflow for tagging versions and publishing releases to GitHub and Packagist.

## Requirements
- Tag and publish releases using GitHub Actions
- Draft release creation via workflow dispatch
- Token-based authentication

## Implementation Checklist

### 1. Create release workflow file
- [ ] Create \`.github/workflows/release.yml\`
- [ ] Trigger on: workflow_dispatch, push to tags (v*)
- [ ] Add manual version input for workflow_dispatch
- [ ] Set up PHP environment (8.2+)

### 2. Version management
- [ ] Accept version parameter in workflow_dispatch
- [ ] Validate version follows semver format
- [ ] Check version doesn't already exist
- [ ] Update version in relevant files if needed

### 3. Pre-release checks
- [ ] Run composer validate
- [ ] Run test suite: composer test
- [ ] Run static analysis: composer run phpstan
- [ ] Generate documentation: composer run generate-docs

### 4. Build artifacts
- [ ] Install production dependencies only
- [ ] Create release archive with composer archive
- [ ] Calculate checksums for artifacts
- [ ] Generate CHANGELOG excerpt for this version

### 5. Create GitHub release
- [ ] Use softprops/action-gh-release or similar
- [ ] Create draft release by default
- [ ] Set release title and tag
- [ ] Use CHANGELOG excerpt as body
- [ ] Upload release artifacts
- [ ] Add checksums file

### 6. Packagist notification
- [ ] Packagist auto-detects new tags typically
- [ ] Document manual webhook setup if needed
- [ ] Add Packagist token to secrets if auto-publish desired
- [ ] Test webhook delivery

### 7. Post-release actions
- [ ] Create post-release PR if version bumps needed
- [ ] Notify team via configured channels
- [ ] Update documentation site if automated
- [ ] Archive release artifacts

### 8. Documentation
- [ ] Add release guide to \`docs/releasing.md\`
- [ ] Document release workflow steps
- [ ] Explain versioning strategy
- [ ] Provide troubleshooting tips

## Acceptance Criteria
- [x] Draft release can be created via workflow
- [x] Release includes artifacts and checksums
- [x] Packagist receives release notification

## Files Created
- \`.github/workflows/release.yml\`
- \`docs/releasing.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: PHAR Build Script
print_issue_header "3" "PHAR Build Script"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Add PHAR build script and CLI entry point" \
    --milestone "$MILESTONE" \
    --label "deployment,packaging,phase-2" \
    --body "## Overview
Provide optional PHAR artifact for standalone distribution with minimal CLI entry point.

## Requirements
- Build PHAR artifact
- Include minimal CLI: bin/blockchain
- Dry-run mode support

## Implementation Checklist

### 1. Create build script
- [ ] Create \`build/phar.sh\` bash script
- [ ] Make executable: chmod +x
- [ ] Accept --output flag for destination path
- [ ] Accept --no-compress flag for debugging

### 2. PHAR builder implementation
- [ ] Create \`build/phar-builder.php\` PHP script
- [ ] Use Phar class to create archive
- [ ] Include src/, vendor/ (production only)
- [ ] Exclude tests/, dev dependencies

### 3. Stub file creation
- [ ] Create \`build/phar-stub.php\` as PHAR entry point
- [ ] Include autoloader initialization
- [ ] Set up error handling
- [ ] Add version information

### 4. CLI entry point
- [ ] Create \`bin/blockchain.php\` CLI tool
- [ ] Support basic commands: version, help
- [ ] Support driver operations if useful
- [ ] Handle arguments and options

### 5. Dependency optimization
- [ ] Run composer install --no-dev before build
- [ ] Optimize autoloader: composer dump-autoload --optimize
- [ ] Remove unnecessary files (tests, docs)
- [ ] Strip comments if desired

### 6. Compression and signing
- [ ] Add compression support (gzip or bzip2)
- [ ] Optionally support PHAR signing
- [ ] Generate signature file if signing enabled
- [ ] Document signing key management

### 7. Tests
- [ ] Create \`tests/Build/PharBuildTest.php\`
- [ ] Test PHAR creation in dry-run mode
- [ ] Verify PHAR can be executed
- [ ] Test CLI commands work from PHAR

### 8. CI integration
- [ ] Add PHAR build job to release workflow
- [ ] Upload PHAR as release artifact
- [ ] Test PHAR in clean environment
- [ ] Document PHAR usage

## Acceptance Criteria
- [x] build/phar.sh produces dist/php-blockchain.phar
- [x] PHAR runs in dry-run mode successfully
- [x] CLI commands accessible from PHAR

## Files Created
- \`build/phar.sh\`
- \`build/phar-builder.php\`
- \`build/phar-stub.php\`
- \`bin/blockchain.php\`
- \`tests/Build/PharBuildTest.php\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Docker Image
print_issue_header "4" "Docker Image"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Add Dockerfile and build script for integration testing" \
    --milestone "$MILESTONE" \
    --label "deployment,packaging,phase-2" \
    --body "## Overview
Create lightweight Docker image for integration testing and containerized deployments.

## Requirements
- Docker image with PHP and SDK
- Build script for CI
- Minimal image size

## Implementation Checklist

### 1. Create Dockerfile
- [ ] Create \`docker/Dockerfile\`
- [ ] Use official PHP image as base (php:8.2-cli-alpine)
- [ ] Install required PHP extensions
- [ ] Copy application code

### 2. Image optimization
- [ ] Use multi-stage build to reduce size
- [ ] Install composer dependencies in build stage
- [ ] Copy only production files to final stage
- [ ] Remove unnecessary packages

### 3. Runtime configuration
- [ ] Set working directory
- [ ] Configure PHP settings (memory, error reporting)
- [ ] Expose any needed ports
- [ ] Set entry point or CMD

### 4. Build script
- [ ] Create \`docker/build.sh\`
- [ ] Accept --tag flag for image tag
- [ ] Support --no-cache flag
- [ ] Show build progress

### 5. Testing setup
- [ ] Create \`docker/test.sh\` to run tests in container
- [ ] Mount test configuration
- [ ] Run PHPUnit inside container
- [ ] Collect coverage if requested

### 6. Docker Compose (optional)
- [ ] Create \`docker-compose.yml\` for local development
- [ ] Include PHP service
- [ ] Include database services if needed
- [ ] Configure volume mounts

### 7. CI integration
- [ ] Add Docker build job to CI
- [ ] Build image in dry-run mode
- [ ] Verify image manifest
- [ ] Optionally push to registry

### 8. Documentation
- [ ] Add Docker guide to \`docs/docker.md\`
- [ ] Explain how to build image
- [ ] Provide usage examples
- [ ] Document environment variables

## Acceptance Criteria
- [x] docker build produces valid image
- [x] CI builds image in dry-run mode
- [x] Documentation complete

## Files Created
- \`docker/Dockerfile\`
- \`docker/build.sh\`
- \`docker/test.sh\`
- \`docker-compose.yml\` (optional)
- \`docs/docker.md\`
" || echo "⚠ Issue 4 may already exist"

print_issue_header "✓" "All Deployment & Distribution issues attempted"
echo "Done. Review output above for any warnings about existing issues."
