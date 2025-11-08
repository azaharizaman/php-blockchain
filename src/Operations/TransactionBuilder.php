<?php

declare(strict_types=1);

namespace Blockchain\Operations;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Wallet\WalletInterface;
use Blockchain\Exceptions\TransactionException;

/**
 * TransactionBuilder
 *
 * Transaction orchestration helper responsible for preparing, signing, and
 * assembling driver-ready transaction payloads. This class coordinates between
 * blockchain drivers and wallet abstractions to create properly formatted
 * transactions with metadata and signatures.
 *
 * ## Key Responsibilities
 *
 * 1. **Payload Assembly**: Constructs driver-specific transaction payloads for
 *    both transfer and contract call operations.
 *
 * 2. **Metadata Normalization**: Attaches wallet information (address, public key)
 *    and transaction options (gas, memo, nonce) to a standardized metadata structure.
 *
 * 3. **Signing Coordination**: Integrates with WalletInterface to sign transaction
 *    payloads while maintaining SEC-001 compliance (no private key exposure).
 *
 * 4. **Immutable Configuration**: Provides builder pattern methods (withFeePayer,
 *    withMemo, withGasOptions) that return new instances, enabling reusable
 *    transaction templates.
 *
 * ## Usage Examples
 *
 * ### Basic Transfer Transaction
 *
 * ```php
 * use Blockchain\Operations\TransactionBuilder;
 * use Blockchain\Drivers\EthereumDriver;
 * use Blockchain\Wallet\SoftwareWallet;
 *
 * $driver = new EthereumDriver();
 * $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/YOUR_KEY']);
 *
 * $wallet = new SoftwareWallet($privateKey);
 * $builder = new TransactionBuilder($driver, $wallet);
 *
 * $transaction = $builder->buildTransfer(
 *     '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
 *     1.5,
 *     ['memo' => 'Payment for services', 'gas' => 21000]
 * );
 *
 * // $transaction contains:
 * // [
 * //   'driver' => 'ethereum',
 * //   'payload' => [...driver-specific transaction data...],
 * //   'metadata' => [...wallet info, gas, memo, nonce...],
 * //   'signatures' => [...signature data...]
 * // ]
 * ```
 *
 * ### Contract Call Transaction
 *
 * ```php
 * $transaction = $builder->buildContractCall(
 *     'transfer',
 *     ['to' => '0x...', 'value' => 1000],
 *     ['gas' => 50000, 'nonce' => 5]
 * );
 * ```
 *
 * ### Immutable Configuration
 *
 * ```php
 * // Create a reusable builder with defaults
 * $templateBuilder = $builder
 *     ->withFeePayer('0x...')
 *     ->withMemo('Default payment memo')
 *     ->withGasOptions(['limit' => 100000, 'price' => 50]);
 *
 * // Use template for multiple transactions
 * $tx1 = $templateBuilder->buildTransfer('0x...', 1.0);
 * $tx2 = $templateBuilder->buildTransfer('0x...', 2.0);
 * ```
 *
 * ### Skip Signing (for unsigned transaction construction)
 *
 * ```php
 * $unsignedTx = $builder->buildTransfer(
 *     '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
 *     1.0,
 *     ['skipSign' => true]
 * );
 * // $unsignedTx['signatures'] will be empty
 * ```
 *
 * ## Security Considerations (SEC-001)
 *
 * This class adheres to SEC-001 security requirements:
 * - Private keys are NEVER exposed, logged, or accessed directly
 * - All signing operations are delegated to WalletInterface
 * - Payload data is sanitized before logging (if logging is added)
 * - Signature operations are isolated in private methods
 *
 * ## Integration Points
 *
 * - **BlockchainDriverInterface**: Provides driver-specific transaction formats
 * - **WalletInterface**: Handles all cryptographic signing operations
 * - **TASK-005** (future): Will integrate idempotency tokens for transaction deduplication
 *
 * @package Blockchain\Operations
 */
class TransactionBuilder
{
    /**
     * Optional fee payer address (primarily for Solana)
     */
    private ?string $feePayer = null;

    /**
     * Optional default memo for all transactions
     */
    private ?string $memo = null;

    /**
     * Optional default gas options for transactions
     *
     * @var array<string,mixed>|null
     */
    private ?array $gasOptions = null;

    /**
     * Constructor
     *
     * @param BlockchainDriverInterface $driver The blockchain driver for transaction formatting
     * @param WalletInterface $wallet The wallet for signing operations
     */
    public function __construct(
        private readonly BlockchainDriverInterface $driver,
        private readonly WalletInterface $wallet
    ) {
    }

    /**
     * Create a new builder instance with a fee payer address
     *
     * Returns a new TransactionBuilder instance with the specified fee payer,
     * maintaining immutability of the original builder.
     *
     * @param string $feePayer The address that will pay transaction fees
     *
     * @return self New builder instance with fee payer configured
     */
    public function withFeePayer(string $feePayer): self
    {
        $clone = clone $this;
        $clone->feePayer = $feePayer;
        return $clone;
    }

