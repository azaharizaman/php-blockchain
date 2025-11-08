# Testing Guide

This document provides instructions for running tests in the PHP Blockchain Integration Layer package.

## Table of Contents

- [Quick Start](#quick-start)
- [Unit Tests](#unit-tests)
- [Integration Tests](#integration-tests)
- [Running Tests in CI](#running-tests-in-ci)
- [Test Data](#test-data)

## Quick Start

### Install Dependencies

```bash
composer install
```

### Run All Unit Tests

```bash
composer test
```

## Unit Tests

Unit tests use mocked HTTP responses and do not require network access. They test the business logic and error handling of the blockchain drivers.

### Running Unit Tests

```bash
# Run all unit tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Drivers/EthereumDriverTest.php

# Run with verbose output
vendor/bin/phpunit --verbose
```

### Unit Test Coverage

Unit tests cover:
- Connection configuration and validation
- Balance retrieval with mocked responses
- Transaction queries with mocked data
- Block queries with mocked data
- Gas estimation logic
- Token balance calculations
- Error handling and exception scenarios
- Caching behavior

## Integration Tests

Integration tests connect to real blockchain test networks (e.g., Sepolia) to validate actual network interactions. These tests are **optional** and gated by environment variables.

### Prerequisites

To run integration tests, you need:

1. **Enable integration tests**: Set `RUN_INTEGRATION_TESTS=true`
2. **RPC Endpoint**: Set `ETHEREUM_RPC_ENDPOINT` to a valid Ethereum testnet RPC URL

### Getting Free RPC Endpoints

#### Option 1: Infura

1. Sign up at [infura.io](https://infura.io/)
2. Create a new project
3. Copy your Sepolia endpoint: `https://sepolia.infura.io/v3/YOUR_PROJECT_ID`

#### Option 2: Alchemy

1. Sign up at [alchemy.com](https://www.alchemy.com/)
2. Create a new app for Sepolia testnet
3. Copy your endpoint: `https://eth-sepolia.g.alchemy.com/v2/YOUR_API_KEY`

#### Option 3: Public Endpoints

Use public endpoints (note: these may have rate limits):
- Sepolia: `https://rpc.sepolia.org`
- Sepolia (alternative): `https://ethereum-sepolia.publicnode.com`

### Running Integration Tests Locally

#### Method 1: Environment Variables

```bash
# Set environment variables
export RUN_INTEGRATION_TESTS=true
export ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID

# Run integration tests
composer run integration-test
```

#### Method 2: Using .env File

Create a `.env` file in the project root (do not commit this file):

```env
RUN_INTEGRATION_TESTS=true
ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID
```

Then load it before running tests:

```bash
# Load .env file (using any method you prefer)
export $(cat .env | xargs)

# Run integration tests
composer run integration-test
```

#### Method 3: Inline

```bash
RUN_INTEGRATION_TESTS=true ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID composer run integration-test
```

### Running Specific Integration Tests

```bash
# Run only Ethereum integration tests
RUN_INTEGRATION_TESTS=true ETHEREUM_RPC_ENDPOINT=your_endpoint vendor/bin/phpunit tests/Integration/EthereumIntegrationTest.php

# Run a specific test method
RUN_INTEGRATION_TESTS=true ETHEREUM_RPC_ENDPOINT=your_endpoint vendor/bin/phpunit --filter testGetBalanceFromTestnet tests/Integration/EthereumIntegrationTest.php
```

### Integration Test Coverage

The Ethereum integration tests cover:

- **Connection Tests**
  - Connect to Sepolia testnet
  - Validate chain ID (11155111)
  - Test network info retrieval

- **Balance Tests**
  - Get balance from real addresses
  - Handle invalid addresses
  - Test zero balances

- **Transaction Tests**
  - Retrieve transaction details
  - Handle non-existent transactions
  - Validate transaction structure

- **Block Tests**
  - Get block by number
  - Get latest block
  - Handle invalid block numbers

- **Gas Estimation Tests**
  - Simple ETH transfers
  - ERC-20 token transfers
  - Contract interactions

- **Token Tests**
  - ERC-20 balance retrieval
  - Token decimals handling
  - Invalid token addresses

- **Error Handling Tests**
  - Invalid addresses
  - Network timeouts
  - Operations without connection
  - Rate limiting

### Test Behavior Without Environment Variables

If `RUN_INTEGRATION_TESTS` is not set or is not `true`, integration tests will be automatically skipped:

```bash
# These will skip integration tests
composer test
composer run integration-test
vendor/bin/phpunit tests/Integration/
```

Output will show:
```
S  // S = Skipped
Integration tests are disabled. Set RUN_INTEGRATION_TESTS=true to run these tests.
```

## Running Tests in CI

Integration tests are configured to run in GitHub Actions when the `RUN_INTEGRATION_TESTS` secret is set to `true`.

### CI Configuration

The `.github/workflows/agent-tasks.yml` file includes:

```yaml
- name: Run integration tests (optional)
  if: env.RUN_INTEGRATION_TESTS == 'true'
  run: |
    vendor/bin/phpunit --testsuite integration || (echo "Integration tests failed" && exit 1)
```

### Setting Up Secrets in GitHub

1. Go to your repository settings
2. Navigate to **Secrets and variables** → **Actions**
3. Add the following secrets:
   - `RUN_INTEGRATION_TESTS`: Set to `true`
   - `ETHEREUM_RPC_ENDPOINT`: Your Sepolia RPC endpoint

## Test Data

### Ethereum/Sepolia Test Data

The following addresses and identifiers are used in integration tests:

| Type | Value | Description |
|------|-------|-------------|
| Chain ID | `11155111` | Sepolia testnet chain ID |
| Test Address | `0x7cF69F7837F089EcBE73Aa93c5c1b7B9E0e5B565` | Ethereum Foundation address |
| Test TX Hash | `0x88df016429689c079f3b2f6ad39fa052532c56795b733da78a91ebe6a713944b` | Example transaction |
| Test Block | `4000000` | Example block number |
| Test Token | `0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238` | Test USDC contract |

**Note**: All test data uses public information from the Sepolia testnet. No private keys or sensitive data are included.

### Updating Test Data

If test data becomes outdated:

1. Find a recent transaction on [Sepolia Etherscan](https://sepolia.etherscan.io/)
2. Update the constants in `tests/Integration/EthereumIntegrationTest.php`
3. Ensure the test address has some test ETH balance

## Best Practices

### For Development

- **Always run unit tests first**: They're faster and don't require network access
- **Use integration tests sparingly**: They're slower and require real network calls
- **Test locally before pushing**: Ensure integration tests pass with your RPC endpoint
- **Be aware of rate limits**: Public RPC endpoints have rate limits

### For CI/CD

- **Keep secrets secure**: Never commit RPC endpoints or API keys
- **Use dedicated RPC endpoints**: Don't use your development endpoint in CI
- **Monitor rate limits**: Consider using a paid plan for CI to avoid rate limiting
- **Make integration tests optional**: Allow CI to pass even if integration tests are disabled

## Troubleshooting

### Integration Tests Are Skipped

**Problem**: Tests show as skipped with message "Integration tests are disabled"

**Solution**: Ensure `RUN_INTEGRATION_TESTS=true` is set:
```bash
export RUN_INTEGRATION_TESTS=true
```

### Missing RPC Endpoint

**Problem**: Tests show as skipped with message "ETHEREUM_RPC_ENDPOINT environment variable is required"

**Solution**: Set the endpoint environment variable:
```bash
export ETHEREUM_RPC_ENDPOINT=https://sepolia.infura.io/v3/YOUR_PROJECT_ID
```

### Rate Limiting Errors

**Problem**: Tests fail with "429 Too Many Requests" or similar errors

**Solutions**:
- Use a paid RPC provider (Infura, Alchemy)
- Add delays between test runs
- Reduce the number of rapid requests in tests
- Use a different RPC endpoint

### Timeout Errors

**Problem**: Tests fail with timeout errors

**Solutions**:
- Increase timeout in driver configuration
- Check your network connection
- Try a different RPC endpoint
- Check if the RPC provider is experiencing issues

### Invalid Test Data

**Problem**: Tests fail because transactions or blocks don't exist

**Solution**: Update test data constants in `EthereumIntegrationTest.php` with current Sepolia data

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Sepolia Testnet Faucet](https://sepoliafaucet.com/)
- [Sepolia Etherscan](https://sepolia.etherscan.io/)
- [Infura Documentation](https://docs.infura.io/)
- [Alchemy Documentation](https://docs.alchemy.com/)
