# 🧾 Specification Document

## Project: PHP Blockchain Integration Layer

**Codename:** `php-blockchain-agent`
**Author:** Azahari Zaman
**Version:** 0.1 (Draft Spec)
**License:** MIT
**Language:** PHP 8.2+
**Type:** Open Source SDK / Agentic-Ready Repository

---

## 1. Overview

### 1.1 Purpose

This project provides a **modular, unified PHP interface** for integrating various **blockchain networks** — both EVM-based and non-EVM — into any PHP application.
It aims to bridge the PHP ecosystem with modern blockchain APIs that lack native SDKs, offering an **agent-ready architecture** where new blockchain drivers can be automatically generated, tested, and integrated via AI or developer agents.

---

### 1.2 Objectives

* Provide a **plug-and-play blockchain abstraction layer** for PHP developers.
* Support major **JSON-RPC and REST-based blockchains** (e.g. Ethereum, Solana, Near, Polygon).
* Enable **auto-generation** of new blockchain drivers through structured tasks or GitHub Copilot Agents.
* Offer **unified method naming**, consistent across all supported networks.
* Allow **Laravel and Symfony integration** via optional service providers.

---

## 2. Scope

The package provides:

1. **BlockchainManager** — main entry point handling connection and driver selection.
2. **Driver Interface** — defines the standard contract for all blockchain implementations.
3. **Driver Registry** — allows runtime registration of new blockchain drivers.
4. **HTTP Client Layer** — uses Guzzle for interacting with blockchain APIs.
5. **Utility Classes** — helpers for transactions, addresses, and format conversions.
6. **CLI Tool (optional)** — quick commands for querying balances or sending transactions.
7. **Agent Task Specs** — YAML task blueprints for driver generation, testing, and documentation updates.

---

## 3. Architecture Overview

### 3.1 Components

| Component                   | Description                                                                                                  |
| --------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `BlockchainManager`         | Core orchestrator. Loads and initializes blockchain driver classes.                                          |
| `BlockchainDriverInterface` | Defines contract for driver implementations (connect, getBalance, sendTransaction, etc).                     |
| `Drivers/*`                 | Each blockchain driver lives in this namespace. Implements the interface and manages specific RPC endpoints. |
| `Utils/*`                   | Shared helpers for data conversion, signing, and address validation.                                         |
| `Exceptions/*`              | Structured error handling classes.                                                                           |
| `Config/*`                  | Optional config files defining supported blockchains, keys, and defaults.                                    |

---

### 3.2 Example Flow

```php
$blockchain = new BlockchainManager('solana', [
    'endpoint' => 'https://api.mainnet-beta.solana.com'
]);

$balance = $blockchain->getBalance('YourPublicKeyHere');
$txHash = $blockchain->sendTransaction($wallet, $recipient, $amount);
```

---

## 4. Driver Specification

Each blockchain integration must:

* Implement `BlockchainDriverInterface`
* Reside in `src/Drivers/{BlockchainName}Driver.php`
* Register itself in the driver registry
* Contain the following core methods:

```php
interface BlockchainDriverInterface
{
    public function connect(array $config): void;
    public function getBalance(string $address): float;
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string;
    public function getTransaction(string $txHash): array;
    public function getBlock(int|string $blockNumber): array;
}
```

Optional methods:

* `estimateGas()`
* `getTokenBalance()`
* `getNetworkInfo()`

---

## 5. Agentic Structure

To make the repository **agentic**, the following files and conventions should exist:

### 5.1 `.copilot/agent.yml`

Defines the repository’s agent persona and abilities.

```yaml
name: PHP Blockchain Agent
description: "Assists in extending blockchain integrations and maintaining SDK structure."
capabilities:
  - code-generation
  - test-generation
  - documentation-update
tasks:
  - id: create-new-driver
    description: "Generate a new blockchain driver."
    input:
      - blockchain_name
      - rpc_spec_url
    output: "src/Drivers/{blockchain_name}Driver.php"
  - id: update-readme
    description: "Regenerate README.md with supported driver list."
  - id: test-driver
    description: "Run driver-specific test cases using PHPUnit."
```

---

## 6. Example Driver Blueprint (Solana)

```php
namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;
use GuzzleHttp\Client;

class SolanaDriver implements BlockchainDriverInterface
{
    protected Client $client;

    public function connect(array $config): void
    {
        $this->client = new Client(['base_uri' => $config['endpoint']]);
    }

    public function getBalance(string $address): float
    {
        $response = $this->client->post('', [
            'json' => ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'getBalance', 'params' => [$address]]
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['result']['value'] ?? 0;
    }

    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        // Placeholder for transaction logic
        return 'tx_hash_placeholder';
    }

    public function getTransaction(string $txHash): array
    {
        // RPC call for Solana transaction details
        return [];
    }

    public function getBlock(int|string $blockNumber): array
    {
        // Fetch block info
        return [];
    }
}
```

---

## 7. Development Guidelines

* Follow **PSR-4 autoloading** and **PSR-12 coding standards**.
* Each new blockchain driver must include:

  * Driver class
  * PHPUnit test
  * Driver registration in `DriverRegistry`
  * Example usage in `/examples`
* Documentation generated from `/docs/specs/*.md`

---

## 8. Future Extensions

* Smart contract interaction layer for EVM-based networks
* Wallet keypair generation
* Multi-signature transaction support
* WebSocket-based live event listener
* Integration with Laravel Filament plugin for blockchain dashboards

---

## 9. Repo Automation Hooks

* **Copilot Tasks:** Auto-generate drivers, tests, and docs.
* **GitHub Actions:**

  * Lint PHP code
  * Run tests
  * Publish package to Packagist
  * Auto-update `README.md` with supported drivers list

---

## 10. Example Folder Layout

```
/php-blockchain-agent
├── .copilot/
│   └── agent.yml
├── src/
│   ├── BlockchainManager.php
│   ├── Contracts/BlockchainDriverInterface.php
│   ├── Drivers/
│   │   └── SolanaDriver.php
│   ├── Registry/DriverRegistry.php
│   ├── Utils/
│   ├── Exceptions/
│   └── Config/
├── tests/
│   └── SolanaDriverTest.php
├── composer.json
├── README.md
└── LICENSE
```
