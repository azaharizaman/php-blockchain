## Epic: Ethereum Driver

### 🎯 Goal & Value Proposition
Provide a production-ready Ethereum (EVM) driver that implements the unified driver interface, allowing developers to interact with Ethereum-compatible networks (Ethereum mainnet, Polygon, BSC, Arbitrum) using the same API as other drivers. The driver must support smart contract interaction, token standards (ERC-20, ERC-721), and gas optimization.

### ⚙️ Features & Requirements
1. Implement `EthereumDriver` that adheres to `Blockchain\Contracts\BlockchainDriverInterface`.
2. Connect to JSON-RPC endpoints (Infura, Alchemy, self-hosted nodes) with robust configuration.
3. Support balance queries (ETH and token balances via ERC-20 `balanceOf`).
4. Support sending transactions, including raw signed txs and gas price/strategy options.
5. Implement `estimateGas()` and gas price recommendation strategies.
6. Provide contract interaction helpers (call, send, encode/decode ABI).
7. Support ERC-20 and ERC-721 token operations (balance, transfer, approval checks).
8. Implement event logs parsing and basic indexed event queries.
9. Provide pagination and history retrieval for transactions and token transfers.
10. Integrate with caching layer for repeated RPC calls and response deduplication.
11. Add extensive unit tests with mocked RPC responses and integration tests for testnets.

### 🤝 Module Mapping & Dependencies
- PHP Namespace / Module: `Blockchain\Drivers\Ethereum` (place implementation in `src/Drivers/EthereumDriver.php` and helpers under `src/Utils/` as needed)
- Depends on: Core Utilities (interfaces, registry), Utils (ABI encoder/decoder), Security & Reliability (key handling)

### ✅ Acceptance Criteria
- `EthereumDriver` implements the full driver interface and passes unit tests.
- Gas estimation and transaction sending works with mocked RPC in unit tests.
- Contract call and decode helpers return expected outputs in tests.
- Integration tests validate send/call flows on a public testnet (operator-enabled CI).
- Documentation and usage examples are added under `docs/drivers/ethereum.md`.
