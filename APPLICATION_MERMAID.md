# PHP Blockchain Application Architecture

This document provides a visual overview of the PHP Blockchain package architecture using Mermaid diagrams.

**Last Updated:** 2025-11-08

## Changelog

### 2025-11-08 - TASK-006: Telemetry Hooks via OperationTracer
- Added `OperationTracerInterface` in `Blockchain\Telemetry` namespace
- Added `OperationTracer` concrete implementation with no-op defaults
- Extended tracer hooks: `onEnqueued()`, `onDequeued()`, `onBatchDispatched()`, `onBroadcastResult()`, `onTransactionBuilt()`
- Integrated tracer into `TransactionQueue` with lifecycle events
- Integrated tracer into `TransactionBuilder` with `withTracer()` method
- Updated `Batcher` to use new interface location
- Added comprehensive test coverage for telemetry hooks

---

## Overall Architecture

```mermaid
graph TB
    subgraph "Client Application"
        App[Application Code]
    end

    subgraph "Core Layer"
        BM[BlockchainManager]
        DR[DriverRegistry]
    end

    subgraph "Operations Layer"
        TB[TransactionBuilder]
        TQ[TransactionQueue]
        BA[Batcher]
        ID[Idempotency]
    end

    subgraph "Telemetry Layer"
        OT[OperationTracer]
        OTI[OperationTracerInterface]
    end

    subgraph "Drivers"
        ED[EthereumDriver]
        SD[SolanaDriver]
        BD[BlockchainDriverInterface]
    end

    subgraph "Wallet Layer"
        WI[WalletInterface]
        SW[SoftwareWallet]
    end

    subgraph "Transport Layer"
        GA[GuzzleAdapter]
        HCA[HttpClientAdapter]
    end

    subgraph "Utilities"
        ABI[ABI Encoder/Decoder]
        AV[AddressValidator]
        CP[CachePool]
        SER[Serializer]
    end

    App --> BM
    BM --> DR
    BM --> TB
    App --> TB
    TB --> BD
    TB --> WI
    TB -.-> OTI
    TQ -.-> OTI
    BA --> TQ
    BA --> BD
    BA -.-> OTI
    DR --> ED
    DR --> SD
    ED --> BD
    SD --> BD
    ED --> GA
    SD --> GA
    GA --> HCA
    WI --> SW
    TB --> ID
    OT -.-> OTI

    style OT fill:#e1f5ff
    style OTI fill:#e1f5ff
    style TB fill:#ffe1e1
    style TQ fill:#ffe1e1
    style BA fill:#ffe1e1
```

---

## Telemetry Architecture (TASK-006)

```mermaid
classDiagram
    class OperationTracerInterface {
        <<interface>>
        +onEnqueued(array job) void
        +onDequeued(array job) void
        +onBatchDispatched(array batch) void
        +traceBatchStart(int jobCount) void
        +traceBatchComplete(int successCount, int failureCount) void
        +traceJobSuccess(string jobId) void
        +traceJobFailure(string jobId, string errorMessage) void
        +onBroadcastResult(array result) void
        +onTransactionBuilt(array metadata) void
    }

    class OperationTracer {
        +onEnqueued(array job) void
        +onDequeued(array job) void
        +onBatchDispatched(array batch) void
        +traceBatchStart(int jobCount) void
        +traceBatchComplete(int successCount, int failureCount) void
        +traceJobSuccess(string jobId) void
        +traceJobFailure(string jobId, string errorMessage) void
        +onBroadcastResult(array result) void
        +onTransactionBuilt(array metadata) void
    }

    class TransactionBuilder {
        -BlockchainDriverInterface driver
        -WalletInterface wallet
        -?string feePayer
        -?string memo
        -?array gasOptions
        -?OperationTracerInterface tracer
        +__construct(driver, wallet)
        +withFeePayer(string feePayer) self
        +withMemo(string memo) self
        +withGasOptions(array gasOptions) self
        +withTracer(OperationTracerInterface tracer) self
        +buildTransfer(string to, float amount, array options) array
        +buildContractCall(string method, array params, array options) array
    }

    class TransactionQueue {
        -SplQueue queue
        -int maxAttempts
        -int baseBackoffSeconds
        -int maxBackoffSeconds
        -?OperationTracerInterface tracer
        +__construct(array options, ..., ?OperationTracerInterface tracer)
        +enqueue(TransactionJob job) void
        +dequeue() ?TransactionJob
        +recordFailure(TransactionJob job, Throwable reason) void
        +acknowledge(TransactionJob job) void
        +size() int
    }

    class Batcher {
        -BlockchainDriverInterface driver
        -TransactionQueue queue
        -int batchSize
        -?OperationTracerInterface tracer
        +__construct(driver, queue, int batchSize, ?OperationTracerInterface tracer)
        +collectReadyJobs(?int max) array
        +dispatch() BatchResult
    }

    OperationTracer ..|> OperationTracerInterface
    TransactionBuilder ..> OperationTracerInterface : uses
    TransactionQueue ..> OperationTracerInterface : uses
    Batcher ..> OperationTracerInterface : uses
```

