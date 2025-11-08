<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Blockchain\Operations\TransactionBuilder;
use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Wallet\WalletInterface;
use Blockchain\Exceptions\TransactionException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * TransactionBuilderTest
 *
 * Test suite for the TransactionBuilder class using stub drivers for
 * both Solana and Ethereum to validate transaction workflow primitives.
 *
 * Following TDD principles, these tests define the expected behavior
 * before implementation.
 */
class TransactionBuilderTest extends TestCase
{
    private MockWallet $wallet;

    protected function setUp(): void
    {
        $this->wallet = new MockWallet();
    }

    /**
     * Test basic transfer transaction building for Ethereum
     */
    public function testBuildTransferForEthereum(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.5,
            ['memo' => 'Test payment', 'gas' => 21000]
        );

        // Assert structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('signatures', $result);

        // Assert driver type
        $this->assertEquals('ethereum', $result['driver']);

        // Assert metadata contains wallet info
        $this->assertEquals($this->wallet->getAddress(), $result['metadata']['from']);
        $this->assertEquals($this->wallet->getPublicKey(), $result['metadata']['publicKey']);
        $this->assertEquals('Test payment', $result['metadata']['memo']);
        $this->assertEquals(21000, $result['metadata']['gas']);

        // Assert payload structure for Ethereum (JSON-RPC format)
        $this->assertArrayHasKey('method', $result['payload']);
        $this->assertArrayHasKey('params', $result['payload']);
        $this->assertEquals('eth_sendTransaction', $result['payload']['method']);

        // Assert signature is present (wallet was called)
        $this->assertNotEmpty($result['signatures']);
        $this->assertArrayHasKey('signature', $result['signatures']);
    }

    /**
     * Test basic transfer transaction building for Solana
     */
    public function testBuildTransferForSolana(): void
    {
        $driver = new StubSolanaDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '7EqQdEUFxDcPkE7cGNL9UuZkxFJDLZPrXmKNMqAJBW4K',
            2.5,
            ['memo' => 'Solana payment']
        );

        // Assert structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('signatures', $result);

        // Assert driver type
        $this->assertEquals('solana', $result['driver']);

        // Assert metadata
        $this->assertEquals($this->wallet->getAddress(), $result['metadata']['from']);
        $this->assertEquals($this->wallet->getPublicKey(), $result['metadata']['publicKey']);
        $this->assertEquals('Solana payment', $result['metadata']['memo']);

        // Assert payload structure for Solana (instruction format)
        $this->assertArrayHasKey('programId', $result['payload']);
        $this->assertArrayHasKey('accounts', $result['payload']);
        $this->assertArrayHasKey('data', $result['payload']);

        // Assert signature is present
        $this->assertNotEmpty($result['signatures']);
    }

    /**
     * Test contract call transaction building for Ethereum
     */
    public function testBuildContractCallForEthereum(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildContractCall(
            'transfer',
            ['to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'value' => 1000],
            ['gas' => 50000, 'nonce' => 5]
        );

        // Assert structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('signatures', $result);

        // Assert driver type
        $this->assertEquals('ethereum', $result['driver']);

        // Assert metadata
        $this->assertEquals($this->wallet->getAddress(), $result['metadata']['from']);
        $this->assertEquals($this->wallet->getPublicKey(), $result['metadata']['publicKey']);
        $this->assertEquals(50000, $result['metadata']['gas']);
        $this->assertEquals(5, $result['metadata']['nonce']);

        // Assert payload includes method and params
        $this->assertArrayHasKey('method', $result['payload']);
        $this->assertEquals('transfer', $result['payload']['method']);
        $this->assertArrayHasKey('params', $result['payload']);
        $this->assertEquals('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', $result['payload']['params']['to']);
        $this->assertEquals(1000, $result['payload']['params']['value']);

        // Assert signature is present
        $this->assertNotEmpty($result['signatures']);
    }

    /**
     * Test builder immutability with withFeePayer
     */
    public function testBuilderImmutabilityWithFeePayer(): void
    {
        $driver = new StubSolanaDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $newBuilder = $builder->withFeePayer('CustomFeePayerAddress');

        // Assert that original builder is unchanged
        $this->assertNotSame($builder, $newBuilder);

        // Build transaction with new builder
        $result = $newBuilder->buildTransfer(
            '7EqQdEUFxDcPkE7cGNL9UuZkxFJDLZPrXmKNMqAJBW4K',
            1.0
        );

        // Assert fee payer is set in metadata
        $this->assertEquals('CustomFeePayerAddress', $result['metadata']['feePayer']);
    }

    /**
     * Test builder immutability with withMemo
     */
    public function testBuilderImmutabilityWithMemo(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $newBuilder = $builder->withMemo('Default memo');

        // Assert that original builder is unchanged
        $this->assertNotSame($builder, $newBuilder);

        // Build transaction with new builder
        $result = $newBuilder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0
        );

        // Assert memo is set from builder defaults
        $this->assertEquals('Default memo', $result['metadata']['memo']);
    }

    /**
     * Test builder immutability with withGasOptions
     */
    public function testBuilderImmutabilityWithGasOptions(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $newBuilder = $builder->withGasOptions(['limit' => 100000, 'price' => 50]);

        // Assert that original builder is unchanged
        $this->assertNotSame($builder, $newBuilder);

        // Build transaction with new builder
        $result = $newBuilder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0
        );

        // Assert gas options are set
        $this->assertArrayHasKey('gas', $result['metadata']);
        $this->assertEquals(['limit' => 100000, 'price' => 50], $result['metadata']['gas']);
    }

    /**
     * Test skip signing option
     */
    public function testSkipSigningOption(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0,
            ['skipSign' => true]
        );

        // Assert signature array is empty when skipSign is true
        $this->assertEmpty($result['signatures']);
    }

    /**
     * Test that signing is performed by default
     */
    public function testSigningPerformedByDefault(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0
        );

        // Assert signature is present by default
        $this->assertNotEmpty($result['signatures']);
        $this->assertArrayHasKey('signature', $result['signatures']);
        $this->assertStringStartsWith('0xsigned_', $result['signatures']['signature']);
    }

    /**
     * Test options override builder defaults
     */
    public function testOptionsOverrideBuilderDefaults(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);
        $builder = $builder->withMemo('Default memo');

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0,
            ['memo' => 'Override memo']
        );

        // Assert options override builder defaults
        $this->assertEquals('Override memo', $result['metadata']['memo']);
    }

    /**
     * Test nonce propagation in metadata
     */
    public function testNoncePropagationInMetadata(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0,
            ['nonce' => 42]
        );

        $this->assertEquals(42, $result['metadata']['nonce']);
    }

    /**
     * Test automatic idempotency token generation (TASK-005)
     */
    public function testAutomaticIdempotencyTokenGeneration(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0
        );

        // Assert idempotency token is automatically generated
        $this->assertArrayHasKey('idempotencyToken', $result['metadata']);
        $this->assertIsString($result['metadata']['idempotencyToken']);
        $this->assertEquals(64, strlen($result['metadata']['idempotencyToken']));
    }

    /**
     * Test explicit idempotency token is preserved (TASK-005)
     */
    public function testExplicitIdempotencyTokenPreserved(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $customToken = hash('sha256', 'my-custom-token');

        $result = $builder->buildTransfer(
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
            1.0,
            ['idempotencyToken' => $customToken]
        );

        // Assert custom token is preserved
        $this->assertEquals($customToken, $result['metadata']['idempotencyToken']);
    }

    /**
     * Test idempotency token in contract call (TASK-005)
     */
    public function testIdempotencyTokenInContractCall(): void
    {
        $driver = new StubEthereumDriver();
        $builder = new TransactionBuilder($driver, $this->wallet);

        $result = $builder->buildContractCall(
            'transfer',
            ['to' => '0x123', 'value' => 1000]
        );

        // Assert idempotency token is present in contract calls too
        $this->assertArrayHasKey('idempotencyToken', $result['metadata']);
        $this->assertIsString($result['metadata']['idempotencyToken']);
    }
}