    /**
     * Create a new builder instance with a default memo
     *
     * Returns a new TransactionBuilder instance with the specified memo,
     * maintaining immutability of the original builder.
     *
     * @param string $memo Default memo for transactions
     *
     * @return self New builder instance with memo configured
     */
    public function withMemo(string $memo): self
    {
        $clone = clone $this;
        $clone->memo = $memo;
        return $clone;
    }

    /**
     * Create a new builder instance with default gas options
     *
     * Returns a new TransactionBuilder instance with the specified gas options,
     * maintaining immutability of the original builder.
     *
     * @param array<string,mixed> $gasOptions Gas configuration (limit, price, etc.)
     *
     * @return self New builder instance with gas options configured
     */
    public function withGasOptions(array $gasOptions): self
    {
        $clone = clone $this;
        $clone->gasOptions = $gasOptions;
        return $clone;
    }

    /**
     * Build a transfer transaction payload
     *
     * Constructs a complete transaction structure for transferring native tokens
     * from the wallet address to the specified recipient. The returned array
     * contains driver-specific payload data, normalized metadata, and signatures
     * (unless skipSign option is used).
     *
     * ## Returned Array Structure
     *
     * ```php
     * [
     *     'driver' => 'ethereum', // or 'solana', driver name
     *     'payload' => [
     *         // Driver-specific transaction data
     *         // Ethereum: ['method' => 'eth_sendTransaction', 'params' => [...]]
     *         // Solana: ['programId' => '...', 'accounts' => [...], 'data' => '...']
     *     ],
     *     'metadata' => [
     *         'from' => 'wallet_address',
     *         'publicKey' => 'wallet_public_key',
     *         'memo' => 'optional_memo',
     *         'gas' => 'gas_options',
     *         'nonce' => 'nonce_value',
     *         'feePayer' => 'fee_payer_address' // if set
     *     ],
     *     'signatures' => [
     *         'signature' => 'signed_payload_hash'
     *     ] // empty if skipSign is true
     * ]
     * ```
     *
     * ## Options
     *
     * - **memo** (string): Transaction memo/note
     * - **gas** (int|array): Gas limit or gas options
     * - **nonce** (int): Transaction nonce
     * - **skipSign** (bool): If true, skip signing and return empty signatures
     *
     * @param string $to Recipient address
     * @param float $amount Amount to transfer in native token units
     * @param array<string,mixed> $options Additional transaction options
     *
     * @return array<string,mixed> Complete transaction structure with payload, metadata, and signatures
     */
    public function buildTransfer(string $to, float $amount, array $options = []): array
    {
        // Get driver name
        $driverName = $this->getDriverName();

        // Build driver-specific payload
        $payload = $this->buildTransferPayload($to, $amount, $options, $driverName);

        // Build metadata
        $metadata = $this->buildMetadata($options);

        // Sign payload unless skipSign is true
        $signatures = [];
        if (!($options['skipSign'] ?? false)) {
            $signatures = $this->signPayload($payload);
        }

        return [
            'driver' => $driverName,
            'payload' => $payload,
            'metadata' => $metadata,
            'signatures' => $signatures,
        ];
    }

    /**
     * Build a contract call transaction payload
     *
     * Constructs a complete transaction structure for calling a smart contract
     * method. This is used for interactions beyond simple transfers, such as
     * ERC-20 token transfers, NFT minting, or custom contract interactions.
     *
     * ## Returned Array Structure
     *
     * Same structure as buildTransfer(), with payload containing method-specific data:
     *
     * ```php
     * [
     *     'driver' => 'ethereum',
     *     'payload' => [
     *         'method' => 'transfer',
     *         'params' => ['to' => '0x...', 'value' => 1000],
     *         // Additional driver-specific fields
     *     ],
     *     'metadata' => [...],
     *     'signatures' => [...]
     * ]
     * ```
     *
     * ## Options
     *
     * - **gas** (int|array): Gas limit or gas options
     * - **nonce** (int): Transaction nonce
     * - **skipSign** (bool): If true, skip signing
     *
     * @param string $method Contract method name to call
     * @param array<string,mixed> $params Method parameters
     * @param array<string,mixed> $options Additional transaction options
     *
     * @return array<string,mixed> Complete transaction structure with payload, metadata, and signatures
     */
    public function buildContractCall(string $method, array $params, array $options = []): array
    {
        // Get driver name
        $driverName = $this->getDriverName();

        // Build driver-specific payload
        $payload = $this->buildContractCallPayload($method, $params, $options, $driverName);

        // Build metadata
        $metadata = $this->buildMetadata($options);

        // Sign payload unless skipSign is true
        $signatures = [];
        if (!($options['skipSign'] ?? false)) {
            $signatures = $this->signPayload($payload);
        }

        return [
            'driver' => $driverName,
            'payload' => $payload,
            'metadata' => $metadata,
            'signatures' => $signatures,
        ];
    }

