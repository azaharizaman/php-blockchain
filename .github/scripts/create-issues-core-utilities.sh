#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Core Utilities Implementation
# Usage: ./create-issues-core-utilities.sh [REPO]
# Example: ./create-issues-core-utilities.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Core Utilities"

echo "Creating GitHub issues for Core Utilities Implementation..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement core utilities, interfaces, manager, registry, and exceptions for PHP Blockchain SDK" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "core"
    "utilities"
    "architecture"
    "testing"
    "documentation"
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

# Issue 1: BlockchainDriverInterface
echo "Creating Issue 1: BlockchainDriverInterface..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Create BlockchainDriverInterface with core methods" \
    --milestone "$MILESTONE" \
    --label "feature,core,architecture,phase-1" \
    --body "## Overview
Create the main contract interface that all blockchain drivers must implement. This interface defines the core methods for blockchain interactions.

## Requirements
- **REQ-001**: Implement \`Blockchain\Contracts\BlockchainDriverInterface\` with core methods and typed phpdoc
- **CON-001**: Target PHP 8.2+, PHPStan level 7 compliance

## Implementation Checklist

### 1. Create Interface File
- [ ] Create directory \`src/Contracts/\` if it doesn't exist
- [ ] Create file \`src/Contracts/BlockchainDriverInterface.php\`
- [ ] Add PHP 8.2+ namespace declaration: \`namespace Blockchain\Contracts;\`
- [ ] Add strict types declaration at top of file

### 2. Implement Core Methods
- [ ] Add \`connect(array \$config): void\` method signature
  - [ ] Add phpdoc: \`@param array<string,mixed> \$config\`
  - [ ] Add description of config parameters in docblock
- [ ] Add \`getBalance(string \$address): float\` method signature
  - [ ] Add phpdoc with parameter and return type descriptions
- [ ] Add \`sendTransaction(string \$from, string \$to, float \$amount, array \$options = []): string\` method signature
  - [ ] Add phpdoc: \`@param array<string,mixed> \$options\`
  - [ ] Add \`@return string\` (transaction hash)
- [ ] Add \`getTransaction(string \$hash): array\` method signature
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
- [ ] Add \`getBlock(int|string \$blockIdentifier): array\` method signature
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
- [ ] Add \`estimateGas(string \$from, string \$to, float \$amount, array \$options = []): ?int\` method signature
  - [ ] Add phpdoc: \`@param array<string,mixed> \$options\`
- [ ] Add \`getTokenBalance(string \$address, string \$tokenAddress): ?float\` method signature
  - [ ] Add phpdoc with nullable return explanation
- [ ] Add \`getNetworkInfo(): ?array\` method signature
  - [ ] Add phpdoc: \`@return array<string,mixed>|null\`

### 3. Documentation
- [ ] Add comprehensive interface-level docblock explaining purpose
- [ ] Document expected config array structure in connect() docblock
- [ ] Document expected return array structures for getTransaction() and getBlock()
- [ ] Add @throws annotations for potential exceptions

### 4. Validation
- [ ] Run \`composer run phpstan\` and verify no errors
- [ ] Verify file follows PSR-12 coding standards
- [ ] Check that all array types use generic syntax: \`array<string,mixed>\`
- [ ] Ensure all method signatures are compatible with PHP 8.2+

## Acceptance Criteria
- [x] File \`src/Contracts/BlockchainDriverInterface.php\` exists
- [x] All 8 core methods defined with proper signatures
- [x] All array parameters and returns use typed phpdoc (\`array<string,mixed>\`)
- [x] PHPStan level 7 reports no typing errors for this interface
- [x] Interface is PSR-4 autoloadable

## Files Created
- \`src/Contracts/BlockchainDriverInterface.php\`

## Related
- Epic: \`docs/prd/01-CORE-UTILITIES-EPIC.md\`
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-001)
" || echo "⚠ Issue 1 may already exist"

# Issue 2: BlockchainManager
echo "Creating Issue 2: BlockchainManager..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Implement BlockchainManager orchestrator class" \
    --milestone "$MILESTONE" \
    --label "feature,core,architecture,phase-1" \
    --body "## Overview
Implement the main BlockchainManager class that orchestrates driver lifecycle, provides driver switching, and exposes a unified API for blockchain operations.

## Requirements
- **REQ-002**: Implement \`BlockchainManager\` for driver lifecycle and unified API
- **CON-001**: PHP 8.2+, PHPStan level 7 compliance

## Implementation Checklist

### 1. Create BlockchainManager Class
- [ ] Create file \`src/BlockchainManager.php\`
- [ ] Add namespace: \`namespace Blockchain;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class BlockchainManager implements Contracts\BlockchainDriverInterface\`

### 2. Properties and Constructor
- [ ] Add private property: \`private ?BlockchainDriverInterface \$currentDriver = null\`
- [ ] Add private property: \`private array \$drivers = []\` with phpdoc \`@var array<string,BlockchainDriverInterface>\`
- [ ] Add private property: \`private Registry\DriverRegistry \$registry\`
- [ ] Add constructor accepting optional \`DriverRegistry \$registry = null\`
  - [ ] Initialize registry (create new if null)
  - [ ] Register default drivers via \`\$this->registry->registerDefaultDrivers()\`

### 3. Driver Management Methods
- [ ] Implement \`setDriver(string \$name, array \$config): self\`
  - [ ] Check if driver exists in registry via \`hasDriver()\`
  - [ ] Throw \`UnsupportedDriverException\` if not found
  - [ ] Instantiate driver class from registry
  - [ ] Call \`connect(\$config)\` on driver instance
  - [ ] Store in \`\$this->drivers[\$name]\`
  - [ ] Set as \`\$this->currentDriver\`
  - [ ] Return \`\$this\` for fluent interface
  - [ ] Add phpdoc: \`@param array<string,mixed> \$config\`
  - [ ] Add \`@throws UnsupportedDriverException\`
  
- [ ] Implement \`switchDriver(string \$name): self\`
  - [ ] Check if driver already loaded in \`\$this->drivers\`
  - [ ] Throw exception if driver not previously set
  - [ ] Set \`\$this->currentDriver\` to loaded driver
  - [ ] Return \`\$this\`

- [ ] Implement \`getSupportedDrivers(): array\`
  - [ ] Return \`\$this->registry->getRegisteredDrivers()\`
  - [ ] Add phpdoc: \`@return array<int,string>\`

### 4. Proxy Methods (Implement BlockchainDriverInterface)
Each method should check if currentDriver is set, throw exception if not, then proxy to driver:

- [ ] Implement \`connect(array \$config): void\`
  - [ ] Check \`\$this->currentDriver !== null\`
  - [ ] Throw \`ConfigurationException\` if no driver set
  - [ ] Call \`\$this->currentDriver->connect(\$config)\`

- [ ] Implement \`getBalance(string \$address): float\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->getBalance(\$address)\`

- [ ] Implement \`sendTransaction(string \$from, string \$to, float \$amount, array \$options = []): string\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->sendTransaction(...)\`

- [ ] Implement \`getTransaction(string \$hash): array\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->getTransaction(\$hash)\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`