/**
 * MockWallet
 *
 * Mock wallet implementation for testing without exposing private keys.
 * Adheres to SEC-001 security requirement.
 */
class MockWallet implements WalletInterface
{
    public function getPublicKey(): string
    {
        return '0x04abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
    }

    public function sign(string $payload): string
    {
        // Return mock signature without exposing any private key material
        return '0xsigned_' . hash('sha256', $payload);
    }

    public function getAddress(): string
    {
        return '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0';
    }
}

/**
 * StubEthereumDriver
 *
 * Stub driver for Ethereum testing that implements BlockchainDriverInterface
 * and returns expected Ethereum transaction format.
 */
class StubEthereumDriver implements BlockchainDriverInterface
{
    private bool $connected = false;

    public function connect(array $config): void
    {
        $this->connected = true;
    }

    public function getBalance(string $address): float
    {
        return 1.0;
    }

    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        return '0x' . bin2hex(random_bytes(32));
    }

    public function getTransaction(string $hash): array
    {
        return [];
    }

    public function getBlock(int|string $blockIdentifier): array
    {
        return [];
    }

    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        return 21000;
    }

    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        return null;
    }

    public function getNetworkInfo(): ?array
    {
        return ['name' => 'ethereum', 'chainId' => 1];
    }
}

/**
 * StubSolanaDriver
 *
 * Stub driver for Solana testing that implements BlockchainDriverInterface
 * and returns expected Solana instruction format.
 */
class StubSolanaDriver implements BlockchainDriverInterface
{
    private bool $connected = false;

    public function connect(array $config): void
    {
        $this->connected = true;
    }

    public function getBalance(string $address): float
    {
        return 2.5;
    }

    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        return base64_encode(random_bytes(64));
    }

    public function getTransaction(string $hash): array
    {
        return [];
    }

    public function getBlock(int|string $blockIdentifier): array
    {
        return [];
    }

    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        return null;
    }

    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        return null;
    }

    public function getNetworkInfo(): ?array
    {
        return ['name' => 'solana', 'cluster' => 'mainnet-beta'];
    }
}
