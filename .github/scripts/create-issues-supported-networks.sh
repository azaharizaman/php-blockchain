#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Supported Networks Implementation
# Usage: ./create-issues-supported-networks.sh [REPO]
# Example: ./create-issues-supported-networks.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Supported Networks"

echo "Creating GitHub issues for Supported Networks..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Add network profile registry, quick switching support, and validation for multiple blockchain networks (mainnet/testnet/custom)" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "networks"
    "configuration"
    "testing"
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

# Issue 1: Network Profile Registry
print_issue_header "1" "Network Profile Registry"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Create NetworkProfiles registry with built-in profiles" \
    --milestone "$MILESTONE" \
    --label "feature,networks,configuration,phase-1" \
    --body "## Overview
Implement network profile registry that maps logical names to driver configuration templates for quick network switching.

## Requirements
- **REQ-001**: Provide NetworkProfiles registry mapping names to configs
- **REQ-002**: Support quick switching via BlockchainManager
- **CON-001**: Profiles must be serializable and validated

## Implementation Checklist

### 1. Create NetworkProfiles class
- [ ] Create file \`src/Config/NetworkProfiles.php\`
- [ ] Add namespace: \`namespace Blockchain\\Config;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Implement as singleton or static helper class

### 2. Built-in profiles
- [ ] Add profile for \`solana.mainnet\` with RPC endpoint and chain config
- [ ] Add profile for \`solana.devnet\` with devnet endpoint
- [ ] Add profile for \`solana.testnet\` with testnet endpoint
- [ ] Add profile for \`ethereum.mainnet\` with Infura/Alchemy placeholder
- [ ] Add profile for \`ethereum.goerli\` (or Sepolia) with testnet config
- [ ] Add profile for \`ethereum.localhost\` for local development

### 3. Profile structure
- [ ] Define profile array structure with keys: driver, endpoint, chainId, options
- [ ] Add phpdoc: \`@return array<string,mixed>\`
- [ ] Ensure profiles include all required config keys for drivers
- [ ] Support environment variable interpolation for API keys

### 4. Profile retrieval
- [ ] Implement \`public static function get(string \$name): array\`
- [ ] Throw \`InvalidArgumentException\` if profile not found
- [ ] Implement \`public static function has(string \$name): bool\`
- [ ] Implement \`public static function all(): array\` returning all profile names
- [ ] Add phpdoc: \`@param string \$name Profile name like 'ethereum.mainnet'\`

### 5. Validation integration
- [ ] Ensure profiles are validated via ConfigLoader::validateConfig
- [ ] Add validation test in registry load
- [ ] Document required keys per driver type

### 6. BlockchainManager integration
- [ ] Update BlockchainManager to accept profile names in setDriver
- [ ] Add \`setDriverByProfile(string \$profileName): self\` convenience method
- [ ] Resolve profile to config array automatically

### 7. Tests
- [ ] Create \`tests/Config/NetworkProfilesTest.php\`
- [ ] Test retrieval of all built-in profiles
- [ ] Test profile validation with ConfigLoader
- [ ] Test error handling for missing profiles
- [ ] Test has() method accuracy
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Add profiles documentation to \`docs/configuration.md\`
- [ ] List all available profiles with descriptions
- [ ] Explain how to use profiles with BlockchainManager
- [ ] Document environment variable usage for API keys

## Acceptance Criteria
- [x] NetworkProfiles::get('solana.mainnet') returns valid config
- [x] All profiles validated via ConfigLoader
- [x] Tests and static analysis pass

## Files Created
- \`src/Config/NetworkProfiles.php\`
- \`tests/Config/NetworkProfilesTest.php\`
- Updated \`docs/configuration.md\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: CLI Network Switcher
print_issue_header "2" "CLI Network Switcher"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Add CLI helper for network switching" \
    --milestone "$MILESTONE" \
    --label "feature,networks,configuration,testing,phase-1" \
    --body "## Overview
Create CLI utility for developers to quickly switch between network profiles and output configuration for local development.

## Requirements
- CLI prints JSON config
- Optional write to config/active.json
- Exits 0 in dry-run mode

## Implementation Checklist

### 1. Create CLI script
- [ ] Create file \`bin/switch-network.php\`
- [ ] Add shebang: \`#!/usr/bin/env php\`
- [ ] Make executable: \`chmod +x bin/switch-network.php\`
- [ ] Add namespace and autoloader

### 2. Argument parsing
- [ ] Accept network profile name as first argument
- [ ] Support --output flag to write to file
- [ ] Support --dry-run flag (default true)
- [ ] Support --format flag (json|php|env)
- [ ] Show usage help if no arguments

### 3. Profile resolution
- [ ] Load profile using NetworkProfiles::get()
- [ ] Validate profile exists before proceeding
- [ ] Apply environment variable substitution if needed

### 4. Output formatting
- [ ] Implement JSON output: \`json_encode(\$profile, JSON_PRETTY_PRINT)\`
- [ ] Implement PHP array output for inclusion in config files
- [ ] Implement ENV format output (KEY=value pairs)
- [ ] Write to stdout by default

### 5. File writing
- [ ] If --output specified, write to that path
- [ ] Create directory if it doesn't exist
- [ ] Confirm overwrite if file exists (unless --force)
- [ ] Set appropriate file permissions (0600 for sensitive configs)

### 6. Error handling
- [ ] Catch exceptions and print user-friendly errors
- [ ] Exit with appropriate codes (0=success, 1=error)
- [ ] Log to stderr for errors, stdout for output

### 7. Tests
- [ ] Create \`tests/Bin/SwitchNetworkTest.php\`
- [ ] Test argument parsing
- [ ] Test output formatting for all formats
- [ ] Test file writing with temp directories
- [ ] Test error handling for invalid profiles

### 8. Documentation
- [ ] Add usage examples to \`bin/switch-network.php\` docblock
- [ ] Document in \`docs/cli-tools.md\`
- [ ] Provide common usage patterns

## Acceptance Criteria
- [x] CLI prints JSON config in dry-run mode
- [x] Exit code 0 on success
- [x] Tests cover argument parsing and output

## Files Created
- \`bin/switch-network.php\`
- \`tests/Bin/SwitchNetworkTest.php\`
- \`docs/cli-tools.md\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: Endpoint Validator
print_issue_header "3" "Endpoint Validator"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement endpoint validator for custom endpoints" \
    --milestone "$MILESTONE" \
    --label "feature,networks,testing,phase-2" \
    --body "## Overview
Add utility to validate custom RPC endpoint reachability and basic functionality before use in production.

## Requirements
- Check URL reachability
- Optional ping or health check
- Dry-run mode without network calls

## Implementation Checklist

### 1. Create EndpointValidator class
- [ ] Create file \`src/Utils/EndpointValidator.php\`
- [ ] Add namespace: \`namespace Blockchain\\Utils;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Accept Guzzle client in constructor

### 2. Validation methods
- [ ] Implement \`public function validate(string \$endpoint, array \$options = []): ValidationResult\`
- [ ] Implement \`public function validateDryRun(string \$endpoint): ValidationResult\`
- [ ] Return structured ValidationResult with status, latency, error message
- [ ] Add phpdoc: \`@param array<string,mixed> \$options\`

### 3. ValidationResult class
- [ ] Create \`src/Utils/ValidationResult.php\` value object
- [ ] Properties: bool \$isValid, ?float \$latency, ?string \$error
- [ ] Implement \`isValid(): bool\`, \`getLatency(): ?float\`, \`getError(): ?string\`
- [ ] Make immutable

### 4. Dry-run mode
- [ ] In dry-run, validate URL format only (parse_url)
- [ ] Check scheme is http/https/wss
- [ ] Check host is present
- [ ] No actual network calls in dry-run
- [ ] Return ValidationResult with isValid based on format

### 5. Live validation
- [ ] Perform HTTP HEAD or GET request to endpoint
- [ ] Measure latency with microtime
- [ ] Check for 2xx response code
- [ ] Optionally send RPC ping request if --rpc-ping flag
- [ ] Handle network errors gracefully

### 6. RPC ping support
- [ ] For Solana: send getHealth or getVersion RPC call
- [ ] For Ethereum: send eth_chainId or web3_clientVersion
- [ ] Verify valid JSON-RPC response structure
- [ ] Report specific errors for invalid responses

### 7. Tests
- [ ] Create \`tests/Utils/EndpointValidatorTest.php\`
- [ ] Test dry-run mode with valid/invalid URLs
- [ ] Test live mode with MockHandler
- [ ] Test RPC ping with mocked responses
- [ ] Test error handling for network failures
- [ ] Run \`composer run phpstan\`

### 8. Documentation
- [ ] Add usage guide to \`docs/validation.md\`
- [ ] Explain dry-run vs live validation
- [ ] Provide examples for common scenarios

## Acceptance Criteria
- [x] Validator runs in dry-run mode without network calls
- [x] Returns valid/invalid based on URL format
- [x] Tests cover all validation modes

## Files Created
- \`src/Utils/EndpointValidator.php\`
- \`src/Utils/ValidationResult.php\`
- \`tests/Utils/EndpointValidatorTest.php\`
- \`docs/validation.md\`
" || echo "⚠ Issue 3 may already exist"

print_issue_header "✓" "All Supported Networks issues attempted"
echo "Done. Review output above for any warnings about existing issues."