- [ ] Implement \`getBlock(int|string \$blockIdentifier): array\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->getBlock(\$blockIdentifier)\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`

- [ ] Implement \`estimateGas(string \$from, string \$to, float \$amount, array \$options = []): ?int\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->estimateGas(...)\`

- [ ] Implement \`getTokenBalance(string \$address, string \$tokenAddress): ?float\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->getTokenBalance(...)\`

- [ ] Implement \`getNetworkInfo(): ?array\`
  - [ ] Guard against null driver
  - [ ] Proxy to \`\$this->currentDriver->getNetworkInfo()\`
  - [ ] Add phpdoc: \`@return array<string,mixed>|null\`

### 5. Unit Tests
- [ ] Create \`tests/BlockchainManagerTest.php\`
- [ ] Test driver switching functionality
  - [ ] Test \`setDriver()\` with valid driver name
  - [ ] Test \`setDriver()\` throws \`UnsupportedDriverException\` for invalid driver
  - [ ] Test \`switchDriver()\` switches between loaded drivers
  - [ ] Test \`switchDriver()\` throws exception for unloaded driver
- [ ] Test proxy methods throw exception when no driver set
- [ ] Test \`getSupportedDrivers()\` returns registry drivers
- [ ] Test fluent interface (method chaining)
- [ ] Use mock driver for testing (don't test actual blockchain calls)

### 6. Validation
- [ ] Run \`composer run phpstan\` - should report no errors
- [ ] Run \`vendor/bin/phpunit tests/BlockchainManagerTest.php\` - all tests pass
- [ ] Verify PSR-12 compliance
- [ ] Check all phpdoc annotations present and correct

## Acceptance Criteria
- [x] \`BlockchainManager\` class implements \`BlockchainDriverInterface\`
- [x] Driver switching methods (\`setDriver\`, \`switchDriver\`) working
- [x] All proxy methods implemented with null driver guards
- [x] Unit tests validate driver switching and exception handling
- [x] PHPStan reports no errors
- [x] Tests demonstrate exception thrown when driver not configured

## Files Created/Modified
- \`src/BlockchainManager.php\` (new)
- \`tests/BlockchainManagerTest.php\` (new)

## Dependencies
- Requires: TASK-001 (BlockchainDriverInterface) completed
- Requires: TASK-003 (DriverRegistry) completed
- Requires: TASK-004 (Exception classes) completed

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-002)
" || echo "⚠ Issue 2 may already exist"

# Issue 3: DriverRegistry
echo "Creating Issue 3: DriverRegistry..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement DriverRegistry for runtime driver management" \
    --milestone "$MILESTONE" \
    --label "feature,core,architecture,phase-1" \
    --body "## Overview
Implement the DriverRegistry class that manages driver registration, retrieval, and provides default driver registration functionality.

## Requirements
- **REQ-003**: Implement \`DriverRegistry\` for runtime registration and default driver management
- **CON-001**: PHP 8.2+, PHPStan level 7 compliance

## Implementation Checklist

### 1. Create DriverRegistry Class
- [ ] Create directory \`src/Registry/\`
- [ ] Create file \`src/Registry/DriverRegistry.php\`
- [ ] Add namespace: \`namespace Blockchain\Registry;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class DriverRegistry\`

### 2. Properties
- [ ] Add private property: \`private array \$drivers = []\`
  - [ ] Add phpdoc: \`@var array<string,class-string<BlockchainDriverInterface>>\`
  - [ ] Stores driver name => fully qualified class name mappings

### 3. Core Methods
- [ ] Implement \`registerDriver(string \$name, string \$driverClass): void\`
  - [ ] Validate \$driverClass implements BlockchainDriverInterface
  - [ ] Check class exists using \`class_exists()\`
  - [ ] Check implements interface using \`is_subclass_of()\`
  - [ ] Throw \`ValidationException\` if validation fails
  - [ ] Store in \`\$this->drivers[\$name] = \$driverClass\`
  - [ ] Add phpdoc: \`@param class-string<BlockchainDriverInterface> \$driverClass\`

- [ ] Implement \`getDriver(string \$name): string\`
  - [ ] Check if driver exists in registry
  - [ ] Throw \`UnsupportedDriverException\` if not found
  - [ ] Return driver class name
  - [ ] Add phpdoc: \`@return class-string<BlockchainDriverInterface>\`

- [ ] Implement \`hasDriver(string \$name): bool\`
  - [ ] Return whether driver exists in \`\$this->drivers\`
  - [ ] Use \`array_key_exists()\` or \`isset()\`

- [ ] Implement \`getRegisteredDrivers(): array\`
  - [ ] Return array of registered driver names
  - [ ] Use \`array_keys(\$this->drivers)\`
  - [ ] Add phpdoc: \`@return array<int,string>\`

- [ ] Implement \`registerDefaultDrivers(): void\`
  - [ ] Register Solana driver: \`\$this->registerDriver('solana', \\Blockchain\\Drivers\\SolanaDriver::class)\`
  - [ ] Add comment explaining default drivers
  - [ ] Method should be idempotent (safe to call multiple times)

### 4. Unit Tests
- [ ] Create \`tests/Registry/DriverRegistryTest.php\`
- [ ] Test driver registration
  - [ ] Test \`registerDriver()\` adds driver successfully
  - [ ] Test \`registerDriver()\` throws exception for invalid class
  - [ ] Test \`registerDriver()\` throws exception for non-interface class
- [ ] Test driver retrieval
  - [ ] Test \`getDriver()\` returns correct class name
  - [ ] Test \`getDriver()\` throws \`UnsupportedDriverException\` for unknown driver
- [ ] Test \`hasDriver()\` returns true/false correctly
- [ ] Test \`getRegisteredDrivers()\` returns array of names
- [ ] Test \`registerDefaultDrivers()\` registers 'solana'
  - [ ] Verify \`hasDriver('solana')\` returns true after call
  - [ ] Verify \`getRegisteredDrivers()\` includes 'solana'

### 5. Integration with BlockchainManager
- [ ] Verify \`BlockchainManager->getSupportedDrivers()\` returns \`['solana']\`
- [ ] Test that manager can instantiate registered drivers
- [ ] Ensure PHPStan understands class-string types

### 6. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Registry/DriverRegistryTest.php\` - all pass
- [ ] Verify proper type annotations for class-string
- [ ] Check PSR-12 compliance

## Acceptance Criteria
- [x] \`DriverRegistry\` class with all methods implemented
- [x] Proper type annotations: \`@var array<string,class-string<BlockchainDriverInterface>>\`
- [x] \`registerDefaultDrivers()\` registers 'solana' driver
- [x] \`BlockchainManager->getSupportedDrivers()\` returns \`['solana']\`
- [x] PHPStan passes with no errors
- [x] Unit tests cover all registration and retrieval scenarios

## Files Created
- \`src/Registry/DriverRegistry.php\`
- \`tests/Registry/DriverRegistryTest.php\`

## Dependencies
- Requires: TASK-001 (BlockchainDriverInterface) completed
- Requires: TASK-004 (Exception classes) completed for ValidationException, UnsupportedDriverException

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-003)
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Exception Classes
echo "Creating Issue 4: Exception Classes..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Create structured exception classes" \
    --milestone "$MILESTONE" \
    --label "feature,core,architecture,phase-1" \
    --body "## Overview
Create structured exception classes for different error scenarios in the blockchain SDK. These provide type-safe error handling throughout the codebase.

## Requirements
- **REQ-004**: Implement structured exception classes under \`src/Exceptions/\`
- **CON-001**: PHP 8.2+, PSR-12 compliance

## Implementation Checklist

### 1. Setup Exception Directory
- [ ] Create directory \`src/Exceptions/\`
- [ ] Plan exception hierarchy (all extend \\Exception)

### 2. ConfigurationException
- [ ] Create file \`src/Exceptions/ConfigurationException.php\`
- [ ] Add namespace: \`namespace Blockchain\Exceptions;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class ConfigurationException extends \\Exception\`
- [ ] Add class docblock explaining when this exception is thrown
  - [ ] Document: \"Thrown when driver configuration is invalid or missing\"
- [ ] Add example usage in docblock

### 3. UnsupportedDriverException
- [ ] Create file \`src/Exceptions/UnsupportedDriverException.php\`
- [ ] Add namespace: \`namespace Blockchain\Exceptions;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class UnsupportedDriverException extends \\Exception\`
- [ ] Add class docblock explaining when this exception is thrown
  - [ ] Document: \"Thrown when requested driver is not registered or available\"
- [ ] Add example usage in docblock

### 4. TransactionException
- [ ] Create file \`src/Exceptions/TransactionException.php\`
- [ ] Add namespace: \`namespace Blockchain\Exceptions;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class TransactionException extends \\Exception\`
- [ ] Add class docblock explaining when this exception is thrown
  - [ ] Document: \"Thrown when transaction operations fail or are invalid\"
- [ ] Consider adding helper methods:
  - [ ] \`public function getTransactionHash(): ?string\` (optional)
  - [ ] Store transaction hash in private property if provided

### 5. ValidationException
- [ ] Create file \`src/Exceptions/ValidationException.php\`
- [ ] Add namespace: \`namespace Blockchain\Exceptions;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class ValidationException extends \\Exception\`
- [ ] Add class docblock explaining when this exception is thrown
  - [ ] Document: \"Thrown when input validation fails (addresses, amounts, etc.)\"
- [ ] Consider adding validation details property:
  - [ ] \`private array \$errors = []\` with getter (optional)

### 6. Usage Examples in Code
- [ ] Update \`BlockchainManager->setDriver()\` to throw \`UnsupportedDriverException\`
- [ ] Update \`BlockchainManager\` proxy methods to throw \`ConfigurationException\` when no driver set
- [ ] Update \`DriverRegistry->registerDriver()\` to throw \`ValidationException\`
- [ ] Add @throws annotations to relevant methods

### 7. Unit Tests
- [ ] Create \`tests/Exceptions/ExceptionTest.php\`
- [ ] Test all exceptions can be instantiated
- [ ] Test exceptions extend \\Exception
- [ ] Test exceptions can be caught by type
- [ ] Test exception messages are preserved
- [ ] Test any additional methods (getTransactionHash, getErrors)
- [ ] Test exceptions are autoloadable

### 8. Documentation
- [ ] Add exceptions to README.md error handling section
- [ ] Document exception hierarchy
- [ ] Provide code examples for catching exceptions
- [ ] Document when each exception type is thrown

### 9. Validation
- [ ] Run \`composer dump-autoload\`
- [ ] Verify all exception classes are autoloadable
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Exceptions/ExceptionTest.php\` - all pass
- [ ] Check PSR-12 compliance for all exception files

## Acceptance Criteria
- [x] Four exception classes created in \`src/Exceptions/\`
- [x] All exceptions extend \\Exception
- [x] All exceptions in \`Blockchain\\Exceptions\` namespace
- [x] Exceptions are autoloadable and type-resolvable by PHPStan
- [x] Class docblocks explain when each exception is thrown
- [x] Unit tests verify exception functionality
- [x] README.md updated with exception documentation

## Files Created
- \`src/Exceptions/ConfigurationException.php\`
- \`src/Exceptions/UnsupportedDriverException.php\`
- \`src/Exceptions/TransactionException.php\`
- \`src/Exceptions/ValidationException.php\`
- \`tests/Exceptions/ExceptionTest.php\`

## Files Modified
- \`README.md\` (add error handling section)

## Dependencies
- None (foundational - no dependencies)

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-004)
" || echo "⚠ Issue 4 may already exist"

# Issue 5: Utilities Skeleton
echo "Creating Issue 5: Utilities Skeleton..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Add utility classes skeleton (AddressValidator, Serializer, HttpClientAdapter)" \
    --milestone "$MILESTONE" \
    --label "feature,core,utilities,phase-1" \
    --body "## Overview
Create skeleton implementations of utility classes that provide common functionality for blockchain operations: address validation, data serialization, and HTTP client abstraction.

## Requirements
- **REQ-001**: Utilities must support multiple blockchain formats
- **SEC-001**: No network calls during unit tests; use MockHandler for HTTP
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Setup Utilities Directory
- [ ] Verify directory \`src/Utils/\` exists (create if not)
- [ ] Plan utility class interfaces

### 2. AddressValidator Class
- [ ] Create file \`src/Utils/AddressValidator.php\`
- [ ] Add namespace: \`namespace Blockchain\Utils;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class AddressValidator\`
- [ ] Implement \`public static function isValid(string \$address, string \$network = 'solana'): bool\`
  - [ ] Add basic validation logic (length, character set)
  - [ ] Support 'solana' network (base58 encoding, 32-44 chars)
  - [ ] Return false for invalid addresses
  - [ ] Add phpdoc with parameter descriptions
- [ ] Implement \`public static function normalize(string \$address): string\`
  - [ ] Trim whitespace
  - [ ] Convert to lowercase (for hex addresses)
  - [ ] Return normalized address
- [ ] Add comprehensive docblocks

### 3. Serializer Class
- [ ] Create file \`src/Utils/Serializer.php\`
- [ ] Add namespace: \`namespace Blockchain\Utils;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class Serializer\`
- [ ] Implement \`public static function toJson(array \$data): string\`
  - [ ] Use \`json_encode()\` with JSON_THROW_ON_ERROR flag
  - [ ] Add phpdoc: \`@param array<string,mixed> \$data\`
  - [ ] Handle encoding errors
- [ ] Implement \`public static function fromJson(string \$json): array\`
  - [ ] Use \`json_decode()\` with JSON_THROW_ON_ERROR flag
  - [ ] Return associative array (second param = true)
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
  - [ ] Handle decoding errors
- [ ] Implement \`public static function toBase64(string \$data): string\`
  - [ ] Use \`base64_encode()\`
- [ ] Implement \`public static function fromBase64(string \$encoded): string\`
  - [ ] Use \`base64_decode()\`
  - [ ] Validate decoded data
- [ ] Add comprehensive docblocks

### 4. HttpClientAdapter Class
- [ ] Create file \`src/Utils/HttpClientAdapter.php\`
- [ ] Add namespace: \`namespace Blockchain\Utils;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class HttpClientAdapter\`
- [ ] Add private property: \`private \\GuzzleHttp\\Client \$client\`
- [ ] Implement constructor accepting optional Guzzle client
  - [ ] Create default Guzzle client if not provided
  - [ ] Set default timeout (30 seconds)
  - [ ] Set default headers (Content-Type: application/json)
- [ ] Implement \`public function get(string \$url, array \$options = []): array\`
  - [ ] Wrap Guzzle GET request
  - [ ] Parse JSON response
  - [ ] Handle HTTP errors
  - [ ] Add phpdoc: \`@param array<string,mixed> \$options\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
- [ ] Implement \`public function post(string \$url, array \$data, array \$options = []): array\`
  - [ ] Wrap Guzzle POST request
  - [ ] Encode data as JSON
  - [ ] Parse JSON response
  - [ ] Handle HTTP errors
  - [ ] Add appropriate phpdoc
- [ ] Add error handling method to convert Guzzle exceptions to blockchain exceptions

### 5. Unit Tests for AddressValidator
- [ ] Create \`tests/Utils/AddressValidatorTest.php\`
- [ ] Test \`isValid()\` with valid Solana addresses
  - [ ] Test base58 encoded addresses
  - [ ] Test various valid lengths (32-44 chars)
- [ ] Test \`isValid()\` with invalid addresses
  - [ ] Test too short addresses
  - [ ] Test invalid characters
  - [ ] Test empty strings
- [ ] Test \`normalize()\` function
  - [ ] Test trimming whitespace
  - [ ] Test lowercase conversion
- [ ] Use mock data (no real blockchain calls)

### 6. Unit Tests for Serializer
- [ ] Create \`tests/Utils/SerializerTest.php\`
- [ ] Test \`toJson()\` conversion
  - [ ] Test simple arrays
  - [ ] Test nested arrays
  - [ ] Test handling of special characters
- [ ] Test \`fromJson()\` conversion
  - [ ] Test valid JSON strings
  - [ ] Test invalid JSON (expect exception)
- [ ] Test \`toBase64()\` and \`fromBase64()\`
  - [ ] Test encoding and decoding
  - [ ] Test roundtrip conversion
- [ ] Test error handling

### 7. Unit Tests for HttpClientAdapter
- [ ] Create \`tests/Utils/HttpClientAdapterTest.php\`
- [ ] Use GuzzleHttp MockHandler (per SEC-001)
- [ ] Test \`get()\` method
  - [ ] Mock successful GET response
  - [ ] Mock HTTP error responses (404, 500)
  - [ ] Verify request parameters
- [ ] Test \`post()\` method
  - [ ] Mock successful POST response
  - [ ] Mock HTTP error responses
  - [ ] Verify JSON encoding of request body
- [ ] Test error handling
  - [ ] Test network errors
  - [ ] Test timeout handling
  - [ ] Verify exceptions are properly converted

### 8. Documentation
- [ ] Add utility classes to README.md
- [ ] Document AddressValidator usage
- [ ] Document Serializer usage
- [ ] Document HttpClientAdapter usage and Guzzle integration
- [ ] Provide code examples

### 9. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Utils/\` - all tests pass
- [ ] Verify PSR-12 compliance
- [ ] Check code coverage for utility classes (aim for 90%+)
- [ ] Verify no real HTTP calls in tests (use MockHandler)

## Acceptance Criteria
- [x] Three utility classes created in \`src/Utils/\`
- [x] AddressValidator with basic Solana address validation
- [x] Serializer with JSON and Base64 conversion
- [x] HttpClientAdapter wrapping Guzzle with error handling
- [x] Unit tests for all utilities with MockHandler for HTTP
- [x] PHPStan reports no errors
- [x] All tests pass, code coverage 90%+
- [x] Documentation updated in README.md

## Files Created
- \`src/Utils/AddressValidator.php\`
- \`src/Utils/Serializer.php\`
- \`src/Utils/HttpClientAdapter.php\`
- \`tests/Utils/AddressValidatorTest.php\`
- \`tests/Utils/SerializerTest.php\`
- \`tests/Utils/HttpClientAdapterTest.php\`

## Files Modified
- \`README.md\` (add utilities documentation)

## Dependencies
- Requires: \`guzzlehttp/guzzle\` package (already in composer.json)
- Requires: TASK-004 (Exception classes) completed

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-005)
- PRD: \`docs/prd/01-CORE-UTILITIES-EPIC.md\`
" || echo "⚠ Issue 5 may already exist"

# Issue 6: ConfigLoader (Phase 2)
echo "Creating Issue 6: ConfigLoader..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-006: Implement ConfigLoader with validation (Phase 2)" \
    --milestone "$MILESTONE" \
    --label "feature,core,utilities,phase-2" \
    --body "## Overview
Implement ConfigLoader class that loads driver configurations from multiple sources (array, env, file) and provides schema validation.

## Requirements
- **REQ-002**: Support multiple configuration sources
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Create ConfigLoader Class
- [ ] Create directory \`src/Config/\` if needed
- [ ] Create file \`src/Config/ConfigLoader.php\`
- [ ] Add namespace: \`namespace Blockchain\Config;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class ConfigLoader\`

### 2. Core Methods
- [ ] Implement \`public static function fromArray(array \$config): array\`
  - [ ] Validate and return config array directly
  - [ ] Add phpdoc: \`@param array<string,mixed> \$config\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
  
- [ ] Implement \`public static function fromEnv(string \$prefix = 'BLOCKCHAIN_'): array\`
  - [ ] Read environment variables with given prefix
  - [ ] Parse into config array structure
  - [ ] Example: BLOCKCHAIN_RPC_URL -> ['rpc_url' => ...]
  - [ ] Handle type conversions (string to int/bool)
  
- [ ] Implement \`public static function fromFile(string \$path): array\`
  - [ ] Support .php config files (return array)
  - [ ] Support .json config files
  - [ ] Validate file exists
  - [ ] Throw ConfigurationException if file invalid
  - [ ] Return parsed config array

### 3. Validation Method
- [ ] Implement \`public static function validateConfig(array \$config, string \$driver = 'solana'): bool\`
  - [ ] Define required keys per driver type
  - [ ] For Solana: require 'rpc_url'
  - [ ] Check required keys exist
  - [ ] Validate data types (URL strings, numeric values)
  - [ ] Throw ValidationException with details if invalid
  - [ ] Return true if valid
  - [ ] Add comprehensive phpdoc

### 4. Schema Definition
- [ ] Create \`private static function getDriverSchema(string \$driver): array\`
  - [ ] Define schema for 'solana' driver
    - [ ] Required: 'rpc_url' (string, valid URL)
    - [ ] Optional: 'timeout' (int, > 0)
    - [ ] Optional: 'commitment' (string, enum values)
  - [ ] Return schema array structure
  - [ ] Add phpdoc: \`@return array<string,mixed>\`

### 5. Helper Methods
- [ ] Implement \`private static function validateUrl(string \$url): bool\`
  - [ ] Use filter_var with FILTER_VALIDATE_URL
  - [ ] Check for http/https scheme
  
- [ ] Implement \`private static function parseEnvValue(string \$value): mixed\`
  - [ ] Convert 'true'/'false' strings to bool
  - [ ] Convert numeric strings to int/float
  - [ ] Return original string otherwise

### 6. Unit Tests
- [ ] Create \`tests/Config/ConfigLoaderTest.php\`
- [ ] Test \`fromArray()\` with valid config
- [ ] Test \`fromEnv()\` reading environment variables
  - [ ] Use putenv() to set test values
  - [ ] Clean up after tests
- [ ] Test \`fromFile()\` with PHP and JSON files
  - [ ] Create temporary test config files
  - [ ] Test file not found exception
  - [ ] Test invalid JSON exception
- [ ] Test \`validateConfig()\` with valid Solana config
  - [ ] Test returns true for valid config
  - [ ] Test throws exception for missing 'rpc_url'
  - [ ] Test throws exception for invalid URL format
  - [ ] Test throws exception for invalid types
- [ ] Test error messages are descriptive

### 7. Example Configs
- [ ] Create \`config/solana.example.php\`
  - [ ] Provide example Solana configuration
  - [ ] Include comments explaining each option
  - [ ] Show mainnet and devnet examples
  
- [ ] Create \`.env.example\` section for blockchain config
  - [ ] Add BLOCKCHAIN_RPC_URL example
  - [ ] Add other config examples

### 8. Documentation
- [ ] Update README.md with ConfigLoader usage
- [ ] Document all configuration sources
- [ ] Provide examples for each source (array, env, file)
- [ ] Document validation schema
- [ ] Explain error messages

### 9. Integration
- [ ] Update \`BlockchainManager\` to use ConfigLoader
  - [ ] Add optional ConfigLoader injection
  - [ ] Use ConfigLoader in setDriver() method
- [ ] Add integration test with BlockchainManager

### 10. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Config/ConfigLoaderTest.php\` - all pass
- [ ] Verify \`ConfigLoader::validateConfig()\` returns true for valid Solana config
- [ ] Test all configuration sources work correctly
- [ ] Check PSR-12 compliance

## Acceptance Criteria
- [x] ConfigLoader class supports array, env, and file sources
- [x] \`validateConfig()\` method with Solana schema validation
- [x] \`ConfigLoader::validateConfig()\` returns true for valid Solana config
- [x] Comprehensive error messages for validation failures
- [x] Unit tests covering all methods and error cases
- [x] Example configuration files created
- [x] README.md updated with usage examples
- [x] PHPStan passes

## Files Created
- \`src/Config/ConfigLoader.php\`
- \`tests/Config/ConfigLoaderTest.php\`
- \`config/solana.example.php\`

## Files Modified
- \`.env.example\` (add blockchain config)
- \`README.md\` (add ConfigLoader documentation)
- \`src/BlockchainManager.php\` (optional: integrate ConfigLoader)

## Dependencies
- Requires: TASK-004 (Exception classes) completed
- Requires: Phase 1 tasks completed

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-006)
" || echo "⚠ Issue 6 may already exist"

# Issue 7: CachePool (Phase 2)
echo "Creating Issue 7: CachePool..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-007: Implement CachePool and integrate into drivers (Phase 2)" \
    --milestone "$MILESTONE" \
    --label "feature,core,utilities,phase-2" \
    --body "## Overview
Implement a simple cache pool (PSR-6 compatible or array-backed) for caching blockchain responses and integrate it into drivers via constructor injection.

## Requirements
- **REQ-002**: Support caching to reduce redundant blockchain calls
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Create CachePool Class
- [ ] Create file \`src/Utils/CachePool.php\`
- [ ] Add namespace: \`namespace Blockchain\Utils;\`
- [ ] Add strict types declaration
- [ ] Decide on implementation: PSR-6 interface or simple array-backed cache
  - [ ] For simplicity: array-backed in-memory cache
  - [ ] For future: PSR-6 CacheItemPoolInterface implementation

### 2. Array-Backed Cache Implementation
- [ ] Declare class: \`class CachePool\`
- [ ] Add private property: \`private array \$cache = []\`
  - [ ] Add phpdoc: \`@var array<string,array{value:mixed,expires:int}>\`
- [ ] Add private property: \`private int \$defaultTtl = 300\` (5 minutes)

### 3. Core Cache Methods
- [ ] Implement \`public function get(string \$key, mixed \$default = null): mixed\`
  - [ ] Check if key exists in cache
  - [ ] Check if item has expired (compare with time())
  - [ ] Remove expired items
  - [ ] Return value or default if not found/expired
  
- [ ] Implement \`public function set(string \$key, mixed \$value, ?int \$ttl = null): bool\`
  - [ ] Use default TTL if not specified
  - [ ] Calculate expiration timestamp: time() + ttl
  - [ ] Store in cache: ['value' => \$value, 'expires' => \$expires]
  - [ ] Return true on success
  
- [ ] Implement \`public function has(string \$key): bool\`
  - [ ] Check if key exists and not expired
  
- [ ] Implement \`public function delete(string \$key): bool\`
  - [ ] Remove key from cache
  - [ ] Return true if existed, false otherwise
  
- [ ] Implement \`public function clear(): bool\`
  - [ ] Clear entire cache array
  - [ ] Return true

### 4. Additional Methods
- [ ] Implement \`public function setDefaultTtl(int \$seconds): void\`
  - [ ] Update default TTL
  - [ ] Validate \$seconds > 0
  
- [ ] Implement \`public function getMultiple(array \$keys, mixed \$default = null): array\`
  - [ ] Bulk get operation
  - [ ] Add phpdoc: \`@param array<string> \$keys\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`
  
