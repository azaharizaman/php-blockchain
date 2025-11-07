#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Ethereum Driver Implementation
# Usage: ./create-issues-ethereum-driver.sh [REPO]
# Example: ./create-issues-ethereum-driver.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Ethereum Driver"

echo "Creating GitHub issues for Ethereum Driver Implementation..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement Ethereum (EVM) driver with JSON-RPC support, ABI helpers, and ERC-20 token support" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "drivers"
    "ethereum"
    "evm"
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

# Issue 1: EthereumDriver Skeleton
echo "Creating Issue 1: EthereumDriver Skeleton..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Create EthereumDriver class implementing BlockchainDriverInterface" \
    --milestone "$MILESTONE" \
    --label "feature,drivers,ethereum,phase-1" \
    --body "## Overview
Create the main Ethereum driver class that implements the BlockchainDriverInterface and provides JSON-RPC communication with Ethereum nodes.

## Requirements
- **REQ-001**: Implement \`src/Drivers/EthereumDriver.php\` that implements \`BlockchainDriverInterface\`
- **REQ-002**: Connect to JSON-RPC endpoints (Infura/Alchemy/self-hosted)
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Create EthereumDriver Class File
- [ ] Create file \`src/Drivers/EthereumDriver.php\`
- [ ] Add namespace: \`namespace Blockchain\Drivers;\`
- [ ] Add strict types declaration
- [ ] Implement \`BlockchainDriverInterface\`

### 2. Properties
- [ ] Add private \`?GuzzleAdapter \$httpClient\` property
- [ ] Add private \`?CachePool \$cache\` property
- [ ] Add private \`string \$endpoint\` property (RPC URL)
- [ ] Add private \`?string \$chainId\` property
- [ ] Add phpdoc annotations for all properties

### 3. Constructor
- [ ] Accept optional \`GuzzleAdapter \$httpClient = null\`
- [ ] Accept optional \`CachePool \$cache = null\`
- [ ] Initialize with defaults if not provided
- [ ] Add phpdoc with \`@param\` annotations

### 4. Connect Method
- [ ] Implement \`connect(array \$config): void\`
- [ ] Validate \`endpoint\` key exists in config
- [ ] Store endpoint URL
- [ ] Optionally validate connection with \`eth_chainId\` call
- [ ] Add phpdoc: \`@param array<string,mixed> \$config\`
- [ ] Throw \`ConfigurationException\` if endpoint missing

### 5. JSON-RPC Helper
- [ ] Implement private \`rpcCall(string \$method, array \$params = []): mixed\`
- [ ] Build JSON-RPC payload: {\"jsonrpc\": \"2.0\", \"method\": \$method, \"params\": \$params, \"id\": 1}
- [ ] Use \`\$this->httpClient->post(\$this->endpoint, \$payload)\`
- [ ] Check for RPC error in response
- [ ] Return \`result\` field from response
- [ ] Add phpdoc: \`@param array<int,mixed> \$params\`

### 6. Get Balance Method
- [ ] Implement \`getBalance(string \$address): float\`
- [ ] Call \`eth_getBalance\` with address and \"latest\" block
- [ ] Convert hex wei to float ETH (divide by 1e18)
- [ ] Use cache if available
- [ ] Add phpdoc and return type

### 7. Get Transaction Method
- [ ] Implement \`getTransaction(string \$hash): array\`
- [ ] Call \`eth_getTransactionByHash\` with transaction hash
- [ ] Return normalized transaction array
- [ ] Add phpdoc: \`@return array<string,mixed>\`

### 8. Get Block Method
- [ ] Implement \`getBlock(int|string \$blockIdentifier): array\`
- [ ] Call \`eth_getBlockByNumber\` or \`eth_getBlockByHash\`
- [ ] Handle both numeric and hash identifiers
- [ ] Return normalized block array
- [ ] Add phpdoc: \`@return array<string,mixed>\`

### 9. Send Transaction (Placeholder)
- [ ] Implement \`sendTransaction(...): string\`
- [ ] Throw \`TransactionException\` with message \"Raw transaction signing not yet implemented\"
- [ ] Add TODO comment for Phase 2 implementation
- [ ] Add phpdoc with all parameters

### 10. Estimate Gas (Stub)
- [ ] Implement \`estimateGas(...): ?int\`
- [ ] Return null for now (will implement in TASK-004)
- [ ] Add TODO comment
- [ ] Add phpdoc

### 11. Get Token Balance (Stub)
- [ ] Implement \`getTokenBalance(...): ?float\`
- [ ] Return null for now (will implement in TASK-005)
- [ ] Add TODO comment
- [ ] Add phpdoc

### 12. Get Network Info
- [ ] Implement \`getNetworkInfo(): ?array\`
- [ ] Call \`eth_chainId\` for chain ID
- [ ] Call \`eth_gasPrice\` for current gas price
- [ ] Call \`eth_blockNumber\` for latest block
- [ ] Return array with network info
- [ ] Add phpdoc: \`@return array<string,mixed>|null\`

### 13. Helper Methods
- [ ] Add \`private function weiToEth(string \$wei): float\` - convert hex wei to ETH
- [ ] Add \`private function hexToInt(string \$hex): int\` - convert hex to integer
- [ ] Add \`private function validateAddress(string \$address): bool\` - validate Ethereum address format

### 14. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Verify all methods have proper phpdoc
- [ ] Check PSR-12 compliance
- [ ] Verify file is autoloadable

## Acceptance Criteria
- [x] EthereumDriver class implements BlockchainDriverInterface
- [x] Connect method validates and stores RPC endpoint
- [x] JSON-RPC helper method for all calls
- [x] getBalance, getTransaction, getBlock implemented with mocked RPC
- [x] sendTransaction throws not-implemented exception
- [x] PHPStan reports no typing errors
- [x] All array parameters use typed phpdoc

## Files Created
- \`src/Drivers/EthereumDriver.php\`

## Dependencies
- Requires: Core Utilities milestone completed (BlockchainDriverInterface, GuzzleAdapter, CachePool)

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-001)
- PRD: \`docs/prd/02-ETHEREUM-DRIVER-EPIC.md\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: ABI Helpers
echo "Creating Issue 2: ABI Helpers..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Add ABI encoding/decoding helpers" \
    --milestone "$MILESTONE" \
    --label "feature,ethereum,utilities,phase-1" \
    --body "## Overview
Implement ABI (Application Binary Interface) encoding and decoding helpers for interacting with Ethereum smart contracts.

## Requirements
- **REQ-004**: Provide ABI encode/decode helpers
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Create Abi Utility Class
- [ ] Create file \`src/Utils/Abi.php\`
- [ ] Add namespace: \`namespace Blockchain\Utils;\`
- [ ] Add strict types declaration
- [ ] Declare class: \`class Abi\`

### 2. Function Selector Generation
- [ ] Implement \`public static function getFunctionSelector(string \$signature): string\`
- [ ] Take signature like \"balanceOf(address)\"
- [ ] Calculate keccak256 hash of signature
- [ ] Return first 4 bytes (8 hex chars) as \"0x...\"
- [ ] Use \`hash('sha3-256', \$signature)\` (PHP 7.1+ keccak)

### 3. Encode Function Call
- [ ] Implement \`public static function encodeFunctionCall(string \$signature, array \$params): string\`
- [ ] Get function selector
- [ ] Encode parameters based on types in signature
- [ ] Support basic types: address, uint256, bool, string
- [ ] Return hex string: selector + encoded params
- [ ] Add phpdoc: \`@param array<int,mixed> \$params\`

### 4. Parameter Encoding
- [ ] Implement \`private static function encodeParameter(string \$type, mixed \$value): string\`
- [ ] Handle address type: pad to 32 bytes
- [ ] Handle uint256: convert to hex, pad to 32 bytes
- [ ] Handle bool: encode as uint256 (0 or 1)
- [ ] Handle string: encode length + padded string
- [ ] Return 32-byte hex string per parameter

### 5. Decode Response
- [ ] Implement \`public static function decodeResponse(string \$returnType, string \$data): mixed\`
- [ ] Support return types: uint256, address, bool, string
- [ ] Parse hex data according to type
- [ ] Return decoded value in appropriate PHP type
- [ ] Handle \"0x\" prefix in data

### 6. Decode uint256
- [ ] Implement \`private static function decodeUint256(string \$hex): string\`
- [ ] Remove \"0x\" prefix if present
- [ ] Convert hex to decimal string (preserve precision)
- [ ] Use \`gmp_strval(gmp_init(\$hex, 16), 10)\` for large numbers

### 7. Decode Address
- [ ] Implement \`private static function decodeAddress(string \$hex): string\`
- [ ] Remove padding (first 24 chars after 0x)
- [ ] Return \"0x\" + last 40 hex chars
- [ ] Validate checksum (optional)

### 8. ERC-20 Helper Methods
- [ ] Add \`public static function encodeBalanceOf(string \$address): string\`
- [ ] Convenience method: \`encodeFunctionCall('balanceOf(address)', [\$address])\`
- [ ] Add \`public static function encodeTransfer(string \$to, string \$amount): string\`
- [ ] Convenience method: \`encodeFunctionCall('transfer(address,uint256)', [\$to, \$amount])\`

### 9. Unit Tests - Function Selector
- [ ] Create \`tests/Utils/AbiTest.php\`
- [ ] Test \`getFunctionSelector('balanceOf(address)')\` returns \"0x70a08231\"
- [ ] Test \`getFunctionSelector('transfer(address,uint256)')\` returns \"0xa9059cbb\"
- [ ] Test known function selectors from ERC-20/721

### 10. Unit Tests - Encoding
- [ ] Test \`encodeParameter('uint256', '1000')\` produces correct 32-byte hex
- [ ] Test \`encodeParameter('address', '0x...')\` produces padded address
- [ ] Test \`encodeFunctionCall('balanceOf(address)', ['0x123...'])\`
- [ ] Verify encoded data matches expected hex strings

### 11. Unit Tests - Decoding
- [ ] Test \`decodeResponse('uint256', '0x000...03e8')\` returns \"1000\"
- [ ] Test \`decodeResponse('address', '0x000...abc')\` returns \"0x...abc\"
- [ ] Test \`decodeResponse('bool', '0x000...001')\` returns true
- [ ] Use known test vectors from Ethereum docs

### 12. Documentation
- [ ] Add comprehensive docblocks to all public methods
- [ ] Add usage examples in method docblocks
- [ ] Document supported types and limitations
- [ ] Note: full ABI support (arrays, structs) is Phase 3

### 13. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Utils/AbiTest.php\` - all pass
- [ ] Verify unit tests with known ERC-20 balanceOf vectors pass
- [ ] Check PSR-12 compliance

## Acceptance Criteria
- [x] Abi class with encode/decode methods
- [x] Support for basic types: address, uint256, bool, string
- [x] ERC-20 helper methods (balanceOf, transfer encoding)
- [x] Unit tests with known test vectors pass
- [x] Function selector generation matches expected values
- [x] PHPStan reports no errors

## Files Created
- \`src/Utils/Abi.php\`
- \`tests/Utils/AbiTest.php\`

## Dependencies
- Requires: Core Utilities (AddressValidator, Serializer)

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-002)
" || echo "⚠ Issue 2 may already exist"

# Issue 3: EthereumDriver Unit Tests
echo "Creating Issue 3: EthereumDriver Unit Tests..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Add comprehensive unit tests for EthereumDriver" \
    --milestone "$MILESTONE" \
    --label "feature,drivers,ethereum,testing,phase-1" \
    --body "## Overview
Create comprehensive unit tests for EthereumDriver using Guzzle MockHandler to avoid external network calls.

## Requirements
- **GUD-001**: Use Guzzle MockHandler for unit tests
- **CON-001**: No external network calls during unit tests

## Implementation Checklist

### 1. Create Test File
- [ ] Create \`tests/Drivers/EthereumDriverTest.php\`
- [ ] Add namespace: \`namespace Tests\Drivers;\`
- [ ] Extend \`PHPUnit\Framework\TestCase\`
- [ ] Import required classes (EthereumDriver, MockHandler, etc.)

### 2. Test Setup
- [ ] Add \`setUp()\` method
- [ ] Create MockHandler instance
- [ ] Create mock GuzzleAdapter with MockHandler
- [ ] Initialize EthereumDriver with mock adapter
- [ ] Call \`connect()\` with test config

### 3. Test Connect Method
- [ ] Test successful connection with valid endpoint
- [ ] Test connection throws ConfigurationException without endpoint
- [ ] Test connection with various endpoint formats (http, https, wss)
- [ ] Verify endpoint is stored correctly

### 4. Test Get Balance
- [ ] Mock JSON-RPC response for \`eth_getBalance\`
- [ ] Test with response: {\"result\": \"0xde0b6b3a7640000\"} (1 ETH)
- [ ] Verify \`getBalance('0x123...')\` returns 1.0
- [ ] Test with zero balance
- [ ] Test with large balance (precision check)
- [ ] Test error handling for invalid address

### 5. Test Get Transaction
- [ ] Mock JSON-RPC response for \`eth_getTransactionByHash\`
- [ ] Create sample transaction response with all fields
- [ ] Verify \`getTransaction('0xabc...')\` returns correct array
- [ ] Test fields: hash, from, to, value, gas, gasPrice, nonce, blockNumber
- [ ] Test with non-existent transaction (null response)

### 6. Test Get Block
- [ ] Mock JSON-RPC response for \`eth_getBlockByNumber\`
- [ ] Test with block number: \`getBlock(12345)\`
- [ ] Test with block hash: \`getBlock('0xdef...')\`
- [ ] Test with \"latest\" tag
- [ ] Verify block fields: number, hash, timestamp, transactions
- [ ] Test with non-existent block

### 7. Test Send Transaction (Not Implemented)
- [ ] Test \`sendTransaction(...)\` throws TransactionException
- [ ] Verify exception message mentions \"not yet implemented\"
- [ ] Test with various parameters

### 8. Test Get Network Info
- [ ] Mock multiple RPC calls: \`eth_chainId\`, \`eth_gasPrice\`, \`eth_blockNumber\`
- [ ] Test \`getNetworkInfo()\` returns array with all info
- [ ] Verify chainId conversion (hex to int)
- [ ] Verify gasPrice in gwei
- [ ] Test error handling

### 9. Test RPC Error Handling
- [ ] Mock RPC error response: {\"error\": {\"code\": -32000, \"message\": \"error\"}}
- [ ] Test that driver throws appropriate exception
- [ ] Test different error codes
- [ ] Verify error message is preserved

### 10. Test Helper Methods (if public or via reflection)
- [ ] Test \`weiToEth()\` conversion accuracy
  - [ ] Test \"0xde0b6b3a7640000\" -> 1.0
  - [ ] Test \"0x0\" -> 0.0
  - [ ] Test large values maintain precision
- [ ] Test \`hexToInt()\` conversion
  - [ ] Test \"0x10\" -> 16
  - [ ] Test \"0x0\" -> 0
- [ ] Test \`validateAddress()\` format check
  - [ ] Test valid address returns true
  - [ ] Test invalid format returns false

### 11. Test Caching (if integrated)
- [ ] Set up driver with mock cache
- [ ] Call \`getBalance()\` twice with same address
- [ ] Verify HTTP client called only once (cache hit)
- [ ] Test cache TTL expiration

### 12. Test Multiple Sequential Calls
- [ ] Queue multiple mock responses in MockHandler
- [ ] Make multiple driver method calls
- [ ] Verify all responses handled correctly
- [ ] Verify request order is preserved

### 13. Validation
- [ ] Run \`composer test\` - all Ethereum tests pass
- [ ] Run \`vendor/bin/phpunit tests/Drivers/EthereumDriverTest.php\` specifically
- [ ] Verify no real network calls made (check MockHandler usage)
- [ ] Check test coverage for EthereumDriver (aim for 90%+)

## Acceptance Criteria
- [x] Comprehensive unit tests using MockHandler (no network calls)
- [x] Tests cover: connect, getBalance, getTransaction, getBlock, getNetworkInfo
- [x] Test sendTransaction throws not-implemented exception
- [x] Error handling tests for RPC errors
- [x] Helper method tests for conversions
- [x] All tests pass with \`composer test\`
- [x] Test coverage 90%+ for EthereumDriver

## Files Created
- \`tests/Drivers/EthereumDriverTest.php\`

## Dependencies
- Requires: TASK-001 (EthereumDriver) completed
- Requires: Core Utilities completed (GuzzleAdapter with MockHandler support)

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-003)
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Estimate Gas Implementation
echo "Creating Issue 4: Estimate Gas Implementation..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Implement estimateGas with fallback heuristics" \
    --milestone "$MILESTONE" \
    --label "feature,drivers,ethereum,phase-2" \
    --body "## Overview
Implement gas estimation for Ethereum transactions using eth_estimateGas JSON-RPC method with fallback heuristics.

## Requirements
- **REQ-003**: Support gas estimation for transactions
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Update EthereumDriver
- [ ] Locate \`estimateGas()\` method in \`src/Drivers/EthereumDriver.php\`
- [ ] Remove TODO comment and null return
- [ ] Implement full gas estimation logic

### 2. Implement eth_estimateGas Call
- [ ] Build transaction object from parameters
  - [ ] \`from\`: sender address
  - [ ] \`to\`: recipient address  
  - [ ] \`value\`: amount in wei (convert from ETH)
  - [ ] \`data\`: contract call data (from options)
- [ ] Call \`eth_estimateGas\` with transaction object
- [ ] Parse hex result to integer
- [ ] Add safety buffer (multiply by 1.2)

### 3. Fallback Heuristics
- [ ] Implement \`private function estimateGasFallback(...): int\`
- [ ] Check transaction type:
  - [ ] Simple ETH transfer: 21,000 gas
  - [ ] ERC-20 transfer: 65,000 gas
  - [ ] Contract interaction: 100,000+ gas (based on data size)
- [ ] Return conservative estimate

### 4. Error Handling
- [ ] Catch RPC errors from \`eth_estimateGas\`
- [ ] If RPC fails, use fallback heuristics
- [ ] Log warning when fallback is used
- [ ] Return fallback estimate instead of null

### 5. Convert ETH to Wei
- [ ] Implement \`private function ethToWei(float \$eth): string\`
- [ ] Multiply by 1e18
- [ ] Return as hex string with \"0x\" prefix
- [ ] Use BCMath or GMP for precision

### 6. Unit Tests - Basic Estimation
- [ ] Update \`tests/Drivers/EthereumDriverTest.php\`
- [ ] Add \`testEstimateGasSimpleTransfer()\`
- [ ] Mock \`eth_estimateGas\` response: {\"result\": \"0x5208\"} (21,000)
- [ ] Verify \`estimateGas()\` returns approximately 25,200 (with buffer)

### 7. Unit Tests - Contract Call
- [ ] Test \`estimateGas()\` with contract call data
- [ ] Mock higher gas estimate: {\"result\": \"0xea60\"} (60,000)
- [ ] Verify returned value includes safety buffer

### 8. Unit Tests - Fallback
- [ ] Mock RPC error response
- [ ] Verify fallback heuristics used
- [ ] Test simple transfer fallback returns 21,000
- [ ] Test ERC-20 transfer fallback (check data field)

### 9. Unit Tests - Edge Cases
- [ ] Test with zero amount
- [ ] Test with very large amount
- [ ] Test with missing 'to' address (contract creation)
- [ ] Test with options containing gas limit

### 10. Documentation
- [ ] Add comprehensive docblock to \`estimateGas()\`
- [ ] Document safety buffer calculation
- [ ] Document fallback scenarios
- [ ] Add usage examples

### 11. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Drivers/EthereumDriverTest.php\` - all pass
- [ ] Verify unit tests for estimateGas with mock RPC responses pass
- [ ] Test coverage includes all fallback paths

## Acceptance Criteria
- [x] \`estimateGas()\` calls \`eth_estimateGas\` JSON-RPC
- [x] Safety buffer applied to estimates (1.2x)
- [x] Fallback heuristics for RPC failures
- [x] Unit tests with mock RPC responses pass
- [x] Test coverage for success and fallback cases
- [x] PHPStan reports no errors

## Files Modified
- \`src/Drivers/EthereumDriver.php\` (implement estimateGas)
- \`tests/Drivers/EthereumDriverTest.php\` (add gas tests)

## Dependencies
- Requires: TASK-001 (EthereumDriver skeleton) completed
- Requires: TASK-003 (Unit tests) in place

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-004)
" || echo "⚠ Issue 4 may already exist"

# Issue 5: ERC-20 Token Balance
echo "Creating Issue 5: ERC-20 Token Balance..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-005: Implement getTokenBalance for ERC-20 tokens" \
    --milestone "$MILESTONE" \
    --label "feature,drivers,ethereum,erc-20,phase-2" \
    --body "## Overview
Implement ERC-20 token balance retrieval using eth_call and ABI encoding/decoding.

## Requirements
- **REQ-003**: Support ERC-20 token balances
- **REQ-004**: Use ABI helpers for contract calls
- **CON-001**: PHP 8.2+, PHPStan level 7

## Implementation Checklist

### 1. Update EthereumDriver
- [ ] Locate \`getTokenBalance()\` method in \`src/Drivers/EthereumDriver.php\`
- [ ] Remove TODO comment and null return
- [ ] Implement full ERC-20 balance query

### 2. Encode balanceOf Call
- [ ] Use \`Abi::encodeBalanceOf(\$address)\` to encode function call
- [ ] Or use \`Abi::encodeFunctionCall('balanceOf(address)', [\$address])\`
- [ ] Result is data field for eth_call

### 3. Build eth_call Transaction Object
- [ ] Create transaction object:
  - [ ] \`to\`: token contract address (\$tokenAddress parameter)
  - [ ] \`data\`: encoded balanceOf call
- [ ] No \`from\`, \`value\`, or \`gas\` needed for read calls

### 4. Execute eth_call
- [ ] Call \`eth_call\` with transaction object and \"latest\" block
- [ ] Use \`rpcCall('eth_call', [\$transaction, 'latest'])\`
- [ ] Get hex result from response

### 5. Decode Response
- [ ] Use \`Abi::decodeResponse('uint256', \$result)\`
- [ ] Result is token balance in smallest unit (like wei)
- [ ] Convert to float by dividing by token decimals

### 6. Get Token Decimals
- [ ] Implement \`private function getTokenDecimals(string \$tokenAddress): int\`
- [ ] Call \`decimals()\` function on token contract
- [ ] Encode: \`Abi::encodeFunctionCall('decimals()', [])\`
- [ ] Execute eth_call and decode uint8 result
- [ ] Cache decimals per token address (won't change)
- [ ] Default to 18 if call fails

### 7. Convert Balance
- [ ] Implement \`private function convertTokenBalance(string \$balance, int \$decimals): float\`
- [ ] Use BCMath for precision: \`bcdiv(\$balance, bcpow('10', (string)\$decimals, 0), \$decimals)\`
- [ ] Return as float

### 8. Error Handling
- [ ] Handle invalid token address (contract doesn't exist)
- [ ] Handle non-ERC-20 contracts (missing balanceOf function)
- [ ] Return null if token balance cannot be determined
- [ ] Log warnings for invalid contracts

### 9. Caching Integration
- [ ] Generate cache key from token address and wallet address
- [ ] Check cache before making RPC call
- [ ] Cache successful balance results with TTL
- [ ] Cache token decimals separately (long TTL)

### 10. Unit Tests - Basic Token Balance
- [ ] Update \`tests/Drivers/EthereumDriverTest.php\`
- [ ] Add \`testGetTokenBalance()\`
- [ ] Mock \`eth_call\` for balanceOf: return \"0x0000...03e8\" (1000)
- [ ] Mock \`eth_call\` for decimals: return \"0x12\" (18)
- [ ] Verify balance converted correctly (1000 / 1e18)

### 11. Unit Tests - Different Decimals
- [ ] Test token with 6 decimals (USDC)
- [ ] Mock decimals() returns 6
- [ ] Verify balance conversion uses correct decimals
- [ ] Test token with 0 decimals

### 12. Unit Tests - Error Cases
- [ ] Test with invalid token address (eth_call fails)
- [ ] Verify returns null
- [ ] Test with non-ERC-20 contract (no balanceOf)
- [ ] Test with zero balance

### 13. Integration with Abi Helper
- [ ] Verify \`Abi::encodeBalanceOf()\` is used correctly
- [ ] Verify \`Abi::decodeResponse('uint256', ...)\` works
- [ ] Test end-to-end encoding -> RPC -> decoding flow

### 14. Documentation
- [ ] Add comprehensive docblock to \`getTokenBalance()\`
- [ ] Document token address format (ERC-20 contract)
- [ ] Document decimal handling
- [ ] Provide usage examples with popular tokens (USDT, USDC, DAI)

### 15. Validation
- [ ] Run \`composer run phpstan\` - no errors
- [ ] Run \`vendor/bin/phpunit tests/Drivers/EthereumDriverTest.php\` - all pass
- [ ] Verify unit tests for ERC-20 balanceOf with mock responses pass
- [ ] Test with different decimal values (6, 8, 18)

## Acceptance Criteria
- [x] \`getTokenBalance()\` calls \`eth_call\` with encoded balanceOf
- [x] Decodes uint256 response via Abi helper
- [x] Retrieves and caches token decimals
- [x] Converts balance to float with correct decimals
- [x] Unit tests with mock ERC-20 responses pass
- [x] Handles error cases (invalid contract, null balance)
- [x] PHPStan reports no errors

## Files Modified
- \`src/Drivers/EthereumDriver.php\` (implement getTokenBalance)
- \`tests/Drivers/EthereumDriverTest.php\` (add ERC-20 tests)

## Dependencies
- Requires: TASK-002 (Abi helpers) completed
- Requires: TASK-001 (EthereumDriver skeleton) completed

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-005)
" || echo "⚠ Issue 5 may already exist"

# Issue 6: Ethereum Driver Documentation
echo "Creating Issue 6: Ethereum Driver Documentation..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-006: Add comprehensive Ethereum driver documentation" \
    --milestone "$MILESTONE" \
    --label "documentation,ethereum,phase-2" \
    --body "## Overview
Create comprehensive documentation for the Ethereum driver including usage examples, configuration schema, and best practices.

## Requirements
- **REQ-002**: Document all configuration options
- **REQ-003**: Provide usage examples for all features

## Implementation Checklist

### 1. Create Documentation File
- [ ] Create directory \`docs/drivers/\` if not exists
- [ ] Create file \`docs/drivers/ethereum.md\`
- [ ] Add frontmatter with title and description

### 2. Overview Section
- [ ] Write introduction to Ethereum driver
- [ ] List supported networks (mainnet, testnet, local)
- [ ] List supported RPC providers (Infura, Alchemy, QuickNode, self-hosted)
- [ ] List key features (ETH balance, ERC-20, gas estimation)

### 3. Installation Section
- [ ] Document Composer installation (already included in main package)
- [ ] List dependencies (guzzlehttp/guzzle)
- [ ] Note PHP version requirements (8.2+)

### 4. Configuration Section
- [ ] Document configuration schema:
  - [ ] \`endpoint\` (required): RPC URL
  - [ ] \`chainId\` (optional): Network chain ID
  - [ ] \`timeout\` (optional): HTTP timeout in seconds
- [ ] Provide example configs for:
  - [ ] Ethereum mainnet (Infura)
  - [ ] Ethereum mainnet (Alchemy)
  - [ ] Sepolia testnet
  - [ ] Local node (http://localhost:8545)
- [ ] Document environment variable usage

### 5. Basic Usage Examples
- [ ] Example: Initialize driver
- [ ] Example: Connect to network
- [ ] Example: Get ETH balance
- [ ] Example: Get transaction details
- [ ] Example: Get block information
- [ ] Example: Get network info (chain ID, gas price)
- [ ] Include complete, runnable code examples

### 6. ERC-20 Token Examples
- [ ] Example: Get token balance
- [ ] Example: Check multiple token balances
- [ ] Example: Handle tokens with different decimals
- [ ] List common token contract addresses (USDT, USDC, DAI)

### 7. Gas Estimation Examples
- [ ] Example: Estimate gas for simple transfer
- [ ] Example: Estimate gas for token transfer
- [ ] Example: Estimate gas for contract interaction
- [ ] Document safety buffer calculation

### 8. Error Handling Section
- [ ] Document common exceptions and how to handle them
- [ ] ConfigurationException examples
- [ ] TransactionException examples
- [ ] RPC error codes and meanings
- [ ] Network timeout handling

### 9. Advanced Usage
- [ ] Using custom HTTP client configuration
- [ ] Caching configuration for performance
- [ ] Batch requests (future feature)
- [ ] WebSocket support (future feature)

### 10. Best Practices
- [ ] Use environment variables for API keys
- [ ] Implement rate limiting for public RPC endpoints
- [ ] Cache token decimals and rarely-changing data
- [ ] Handle network errors gracefully
- [ ] Validate addresses before making calls

### 11. Supported Networks Table
- [ ] Create table with:
  - [ ] Network name
  - [ ] Chain ID
  - [ ] RPC endpoint example
  - [ ] Block explorer
- [ ] Include: Mainnet, Sepolia, Goerli, Polygon, BSC, Arbitrum, Optimism

### 12. API Reference
- [ ] Link to BlockchainDriverInterface documentation
- [ ] List all public methods with signatures
- [ ] Document parameters and return types
- [ ] Note any Ethereum-specific behaviors

### 13. Troubleshooting Section
- [ ] Common issues and solutions
- [ ] \"Invalid API key\" -> check endpoint config
- [ ] \"Timeout\" -> increase timeout setting
- [ ] \"Invalid address format\" -> use checksummed addresses
- [ ] \"Gas estimation failed\" -> check transaction validity

### 14. Create Check Script
- [ ] Create \`scripts/check-driver-docs.php\` (if not exists)
- [ ] Script verifies all driver docs exist
- [ ] Script validates markdown syntax
- [ ] Script checks for required sections
- [ ] Return exit code 0 on success

### 15. Validation
- [ ] Run \`php scripts/check-driver-docs.php\` - returns success
- [ ] Verify all code examples are syntactically correct
- [ ] Test examples with actual driver (optional)
- [ ] Check markdown rendering in GitHub
- [ ] Verify all links work

## Acceptance Criteria
- [x] Documentation file \`docs/drivers/ethereum.md\` created
- [x] Complete configuration schema documented
- [x] Usage examples for all major features
- [x] ERC-20 token examples included
- [x] Error handling documented
- [x] Best practices section
- [x] Supported networks table
- [x] \`php scripts/check-driver-docs.php\` returns success
- [x] All code examples are runnable

## Files Created
- \`docs/drivers/ethereum.md\`
- \`scripts/check-driver-docs.php\` (if not exists)

## Files Modified
- \`README.md\` (add link to Ethereum driver docs)

## Dependencies
- Requires: TASK-001 through TASK-005 completed (all features implemented)

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-006)
" || echo "⚠ Issue 6 may already exist"

# Issue 7: Integration Tests
echo "Creating Issue 7: Integration Tests..."
gh issue create \
    --repo "$REPO" \
    --title "TASK-007: Add optional integration tests with real Ethereum nodes" \
    --milestone "$MILESTONE" \
    --label "testing,ethereum,integration,phase-2" \
    --body "## Overview
Add optional integration tests that connect to real Ethereum test networks, gated by RUN_INTEGRATION_TESTS environment variable.

## Requirements
- **CON-001**: Integration tests gated by \`RUN_INTEGRATION_TESTS\` secret
- **TEST-003**: Optional integration tests with real networks

## Implementation Checklist

### 1. Create Integration Test File
- [ ] Create directory \`tests/Integration/\` if not exists
- [ ] Create file \`tests/Integration/EthereumIntegrationTest.php\`
- [ ] Add namespace: \`namespace Tests\Integration;\`
- [ ] Extend \`PHPUnit\Framework\TestCase\`

### 2. Test Setup
- [ ] Add \`setUp()\` method
- [ ] Check \`RUN_INTEGRATION_TESTS\` environment variable
- [ ] Skip tests if not set: \`\$this->markTestSkipped()\`
- [ ] Load RPC endpoint from environment (\`ETHEREUM_RPC_ENDPOINT\`)
- [ ] Initialize real EthereumDriver (no mocks)
- [ ] Connect to test network (Sepolia)

### 3. Test Real Connection
- [ ] Test \`testConnectToTestnet()\`
- [ ] Connect to Sepolia testnet
- [ ] Verify no exceptions thrown
- [ ] Test \`getNetworkInfo()\` returns valid chain ID (11155111 for Sepolia)

### 4. Test Real Balance Retrieval
- [ ] Test \`testGetBalanceFromTestnet()\`
- [ ] Use known test address with balance
- [ ] Call \`getBalance()\` on real network
- [ ] Verify returns numeric value >= 0
- [ ] Verify no exceptions thrown

### 5. Test Real Transaction Retrieval
- [ ] Test \`testGetTransactionFromTestnet()\`
- [ ] Use known transaction hash from Sepolia
- [ ] Call \`getTransaction()\`
- [ ] Verify transaction details returned
- [ ] Validate transaction structure (hash, from, to, value)

### 6. Test Real Block Retrieval
- [ ] Test \`testGetBlockFromTestnet()\`
- [ ] Query recent block number
- [ ] Call \`getBlock()\` with block number
- [ ] Verify block data returned
- [ ] Validate block structure (number, hash, timestamp)

### 7. Test Real Gas Estimation
- [ ] Test \`testEstimateGasOnTestnet()\`
- [ ] Estimate gas for simple ETH transfer
- [ ] Verify returns reasonable value (> 21000, < 100000)
- [ ] Test with different transaction types

### 8. Test Real ERC-20 Balance
- [ ] Test \`testGetTokenBalanceFromTestnet()\`
- [ ] Use known ERC-20 token on Sepolia (e.g., test USDC)
- [ ] Query token balance for known address
- [ ] Verify returns numeric value
- [ ] Test token decimals retrieval

### 9. Test Error Handling
- [ ] Test with invalid address format
- [ ] Test with non-existent transaction hash
- [ ] Test with invalid block number
- [ ] Verify appropriate exceptions thrown
- [ ] Test network timeout handling

### 10. Test Rate Limiting
- [ ] Make multiple rapid requests
- [ ] Verify driver handles rate limits gracefully
- [ ] Test retry logic if implemented
- [ ] Document rate limit considerations

### 11. CI Configuration
- [ ] Update \`.github/workflows/tests.yml\` (or create)
- [ ] Add integration test job (separate from unit tests)
- [ ] Set \`RUN_INTEGRATION_TESTS=true\` in CI
- [ ] Store \`ETHEREUM_RPC_ENDPOINT\` as GitHub secret
- [ ] Configure to run on: push to main, PRs, manual trigger

### 12. Local Testing Instructions
- [ ] Add \`TESTING.md\` document
- [ ] Document how to run integration tests locally
- [ ] Document required environment variables
- [ ] Provide example .env file
- [ ] Document how to get free Sepolia RPC endpoint (Infura, Alchemy)

### 13. Test Data Documentation
- [ ] Document test addresses used
- [ ] Document test transaction hashes
- [ ] Document test token contracts
- [ ] Note: use public testnet data only

### 14. Validation
- [ ] Run integration tests locally: \`RUN_INTEGRATION_TESTS=true composer run integration-test\`
- [ ] Verify all integration tests pass with real network
- [ ] Verify tests are skipped without environment variable
- [ ] Test in CI pipeline
- [ ] Verify no secrets leaked in logs

### 15. Composer Script
- [ ] Add \`integration-test\` script to \`composer.json\`
- [ ] Script: \`phpunit tests/Integration/\`
- [ ] Document usage in README.md

## Acceptance Criteria
- [x] Integration test file created in \`tests/Integration/\`
- [x] Tests connect to real Sepolia testnet
- [x] Tests gated by \`RUN_INTEGRATION_TESTS\` environment variable
- [x] Tests cover: connection, balance, transaction, block, gas, ERC-20
- [x] CI configured to run integration tests
- [x] Integration tests pass when \`RUN_INTEGRATION_TESTS=true\`
- [x] Tests skipped by default (without env var)
- [x] Documentation for local testing added

## Files Created
- \`tests/Integration/EthereumIntegrationTest.php\`
- \`TESTING.md\` (or update existing)

## Files Modified
- \`.github/workflows/tests.yml\` (add integration test job)
- \`composer.json\` (add integration-test script)
- \`README.md\` (document integration testing)

## Dependencies
- Requires: All Phase 1 and Phase 2 tasks completed
- Requires: Access to Ethereum testnet RPC endpoint

## Related
- Plan: \`plan/feature-ethereum-driver-1.md\` (TASK-007)
" || echo "⚠ Issue 7 may already exist"

echo ""
echo "✅ All GitHub issues created successfully for Ethereum Driver!"
echo ""
echo "📋 Implementation Summary:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Phase 1 (Foundation - 3 issues):"
echo "  1. TASK-001: EthereumDriver Skeleton"
echo "  2. TASK-002: ABI Helpers"
echo "  3. TASK-003: EthereumDriver Unit Tests"
echo ""
echo "Phase 2 (Features - 4 issues):"
echo "  4. TASK-004: Estimate Gas Implementation"
echo "  5. TASK-005: ERC-20 Token Balance"
echo "  6. TASK-006: Driver Documentation"
echo "  7. TASK-007: Integration Tests"
echo ""
echo "📊 Statistics:"
echo "  • Total Issues: 7"
echo "  • Milestone: $MILESTONE"
echo "  • Epic: Ethereum Driver"
echo ""
echo "🔗 View issues at: https://github.com/$REPO/issues?q=milestone%3A%22$(echo "$MILESTONE" | sed 's/ /%20/g')%22"
echo ""