---

## Transaction Lifecycle with Telemetry

```mermaid
sequenceDiagram
    participant App as Application
    participant TB as TransactionBuilder
    participant TQ as TransactionQueue
    participant BA as Batcher
    participant DRV as Driver
    participant TRC as OperationTracer

    App->>TB: buildTransfer(to, amount)
    TB->>TB: Build payload & metadata
    TB->>TB: Sign transaction
    TB->>TRC: onTransactionBuilt(metadata)
    TB-->>App: transaction

    App->>TQ: enqueue(job)
    TQ->>TRC: onEnqueued(job)
    TQ-->>App: void

    loop Batch Processing
        BA->>TQ: dequeue()
        TQ->>TRC: onDequeued(job)
        TQ-->>BA: job
        
        BA->>TRC: traceBatchStart(jobCount)
        BA->>DRV: sendTransaction(...)
        
        alt Success
            DRV-->>BA: txHash
            BA->>TRC: traceJobSuccess(jobId)
            %% BA->>TRC: onBroadcastResult({success: true, ...}) -- REMOVED, not called in implementation
            BA->>TQ: acknowledge(job)
        else Failure
            DRV-->>BA: Exception
            BA->>TRC: traceJobFailure(jobId, error)
            %% BA->>TRC: onBroadcastResult({success: false, ...}) -- REMOVED, not called in implementation
            BA->>TQ: recordFailure(job, error)
            TQ->>TRC: onEnqueued(job) [retry]
        end
        
        BA->>TRC: traceBatchComplete(successCount, failureCount)
    end
```

---

## Operations Module Class Diagram

```mermaid
classDiagram
    class TransactionBuilder {
        -BlockchainDriverInterface driver
        -WalletInterface wallet
        -?string feePayer
        -?string memo
        -?array gasOptions
        -?OperationTracerInterface tracer
        +__construct(driver, wallet)
        +withFeePayer(string) self
        +withMemo(string) self
        +withGasOptions(array) self
        +withTracer(OperationTracerInterface) self
        +buildTransfer(string to, float amount, array options) array
        +buildContractCall(string method, array params, array options) array
        -buildTransferPayload(...) array
        -buildContractCallPayload(...) array
        -buildMetadata(array options) array
        -signPayload(array payload) array
        -getDriverName() string
    }

    class TransactionQueue {
        -SplQueue~TransactionJob~ queue
        -int maxAttempts
        -int baseBackoffSeconds
        -int maxBackoffSeconds
        -callable clockFn
        -callable jitterFn
        -?LoggerInterface logger
        -?IdempotencyStoreInterface idempotencyStore
        -?OperationTracerInterface tracer
        +__construct(array options, ..., ?OperationTracerInterface tracer)
        +enqueue(TransactionJob job) void
        +dequeue() ?TransactionJob
        +recordFailure(TransactionJob job, Throwable reason) void
        +acknowledge(TransactionJob job) void
        +size() int
        -calculateBackoff(int attempts) int
    }

    class TransactionJob {
        -string id
        -array payload
        -array metadata
        -int attempts
        -int nextAvailableAt
        -?string idempotencyToken
        +__construct(...)
        +getId() string
        +getPayload() array
        +getMetadata() array
        +getAttempts() int
        +getNextAvailableAt() int
        +getIdempotencyToken() ?string
    }

    class Batcher {
        -BlockchainDriverInterface driver
        -TransactionQueue queue
        -int batchSize
        -?OperationTracerInterface tracer
        +__construct(driver, queue, int batchSize, ?OperationTracerInterface tracer)
        +collectReadyJobs(?int max) array
        +dispatch() BatchResult
        -supportsBatching() bool
        -submitBatch(array jobs) array
        -submitSingleJob(TransactionJob job) void
        -handleJobFailure(TransactionJob job, Throwable error) void
        -sanitizeErrorMessage(Throwable error) string
    }

    class BatchResult {
        -array successfulJobs
        -array failedJobs
        +__construct(array successfulJobs, array failedJobs)
        +getSuccessCount() int
        +getFailureCount() int
        +getSuccessfulJobs() array
        +getFailedJobs() array
        +isFullSuccess() bool
        +isFullFailure() bool
    }

    class Idempotency {
        +generate(string hint) string
    }

    TransactionQueue --> TransactionJob : manages
    Batcher --> TransactionQueue : uses
    Batcher --> BatchResult : returns
    TransactionBuilder --> Idempotency : uses
    TransactionBuilder ..> OperationTracerInterface : optional
    TransactionQueue ..> OperationTracerInterface : optional
    Batcher ..> OperationTracerInterface : optional
```

---

## Driver Architecture