- [ ] Implement \`public function setMultiple(array \$values, ?int \$ttl = null): bool\`
  - [ ] Bulk set operation
  - [ ] Add phpdoc: \`@param array<string,mixed> \$values\`

### 5. Cache Key Generation
- [ ] Implement \`public static function generateKey(string \$method, array \$params): string\`
  - [ ] Create deterministic cache key from method name and parameters
  - [ ] Use hash (md5 or sha256) of serialized params
  - [ ] Format: \"blockchain:{method}:{hash}\"
  - [ ] Add phpdoc: \`@param array<string,mixed> \$params\`

### 6. Integrate into SolanaDriver
- [ ] Update \`src/Drivers/SolanaDriver.php\` constructor
  - [ ] Add optional \`CachePool \$cache = null\` parameter
  - [ ] Store as private property
  - [ ] Create default cache if not provided
  
- [ ] Update \`getBalance()\` method to use cache
  - [ ] Generate cache key from method and address
  - [ ] Check cache first
  - [ ] If cache hit, return cached value
  - [ ] If cache miss, fetch from blockchain
  - [ ] Store result in cache before returning
  
- [ ] Update other read methods (getTransaction, getBlock) similarly
  - [ ] Cache GET operations only (not write operations)
  - [ ] Use appropriate TTL for different data types

### 7. Unit Tests for CachePool
- [ ] Create \`tests/Utils/CachePoolTest.php\`
- [ ] Test \`set()\` and \`get()\` operations
  - [ ] Test storing and retrieving values
  - [ ] Test default return value when key doesn't exist
- [ ] Test TTL expiration
  - [ ] Set item with short TTL (1 second)
  - [ ] Sleep and verify item expires
  - [ ] Test expired items are cleaned up
- [ ] Test \`has()\` method
- [ ] Test \`delete()\` method
- [ ] Test \`clear()\` method
- [ ] Test bulk operations (getMultiple, setMultiple)
- [ ] Test cache key generation
  - [ ] Verify deterministic keys
  - [ ] Test same params produce same key

### 8. Integration Tests
- [ ] Create \`tests/Integration/CacheIntegrationTest.php\`
- [ ] Test SolanaDriver with cache
  - [ ] Mock HTTP responses
  - [ ] Call getBalance() twice with same address
  - [ ] Verify HTTP client called only once (cache hit)
  - [ ] Verify cached value returned on second call
- [ ] Test cache TTL with driver
  - [ ] Set short TTL
  - [ ] Verify cache expires and refetches

### 9. Documentation
- [ ] Update README.md with caching documentation
- [ ] Document CachePool API
- [ ] Document cache integration in drivers
- [ ] Provide examples of custom cache usage
- [ ] Document cache key format and TTL defaults
- [ ] Explain when to clear cache

### 10. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Utils/CachePoolTest.php\` - all pass
- [ ] Run integration tests - verify cache working
- [ ] Verify drivers accept optional cache dependency
- [ ] Unit tests demonstrate cached response usage
- [ ] Check PSR-12 compliance

## Acceptance Criteria
- [x] CachePool class implemented (array-backed or PSR-6)
- [x] Core cache operations: get, set, has, delete, clear
- [x] TTL support with automatic expiration
- [x] Cache key generation utility
- [x] Integrated into SolanaDriver via constructor injection
- [x] Unit tests cover all cache operations
- [x] Integration tests demonstrate cache hits and TTL
- [x] Drivers accept optional cache dependency
- [x] README.md updated with cache documentation

## Files Created
- \`src/Utils/CachePool.php\`
- \`tests/Utils/CachePoolTest.php\`
- \`tests/Integration/CacheIntegrationTest.php\`

## Files Modified
- \`src/Drivers/SolanaDriver.php\` (add cache support)
- \`tests/SolanaDriverTest.php\` (update for cache parameter)
- \`README.md\` (add caching documentation)

## Dependencies
- Requires: TASK-001, TASK-002 completed (drivers exist)
- Requires: Phase 1 completed

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-007)
" || echo "⚠ Issue 7 may already exist"

# Issue 8: GuzzleAdapter (Phase 2)
echo "Creating Issue 8: GuzzleAdapter..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-008: Add GuzzleAdapter for centralized HTTP handling (Phase 2)" \
    --milestone "$MILESTONE" \
    --label "feature,core,utilities,phase-2" \
    --body "## Overview
Implement GuzzleAdapter as a centralized HTTP client wrapper that implements HttpClientAdapter interface, providing standardized error handling and configuration for all drivers.

## Requirements
- **REQ-002**: Centralize HTTP client configuration and error handling
- **SEC-001**: Support MockHandler for unit testing
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Define HttpClientAdapter Interface
- [ ] Create file \`src/Transport/HttpClientAdapter.php\` (if not from TASK-005)
- [ ] If it's a class from TASK-005, refactor to interface
- [ ] Add namespace: \`namespace Blockchain\Transport;\`
- [ ] Declare interface: \`interface HttpClientAdapter\`
- [ ] Define method signatures:
  - [ ] \`public function get(string \$url, array \$options = []): array;\`
  - [ ] \`public function post(string \$url, array \$data, array \$options = []): array;\`
  - [ ] Add phpdoc: \`@return array<string,mixed>\`

### 2. Create GuzzleAdapter Implementation
- [ ] Create file \`src/Transport/GuzzleAdapter.php\`
- [ ] Add namespace: \`namespace Blockchain\Transport;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class GuzzleAdapter implements HttpClientAdapter\`
- [ ] Add private property: \`private \\GuzzleHttp\\Client \$client\`

### 3. Constructor and Configuration
- [ ] Implement constructor \`__construct(?\\GuzzleHttp\\Client \$client = null, array \$config = [])\`
  - [ ] Accept optional Guzzle client (for testing with MockHandler)
  - [ ] If client is null, create with default config
  - [ ] Merge user config with defaults
  - [ ] Add phpdoc: \`@param array<string,mixed> \$config\`
  
- [ ] Define default configuration:
  - [ ] Timeout: 30 seconds
  - [ ] Connect timeout: 10 seconds
  - [ ] Headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json']
  - [ ] Verify SSL: true
  - [ ] HTTP errors: false (we'll handle manually)

### 4. GET Method Implementation
- [ ] Implement \`public function get(string \$url, array \$options = []): array\`
  - [ ] Merge options with defaults
  - [ ] Wrap in try-catch for GuzzleHttp exceptions
  - [ ] Call \`\$this->client->get(\$url, \$options)\`
  - [ ] Get response body: \`\$response->getBody()->getContents()\`
  - [ ] Parse JSON: \`json_decode(\$body, true, 512, JSON_THROW_ON_ERROR)\`
  - [ ] Handle non-2xx status codes
  - [ ] Convert Guzzle exceptions to blockchain exceptions
  - [ ] Add comprehensive phpdoc

### 5. POST Method Implementation
- [ ] Implement \`public function post(string \$url, array \$data, array \$options = []): array\`
  - [ ] Add data to options as 'json' key
  - [ ] Wrap in try-catch
  - [ ] Call \`\$this->client->post(\$url, \$options)\`
  - [ ] Parse JSON response
  - [ ] Handle errors same as GET
  - [ ] Add comprehensive phpdoc

### 6. Error Handling
- [ ] Implement \`private function handleException(\\Throwable \$e): never\`
  - [ ] Map \`ConnectException\` to \`ConfigurationException\` (network errors)
  - [ ] Map \`ClientException\` (4xx) to \`ValidationException\`
  - [ ] Map \`ServerException\` (5xx) to \`TransactionException\`
  - [ ] Map \`RequestException\` to generic exception
  - [ ] Include original error message and code
  - [ ] Preserve stack trace
  
- [ ] Implement \`private function handleHttpError(ResponseInterface \$response): never\`
  - [ ] Check status code
  - [ ] Parse error message from response body if available
  - [ ] Throw appropriate exception type based on status code
  - [ ] Include status code in exception message

### 7. Helper Methods
- [ ] Implement \`public function setDefaultHeader(string \$name, string \$value): void\`
  - [ ] Allow adding custom headers to all requests
  
- [ ] Implement \`public function setTimeout(int \$seconds): void\`
  - [ ] Update timeout configuration
  - [ ] Validate \$seconds > 0

### 8. Update SolanaDriver to Use GuzzleAdapter
- [ ] Refactor \`src/Drivers/SolanaDriver.php\`
- [ ] Update constructor to accept \`GuzzleAdapter\` instead of raw Guzzle client
  - [ ] \`public function __construct(?GuzzleAdapter \$httpClient = null, ?CachePool \$cache = null)\`
  - [ ] Create default GuzzleAdapter if not provided
- [ ] Replace all \`\$this->client->post()\` calls with \`\$this->httpClient->post()\`
- [ ] Remove manual exception handling (now in adapter)
- [ ] Simplify driver code

### 9. Unit Tests for GuzzleAdapter
- [ ] Create \`tests/Transport/GuzzleAdapterTest.php\`
- [ ] Use MockHandler for all tests (per SEC-001)
- [ ] Test GET method
  - [ ] Mock successful response with JSON body
  - [ ] Mock 404 error
  - [ ] Mock 500 error
  - [ ] Verify correct exceptions thrown
- [ ] Test POST method
  - [ ] Mock successful response
  - [ ] Verify request body contains JSON data
  - [ ] Mock error responses
- [ ] Test network errors
  - [ ] Mock ConnectException
  - [ ] Verify ConfigurationException thrown
- [ ] Test timeout configuration
- [ ] Test custom headers
- [ ] Test error message parsing

### 10. Integration Tests
- [ ] Update \`tests/SolanaDriverTest.php\`
  - [ ] Update to use GuzzleAdapter with MockHandler
  - [ ] Verify adapter integration works
  - [ ] Test that driver errors are properly mapped
- [ ] Create \`tests/Integration/GuzzleAdapterIntegrationTest.php\`
  - [ ] Test adapter with multiple drivers
  - [ ] Test error handling across drivers

### 11. Documentation
- [ ] Update README.md with GuzzleAdapter documentation
- [ ] Document HttpClientAdapter interface
- [ ] Provide examples of custom HTTP configuration
- [ ] Document error mapping
- [ ] Show how to inject custom adapter for testing

### 12. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Transport/\` - all pass
- [ ] Verify adapter used by SolanaDriver
- [ ] Verify unit tests for HTTP error mapping pass
- [ ] Test with MockHandler (no real network calls)
- [ ] Check PSR-12 compliance

## Acceptance Criteria
- [x] \`HttpClientAdapter\` interface defined
- [x] \`GuzzleAdapter\` implementing interface with error handling
- [x] Centralized HTTP configuration (timeout, headers, SSL)
- [x] Exception mapping (Guzzle exceptions → blockchain exceptions)
- [x] SolanaDriver refactored to use GuzzleAdapter
- [x] Unit tests with MockHandler demonstrate error handling
- [x] Integration tests verify cross-driver compatibility
- [x] README.md updated with adapter documentation

## Files Created
- \`src/Transport/HttpClientAdapter.php\` (interface)
- \`src/Transport/GuzzleAdapter.php\` (implementation)
- \`tests/Transport/GuzzleAdapterTest.php\`
- \`tests/Integration/GuzzleAdapterIntegrationTest.php\`

## Files Modified
- \`src/Drivers/SolanaDriver.php\` (use GuzzleAdapter)
- \`tests/SolanaDriverTest.php\` (update for adapter)
- \`README.md\` (add adapter documentation)

## Dependencies
- Requires: TASK-004 (Exception classes) completed
- Requires: TASK-005 (HttpClientAdapter skeleton) completed
- Requires: \`guzzlehttp/guzzle\` package

## Related
- Plan: \`plan/feature-core-utilities-1.md\` (TASK-008)
" || echo "⚠ Issue 8 may already exist"

echo ""
echo "✅ All GitHub issues created successfully for Core Utilities!"
echo ""
echo "📋 Implementation Summary:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Phase 1 (Foundation - 5 issues):"
echo "  1. TASK-001: BlockchainDriverInterface"
echo "  2. TASK-002: BlockchainManager"
echo "  3. TASK-003: DriverRegistry"
echo "  4. TASK-004: Exception Classes"
echo "  5. TASK-005: Utilities Skeleton"
echo ""
echo "Phase 2 (Hardening - 3 issues):"
echo "  6. TASK-006: ConfigLoader"
echo "  7. TASK-007: CachePool"
echo "  8. TASK-008: GuzzleAdapter"
echo ""
echo "📊 Statistics:"
echo "  • Total Issues: 8"
echo "  • Milestone: $MILESTONE"
echo "  • Epic: Core Utilities"
echo ""
echo "🔗 View issues at: https://github.com/$REPO/issues?q=milestone%3A%22$(echo "$MILESTONE" | sed 's/ /%20/g')%22"
echo ""