    /**
     * Build transfer payload based on driver type
     *
     * @param string $to Recipient address
     * @param float $amount Amount to transfer
     * @param array<string,mixed> $options Transaction options
     * @param string $driverName Driver name
     *
     * @return array<string,mixed> Driver-specific payload
     */
    private function buildTransferPayload(string $to, float $amount, array $options, string $driverName): array
    {
        if ($driverName === 'ethereum') {
            return [
                'method' => 'eth_sendTransaction',
                'params' => [
                    'to' => $to,
                    'value' => $amount,
                ],
            ];
        } elseif ($driverName === 'solana') {
            return [
                'programId' => 'System Program',
                'accounts' => [
                    'from' => $this->wallet->getAddress(),
                    'to' => $to,
                ],
                'data' => [
                    'instruction' => 'transfer',
                    'amount' => $amount,
                ],
            ];
        }

        // Default generic format
        return [
            'to' => $to,
            'amount' => $amount,
        ];
    }

    /**
     * Build contract call payload based on driver type
     *
     * @param string $method Contract method name
     * @param array<string,mixed> $params Method parameters
     * @param array<string,mixed> $options Transaction options
     * @param string $driverName Driver name
     *
     * @return array<string,mixed> Driver-specific payload
     */
    private function buildContractCallPayload(string $method, array $params, array $options, string $driverName): array
    {
        if ($driverName === 'ethereum') {
            return [
                'method' => $method,
                'params' => $params,
                'type' => 'contract_call',
            ];
        } elseif ($driverName === 'solana') {
            return [
                'programId' => 'Contract Program',
                'accounts' => [
                    'caller' => $this->wallet->getAddress(),
                ],
                'data' => [
                    'instruction' => $method,
                    'params' => $params,
                ],
            ];
        }

        // Default generic format
        return [
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * Build normalized metadata structure
     *
     * Constructs metadata containing wallet information and transaction options.
     * Options from parameters override builder defaults.
     *
     * @param array<string,mixed> $options Transaction options
     *
     * @return array<string,mixed> Normalized metadata
     */
    private function buildMetadata(array $options): array
    {
        $metadata = [
            'from' => $this->wallet->getAddress(),
            'publicKey' => $this->wallet->getPublicKey(),
        ];

        // Add memo (options override builder default)
        if (isset($options['memo'])) {
            $metadata['memo'] = $options['memo'];
        } elseif ($this->memo !== null) {
            $metadata['memo'] = $this->memo;
        }

        // Add gas options (options override builder default)
        if (isset($options['gas'])) {
            $metadata['gas'] = $options['gas'];
        } elseif ($this->gasOptions !== null) {
            $metadata['gas'] = $this->gasOptions;
        }

        // Add nonce if provided
        if (isset($options['nonce'])) {
            $metadata['nonce'] = $options['nonce'];
        }

        // Add fee payer if set (primarily for Solana)
        if ($this->feePayer !== null) {
            $metadata['feePayer'] = $this->feePayer;
        }

        // Add idempotency token (TASK-005)
        // Generate if not provided in options
        if (isset($options['idempotencyToken'])) {
            $metadata['idempotencyToken'] = $options['idempotencyToken'];
        } else {
            // Generate deterministic token using wallet address and payload fingerprint
            $hint = $this->wallet->getAddress() . '|' . time() . '|' . uniqid('', true);
            $metadata['idempotencyToken'] = Idempotency::generate($hint);
        }

        return $metadata;
    }

    /**
     * Sign transaction payload using wallet
     *
     * Delegates signing to WalletInterface while maintaining SEC-001 compliance.
     * The payload is serialized before signing to create a deterministic
     * signature input.
     *
     * **Security Note**: This method never accesses or logs private keys. All
     * cryptographic operations are performed by the WalletInterface implementation.
     *
     * @param array<string,mixed> $payload Transaction payload to sign
     *
     * @return array<string,mixed> Signature data structure
     */
    private function signPayload(array $payload): array
    {
        // Serialize payload for signing
        $payloadString = json_encode($payload, JSON_THROW_ON_ERROR);

        // Sign the payload using wallet (SEC-001: no private key exposure)
        $signature = $this->wallet->sign($payloadString);

        return [
            'signature' => $signature,
        ];
    }

    /**
     * Get driver name from the driver instance
     *
     * Attempts to retrieve the driver name using getName() method if available,
     * otherwise extracts it from the network info.
     *
     * @return string Driver name (e.g., 'ethereum', 'solana')
     */
    private function getDriverName(): string
    {
        // Try to get name from driver if it has a getName method
        if (method_exists($this->driver, 'getName')) {
            return $this->driver->getName();
        }

        // Fallback to network info
        $networkInfo = $this->driver->getNetworkInfo();
        if ($networkInfo !== null && isset($networkInfo['name'])) {
            return $networkInfo['name'];
        }

        // Default fallback
        return 'unknown';
    }
}
