# Solana Driver

This page documents the Solana driver implementation for the PHP Blockchain Integration Layer.

## Overview

The `SolanaDriver` implements `Blockchain\Contracts\BlockchainDriverInterface` and provides basic RPC interactions with a Solana JSON-RPC endpoint using Guzzle.

Key methods:

- `connect(array $config)`: configure the HTTP client (`endpoint`, `timeout`)
- `getBalance(string $address)`: returns SOL balance (converted from lamports)
- `sendTransaction(string $from, string $to, float $amount, array $options = [])`: placeholder (signing should be handled by a wallet library)
- `getTransaction(string $txHash)`: fetch transaction details
- `getBlock(int|string $blockNumber)`: fetch block information
- `getTokenBalance(string $address, string $tokenAddress)`: returns SPL token balance

## Installation

```bash
composer require azaharizaman/php-blockchain
```

## Usage example

```php
use Blockchain\BlockchainManager;

$manager = new BlockchainManager('solana', ['endpoint' => 'https://api.mainnet-beta.solana.com']);
$balance = $manager->getBalance('YourPublicKeyHere');
```

## Testing

Unit tests use `GuzzleHttp\Handler\MockHandler` to mock RPC responses. Tests assert conversion from lamports to SOL and error handling for RPC error payloads.

## Caveats & Security

- The driver does not include key management. Private keys must never be committed to the repository — use secure key stores or environment-based injection.
- For signing transactions, use a dedicated wallet library and pass signed transactions to `sendTransaction` or extend the driver to accept a signer interface.
- Integration tests that perform real transactions should be run on testnets only and require `allow_network_access` enabled by an operator.

## Extending

- Add contract interaction helpers if building EVM-like behavior on Solana wrappers.
- Implement robust signing by integrating with external signer services or secure HSMs.
- Add caching for frequently requested endpoints (e.g., block lookups) to reduce RPC load.
