# PHP Blockchain Integration Layer - Architecture Diagram

> **Auto-Generated Architecture Visualization**  
> Last Updated: 2025-11-08  
> This diagram is maintained by agents and should be updated whenever changes are made to the `src/` directory.

## Table of Contents
- [Overview](#overview)
- [High-Level Architecture](#high-level-architecture)
- [Core Components](#core-components)
- [Driver System](#driver-system)
- [Exception Hierarchy](#exception-hierarchy)
- [Utilities & Helpers](#utilities--helpers)
- [Operations Layer](#operations-layer)
- [Class Relationships](#class-relationships)
- [Method Flow Diagrams](#method-flow-diagrams)

---

## Overview

This document provides a comprehensive visual representation of the PHP Blockchain Integration Layer architecture. It shows all classes, interfaces, their relationships, key methods, and properties.

**Directory Structure:**
```
src/
├── BlockchainManager.php          # Main facade
├── Contracts/                     # Interfaces
│   └── BlockchainDriverInterface.php
├── Drivers/                       # Blockchain implementations
│   ├── EthereumDriver.php
│   └── SolanaDriver.php
├── Registry/                      # Driver management
│   └── DriverRegistry.php
├── Config/                        # Configuration
│   └── ConfigLoader.php
├── Transport/                     # HTTP layer
│   ├── HttpClientAdapter.php
│   └── GuzzleAdapter.php
├── Exceptions/                    # Error handling
│   ├── ConfigurationException.php
│   ├── TransactionException.php
│   ├── ValidationException.php
│   ├── UnsupportedDriverException.php
│   ├── ContractException.php
│   └── RpcException.php
├── Operations/                    # Transaction operations
│   ├── TransactionBuilder.php
│   └── TransactionQueue.php
├── Utils/                         # Utilities
│   ├── AddressValidator.php
│   ├── CachePool.php
│   ├── Serializer.php
│   ├── Abi.php
│   ├── Keccak.php
│   └── KeccakLib.php
└── Wallet/                        # Wallet interface
    └── WalletInterface.php
```

---

## High-Level Architecture

```mermaid
graph TB
    subgraph "Application Layer"
        APP[Application Code]
    end
    
    subgraph "Facade Layer"
        BM[BlockchainManager<br/>Main Entry Point]
    end
    
    subgraph "Core Layer"
        IFACE[BlockchainDriverInterface<br/>Contract]
        REG[DriverRegistry<br/>Driver Management]
        CONFIG[ConfigLoader<br/>Configuration]
    end
    
    subgraph "Driver Layer"
        SOL[SolanaDriver<br/>Solana Implementation]
        ETH[EthereumDriver<br/>Ethereum Implementation]
    end
    
    subgraph "Operations Layer"
        TB[TransactionBuilder<br/>Transaction Assembly]
        TQ[TransactionQueue<br/>Queue Management]
    end
    
    subgraph "Transport Layer"
        HTTP[HttpClientAdapter<br/>Interface]
        GUZZ[GuzzleAdapter<br/>HTTP Client]
    end
    
    subgraph "Support Layer"
        WALLET[WalletInterface<br/>Wallet Contract]
        UTILS[Utilities<br/>Helpers]
        CACHE[CachePool<br/>Caching]
        VALID[AddressValidator<br/>Validation]
    end
    
    subgraph "Exception Layer"
        EXC[Exception Hierarchy<br/>Error Handling]
    end
    
    APP --> BM
    BM --> IFACE
    BM --> REG
    BM --> CONFIG
    REG --> SOL
    REG --> ETH
    SOL --> IFACE
    ETH --> IFACE
    SOL --> GUZZ
    ETH --> GUZZ
    SOL --> CACHE
    ETH --> CACHE
    GUZZ --> HTTP
    TB --> IFACE
    TB --> WALLET
    TQ --> TB
    SOL --> VALID
    ETH --> VALID
    SOL --> UTILS
    ETH --> UTILS
    BM --> EXC
    SOL --> EXC
    ETH --> EXC
    TB --> EXC
    TQ --> EXC
    
    style BM fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style IFACE fill:#2196F3,stroke:#1565C0,stroke-width:2px,color:#fff
    style SOL fill:#FF9800,stroke:#E65100,stroke-width:2px,color:#fff
    style ETH fill:#FF9800,stroke:#E65100,stroke-width:2px,color:#fff
```

---

## Core Components

### BlockchainManager (Main Facade)

```mermaid
classDiagram
    class BlockchainManager {
        -BlockchainDriverInterface currentDriver
        -array~BlockchainDriverInterface~ drivers
        -DriverRegistry registry
        +__construct(string driverName, array config)
        +setDriver(string name, array config) void
        +switchDriver(string name) self
        +getDriverRegistry() DriverRegistry
        +getSupportedDrivers() array
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(string from, string to, float amount, array options) int
        +getTokenBalance(string address, string tokenAddress) float
        +getNetworkInfo() array
    }
    
    class BlockchainDriverInterface {
        <<interface>>
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(string from, string to, float amount, array options) int
        +getTokenBalance(string address, string tokenAddress) float
        +getNetworkInfo() array
    }
    
    class DriverRegistry {
        -array~string~ drivers
        +__construct()
        +registerDriver(string name, string driverClass) void
        +getDriver(string name) BlockchainDriverInterface
        +hasDriver(string name) bool
        +getRegisteredDrivers() array
        -registerDefaultDrivers() void
    }
    
    BlockchainManager ..|> BlockchainDriverInterface : implements
    BlockchainManager --> DriverRegistry : uses
    BlockchainManager --> BlockchainDriverInterface : delegates to
```

---

## Driver System

### Driver Implementations

```mermaid
classDiagram
    class BlockchainDriverInterface {
        <<interface>>
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(...) int
        +getTokenBalance(string address, string tokenAddress) float
        +getNetworkInfo() array
    }
    
    class SolanaDriver {
        -GuzzleAdapter httpClient
        -array config
        -CachePool cache
        -const LAMPORTS_PER_SOL
        +__construct(GuzzleAdapter httpClient, CachePool cache)
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(...) int
        +getTokenBalance(string address, string tokenAddress) float
        +getNetworkInfo() array
        -ensureConnected() void
        -rpcCall(string method, array params) array
    }
    
    class EthereumDriver {
        -GuzzleAdapter httpClient
        -array config
        -CachePool cache
        -const WEI_PER_ETH
        +__construct(GuzzleAdapter httpClient, CachePool cache)
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(string from, string to, float amount, array options) int
        +getTokenBalance(string address, string tokenAddress) float
        +getNetworkInfo() array
        +callContract(string contractAddress, string method, array params) array
        -ensureConnected() void
        -rpcCall(string method, array params) array
        -hexToDecimal(string hex) string
        -decimalToHex(string decimal) string
    }
    
    class GuzzleAdapter {
        -Client client
        -array config
        +__construct(Client client, array config)
        +post(string uri, array data) array
        +get(string uri, array params) array
    }
    
    class CachePool {
        -array cache
        -array ttl
        +get(string key) mixed
        +set(string key, mixed value, int ttl) void
        +has(string key) bool
        +delete(string key) void
        +clear() void
        +generateKey(string method, array params) string
    }
    
    SolanaDriver ..|> BlockchainDriverInterface : implements
    EthereumDriver ..|> BlockchainDriverInterface : implements
    SolanaDriver --> GuzzleAdapter : uses
    EthereumDriver --> GuzzleAdapter : uses
    SolanaDriver --> CachePool : uses
    EthereumDriver --> CachePool : uses
```

---

## Exception Hierarchy

```mermaid
graph TB
    BASE[Exception<br/>PHP Base]
    
    CONFIG[ConfigurationException<br/>Invalid/Missing Config]
    UNSUP[UnsupportedDriverException<br/>Driver Not Available]
    TRANS[TransactionException<br/>Transaction Failures<br/>+getTransactionHash()]
    VALID[ValidationException<br/>Input Validation<br/>+getErrors()]
    CONTRACT[ContractException<br/>Smart Contract Errors<br/>+getContractAddress()]
    RPC[RpcException<br/>RPC Call Failures]
    
    BASE --> CONFIG
    BASE --> UNSUP
    BASE --> TRANS
    BASE --> VALID
    BASE --> CONTRACT
    BASE --> RPC
    
    style BASE fill:#F44336,stroke:#C62828,stroke-width:2px,color:#fff
    style CONFIG fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
    style UNSUP fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
    style TRANS fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
    style VALID fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
    style CONTRACT fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
    style RPC fill:#FF5722,stroke:#D84315,stroke-width:2px,color:#fff
```

### Exception Details

```mermaid
classDiagram
    class ConfigurationException {
        +__construct(string message)
        +getMessage() string
    }
    
    class UnsupportedDriverException {
        +__construct(string message)
        +getMessage() string
    }
    
    class TransactionException {
        -string transactionHash
        +__construct(string message)
        +setTransactionHash(string hash) self
        +getTransactionHash() string
    }
    
    class ValidationException {
        -array errors
        +__construct(string message, array errors)
        +getErrors() array
    }
    
    class ContractException {
        -string contractAddress
        +__construct(string message)
        +setContractAddress(string address) self
        +getContractAddress() string
    }
    
    class RpcException {
        -int code
        -mixed data
        +__construct(string message, int code, mixed data)
        +getCode() int
        +getData() mixed
    }
```

---

## Utilities & Helpers

```mermaid
classDiagram
    class ConfigLoader {
        <<static>>
        +fromArray(array config) array
        +fromEnv(string prefix) array
        +fromFile(string path) array
        +validateConfig(array config, string driver) void
        -parseEnvValue(string value) mixed
        -mergeConfig(array base, array override) array
    }
    
    class AddressValidator {
        <<static>>
        +isValid(string address, string network) bool
        +normalize(string address) string
        -validateSolanaAddress(string address) bool
        -validateEthereumAddress(string address) bool
    }
    
    class CachePool {
        -array cache
        -array ttl
        +get(string key) mixed
        +set(string key, mixed value, int ttl) void
        +has(string key) bool
        +delete(string key) void
        +clear() void
        +generateKey(string method, array params) string
        -isExpired(string key) bool
    }
    
    class Serializer {
        <<static>>
        +encode(mixed data, string format) string
        +decode(string data, string format) mixed
        +toJson(mixed data) string
        +fromJson(string json) mixed
    }
    
    class Abi {
        +encodeFunctionCall(string signature, array params) string
        +decodeFunctionResult(string signature, string data) array
        +encodeParameter(string type, mixed value) string
        +decodeParameter(string type, string data) mixed
    }
    
    class Keccak {
        +hash(string data, int bits) string
        +keccak256(string data) string
    }
```

---

## Operations Layer

### Transaction Operations

```mermaid
classDiagram
    class TransactionBuilder {
        -BlockchainDriverInterface driver
        -WalletInterface wallet
        -array options
        +__construct(BlockchainDriverInterface driver, WalletInterface wallet)
        +buildTransfer(string to, float amount, array options) array
        +buildContractCall(string method, array params, array options) array
        +withFeePayer(string address) self
        +withMemo(string memo) self
        +withGasOptions(array gas) self
        +withNonce(int nonce) self
        -assemblePayload(array data) array
        -attachMetadata(array payload) array
        -signPayload(array payload) array
    }
    
    class TransactionQueue {
        -SplQueue queue
        -int maxAttempts
        -int baseBackoffSeconds
        -int maxBackoffSeconds
        -callable clockFn
        -callable jitterFn
        -LoggerInterface logger
        +__construct(array options, callable clockFn, callable jitterFn)
        +enqueue(TransactionJob job) void
        +dequeue() TransactionJob
        +peek() TransactionJob
        +recordFailure(TransactionJob job, Throwable error) void
        +acknowledge(TransactionJob job) void
        +size() int
        +isEmpty() bool
        -calculateBackoff(int attempts) int
        -isAvailable(TransactionJob job) bool
    }
    
    class TransactionJob {
        -string id
        -array payload
        -array metadata
        -int attempts
        -int nextAvailableAt
        -array errors
        +__construct(string id, array payload, array metadata)
        +getId() string
        +getPayload() array
        +getMetadata() array
        +getAttempts() int
        +getNextAvailableAt() int
        +getErrors() array
        +incrementAttempts() void
        +setNextAvailableAt(int timestamp) void
        +recordError(Throwable error) void
    }
    
    class WalletInterface {
        <<interface>>
        +getPublicKey() string
        +sign(string payload) string
        +getAddress() string
    }
    
    TransactionBuilder --> BlockchainDriverInterface : uses
    TransactionBuilder --> WalletInterface : uses
    TransactionQueue --> TransactionJob : manages
```

---

## Transport Layer

```mermaid
classDiagram
    class HttpClientAdapter {
        <<interface>>
        +get(string url, array options) array
        +post(string url, array data, array options) array
    }
    
    class GuzzleAdapter {
        -Client client
        -array config
        +__construct(Client client, array config)
        +get(string url, array options) array
        +post(string url, array data, array options) array
        -handleRequest(callable request) array
        -handleError(Exception e) void
    }
    
    GuzzleAdapter ..|> HttpClientAdapter : implements
```

---

## Class Relationships

### Complete Dependency Graph

```mermaid
graph LR
    subgraph "Entry Point"
        APP[Application]
    end
    
    subgraph "Facade"
        BM[BlockchainManager]
    end
    
    subgraph "Registry"
        REG[DriverRegistry]
    end
    
    subgraph "Drivers"
        SOL[SolanaDriver]
        ETH[EthereumDriver]
    end
    
    subgraph "Interfaces"
        IFACE[BlockchainDriverInterface]
        HTTP_IFACE[HttpClientAdapter]
        WALLET[WalletInterface]
    end
    
    subgraph "Transport"
        GUZZ[GuzzleAdapter]
    end
    
    subgraph "Config"
        CONFIG[ConfigLoader]
    end
    
    subgraph "Operations"
        TB[TransactionBuilder]
        TQ[TransactionQueue]
        TJ[TransactionJob]
    end
    
    subgraph "Utils"
        CACHE[CachePool]
        VALID[AddressValidator]
        SER[Serializer]
        ABI[Abi]
        KECC[Keccak]
    end
    
    subgraph "Exceptions"
        CONF_EX[ConfigurationException]
        TRANS_EX[TransactionException]
        VALID_EX[ValidationException]
        UNSUP_EX[UnsupportedDriverException]
        CONT_EX[ContractException]
        RPC_EX[RpcException]
    end
    
    APP --> BM
    BM --> REG
    BM --> IFACE
    BM --> CONFIG
    
    REG --> SOL
    REG --> ETH
    
    SOL --> IFACE
    ETH --> IFACE
    
    SOL --> GUZZ
    ETH --> GUZZ
    
    SOL --> CACHE
    ETH --> CACHE
    
    SOL --> VALID
    ETH --> VALID
    
    GUZZ --> HTTP_IFACE
    
    TB --> IFACE
    TB --> WALLET
    TQ --> TJ
    
    ETH --> ABI
    ETH --> KECC
    ETH --> SER
    
    BM --> CONF_EX
    BM --> UNSUP_EX
    SOL --> TRANS_EX
    ETH --> TRANS_EX
    ETH --> CONT_EX
    SOL --> RPC_EX
    ETH --> RPC_EX
    REG --> VALID_EX
    CONFIG --> CONF_EX
    
    style BM fill:#4CAF50,stroke:#2E7D32,stroke-width:3px
    style IFACE fill:#2196F3,stroke:#1565C0,stroke-width:2px
    style SOL fill:#FF9800,stroke:#E65100,stroke-width:2px
    style ETH fill:#FF9800,stroke:#E65100,stroke-width:2px
```

---

## Method Flow Diagrams

### Get Balance Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant BM as BlockchainManager
    participant Driver as SolanaDriver/EthereumDriver
    participant Cache as CachePool
    participant HTTP as GuzzleAdapter
    participant RPC as Blockchain RPC
    
    App->>BM: getBalance(address)
    BM->>Driver: getBalance(address)
    
    Driver->>Cache: has(cacheKey)?
    alt Cache Hit
        Cache-->>Driver: return cached value
        Driver-->>BM: return balance
        BM-->>App: return balance
    else Cache Miss
        Driver->>HTTP: post(rpc_method, params)
        HTTP->>RPC: JSON-RPC Request
        RPC-->>HTTP: JSON-RPC Response
        HTTP-->>Driver: return data
        Driver->>Driver: convert units (lamports→SOL, wei→ETH)
        Driver->>Cache: set(cacheKey, balance, ttl)
        Driver-->>BM: return balance
        BM-->>App: return balance
    end
```

### Send Transaction Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant BM as BlockchainManager
    participant Driver as SolanaDriver/EthereumDriver
    participant Validator as AddressValidator
    participant HTTP as GuzzleAdapter
    participant RPC as Blockchain RPC
    
    App->>BM: sendTransaction(from, to, amount, options)
    BM->>Driver: sendTransaction(from, to, amount, options)
    
    Driver->>Validator: isValid(from, network)
    Validator-->>Driver: validation result
    
    alt Invalid Address
        Driver-->>BM: throw ValidationException
        BM-->>App: throw ValidationException
    else Valid Address
        Driver->>Validator: isValid(to, network)
        Validator-->>Driver: validation result
        
        alt Invalid Address
            Driver-->>BM: throw ValidationException
            BM-->>App: throw ValidationException
        else Valid Address
            Driver->>Driver: convert amount to base units
            Driver->>Driver: prepare transaction payload
            Driver->>HTTP: post(rpc_method, params)
            HTTP->>RPC: JSON-RPC Request (sendTransaction)
            
            alt Success
                RPC-->>HTTP: transaction hash
                HTTP-->>Driver: transaction hash
                Driver-->>BM: return tx hash
                BM-->>App: return tx hash
            else RPC Error
                RPC-->>HTTP: error response
                HTTP-->>Driver: throw exception
                Driver-->>BM: throw TransactionException
                BM-->>App: throw TransactionException
            end
        end
    end
```

### Transaction Builder Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant TB as TransactionBuilder
    participant Wallet as WalletInterface
    participant Driver as BlockchainDriverInterface
    
    App->>TB: buildTransfer(to, amount, options)
    TB->>Wallet: getAddress()
    Wallet-->>TB: from address
    TB->>Wallet: getPublicKey()
    Wallet-->>TB: public key
    
    TB->>TB: assemblePayload(to, amount, options)
    TB->>TB: attachMetadata(address, publicKey)
    TB->>TB: signPayload(payload)
    TB->>Wallet: sign(payload)
    Wallet-->>TB: signature
    TB->>TB: buildFinalTransaction()
    
    TB-->>App: return transaction object
    
    App->>Driver: sendTransaction(transaction)
    Driver-->>App: return tx hash
```

### Driver Registry Flow

```mermaid
sequenceDiagram
    participant App as Application
    participant BM as BlockchainManager
    participant Reg as DriverRegistry
    participant Driver as SolanaDriver
    
    App->>BM: new BlockchainManager('solana', config)
    BM->>Reg: new DriverRegistry()
    Reg->>Reg: registerDefaultDrivers()
    Reg->>Reg: registerDriver('solana', SolanaDriver::class)
    Reg->>Reg: registerDriver('ethereum', EthereumDriver::class)
    
    BM->>Reg: hasDriver('solana')
    Reg-->>BM: true
    
    BM->>Reg: getDriver('solana')
    Reg->>Driver: new SolanaDriver()
    Driver-->>Reg: instance
    Reg-->>BM: driver instance
    
    BM->>Driver: connect(config)
    Driver-->>BM: connected
    
    BM-->>App: ready to use
```

---

## Update Instructions for Agents

When making changes to the `src/` directory structure, please update this diagram according to the following rules:

### 1. Adding a New Class

1. Add the class to the appropriate section based on its namespace:
   - `Blockchain\` → Core Components
   - `Blockchain\Drivers\` → Driver System
   - `Blockchain\Exceptions\` → Exception Hierarchy
   - `Blockchain\Utils\` → Utilities & Helpers
   - `Blockchain\Operations\` → Operations Layer
   - `Blockchain\Transport\` → Transport Layer
   - `Blockchain\Config\` → Core Components
   - `Blockchain\Registry\` → Core Components

2. Update the class diagram with:
   - Class name
   - Key properties (with visibility and type)
   - Key methods (with parameters and return types)
   - Relationships (implements, extends, uses, depends on)

3. Update the dependency graph to show how the new class fits in

### 2. Adding a New Method

1. Add method to the appropriate class diagram
2. If the method is a major flow, add a sequence diagram
3. Update the overview if it changes how components interact

### 3. Adding a New Driver

1. Add driver class to "Driver System" section
2. Update the driver implementations diagram
3. Add driver to DriverRegistry default registrations
4. Update dependency relationships

### 4. Modifying Relationships

1. Update all diagrams showing the modified relationship
2. Update dependency graph
3. Update sequence diagrams if flow changes

### 5. Deprecating/Removing Components

1. Mark as deprecated in diagrams (add `[DEPRECATED]` suffix)
2. After removal, delete from all diagrams
3. Update dependencies to show new paths

### Example Update Format

```markdown
## Changelog

### 2025-11-08 - Initial Architecture Diagram
- Created comprehensive visualization of src/ directory
- Documented all classes, interfaces, relationships
- Added flow diagrams for key operations

### [Date] - [Change Description]
- [Details of what changed]
- [Which diagrams were updated]
```

---

## Changelog

### 2025-11-08 - Initial Architecture Diagram
- Created comprehensive Mermaid diagram for entire `src/` structure
- Documented 23 PHP files across 8 namespaces
- Added high-level architecture overview
- Documented all core components with methods and properties
- Created driver system visualization
- Documented complete exception hierarchy
- Added utilities and helpers documentation
- Created operations layer diagrams
- Added complete dependency graph
- Created 4 sequence diagrams for key flows:
  - Get Balance Flow
  - Send Transaction Flow
  - Transaction Builder Flow
  - Driver Registry Flow
- Added update instructions for agents

---

**Legend:**
- 🟢 Green: Entry points and facades
- 🔵 Blue: Interfaces and contracts
- 🟠 Orange: Implementations (Drivers)
- 🔴 Red: Exceptions
- → Dependency/usage relationship
- ⇢ Implementation relationship
- - - → Association

---

*This diagram is auto-updated by agents. Last manual review: 2025-11-08*