```mermaid
classDiagram
    class BlockchainDriverInterface {
        <<interface>>
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(string from, string to, float amount, array options) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(string from, string to, float amount, array options) ?int
        +getTokenBalance(string address, string tokenAddress) ?float
        +getNetworkInfo() ?array
    }

    class EthereumDriver {
        -HttpClientAdapter adapter
        -string endpoint
        -int chainId
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(...) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(...) ?int
        +getTokenBalance(...) ?float
        +getNetworkInfo() ?array
        +callContract(string contractAddress, string method, array params) mixed
    }

    class SolanaDriver {
        -HttpClientAdapter adapter
        -string endpoint
        -string cluster
        +connect(array config) void
        +getBalance(string address) float
        +sendTransaction(...) string
        +getTransaction(string hash) array
        +getBlock(int|string blockIdentifier) array
        +estimateGas(...) ?int
        +getTokenBalance(...) ?float
        +getNetworkInfo() ?array
    }

    BlockchainDriverInterface <|.. EthereumDriver
    BlockchainDriverInterface <|.. SolanaDriver
```

---

## Wallet Architecture

```mermaid
classDiagram
    class WalletInterface {
        <<interface>>
        +getPublicKey() string
        +sign(string payload) string
        +getAddress() string
    }

    class SoftwareWallet {
        -string privateKey
        -string publicKey
        -string address
        +__construct(string privateKey)
        +getPublicKey() string
        +sign(string payload) string
        +getAddress() string
        -derivePublicKey() string
        -deriveAddress() string
    }

    WalletInterface <|.. SoftwareWallet
```

---

## Security Model (SEC-001 Compliance)

```mermaid
graph LR
    subgraph "Safe Zone - No Sensitive Data"
        TRC[OperationTracer]
        LOG[Logger]
        MET[Metrics System]
    end

    subgraph "Protected Zone - Sanitized Data Only"
        TB[TransactionBuilder]
        TQ[TransactionQueue]
        BA[Batcher]
    end

    subgraph "Secure Zone - Sensitive Data"
        WAL[Wallet]
        PK[Private Keys]
        SIG[Signatures]
        PAY[Full Payloads]
    end

    TB -->|sanitized metadata| TRC
    TQ -->|job IDs only| TRC
    BA -->|sanitized errors| TRC
    TRC --> LOG
    TRC --> MET
    WAL -.->|NEVER exposed| PK
    TB -->|signs via interface| WAL
    
    style PK fill:#ff6b6b
    style SIG fill:#ff6b6b
    style PAY fill:#ff6b6b
    style WAL fill:#ffe66d
    style TRC fill:#95e1d3
    style LOG fill:#95e1d3
    style MET fill:#95e1d3
```

---

## Key Design Principles

1. **No-Op by Default**: All telemetry hooks are optional and have zero overhead when not used
2. **Immutability**: TransactionBuilder uses builder pattern with immutable configuration
3. **SEC-001 Compliance**: Private keys never leave wallet, sensitive data never logged
4. **Extensibility**: Custom tracers can be created by extending OperationTracer
5. **Type Safety**: Full PHP 8.2+ type hints on all methods and properties
6. **Testability**: All components support dependency injection and testing

---

## API Usage Examples

### Basic Tracer Usage

```php
use Blockchain\Telemetry\OperationTracer;
use Blockchain\Operations\TransactionQueue;

// No-op tracer (zero overhead)
$tracer = new OperationTracer();
$queue = new TransactionQueue(tracer: $tracer);
```

### Custom Tracer Implementation

```php
class MetricsTracer extends OperationTracer
{
    public function __construct(private MetricsCollector $metrics) {}

    public function onEnqueued(array $job): void
    {
        $this->metrics->increment('jobs.enqueued');
    }

    public function onBroadcastResult(array $result): void
    {
        if ($result['success']) {
            $this->metrics->increment('transactions.success');
        } else {
            $this->metrics->increment('transactions.failed');
        }
    }
}

$tracer = new MetricsTracer($metrics);
$builder = (new TransactionBuilder($driver, $wallet))->withTracer($tracer);
```

### Complete Lifecycle

```php
// Build transaction with tracer
$builder = (new TransactionBuilder($driver, $wallet))->withTracer($tracer);
$tx = $builder->buildTransfer('0x...', 1.0);
// → emits: onTransactionBuilt()

// Enqueue
$queue = new TransactionQueue(tracer: $tracer);
$queue->enqueue(new TransactionJob('tx-1', $tx['payload'], $tx['metadata']));
// → emits: onEnqueued()

// Batch process
$batcher = new Batcher($driver, $queue, tracer: $tracer);
$result = $batcher->dispatch();
// → emits: onDequeued(), traceBatchStart(), traceJobSuccess(), traceBatchComplete()
```

---

## Related Documentation

- [PRD.md](PRD.md) - Product Requirements Document
- [TESTING.md](TESTING.md) - Testing Guidelines
- [CONTRIBUTING.md](CONTRIBUTING.md) - Contribution Guidelines
- [plan/feature-core-operations-1.md](plan/feature-core-operations-1.md) - Operations Feature Plan
