<?php

declare(strict_types=1);

namespace Blockchain\Wallet;

use Blockchain\Exceptions\TransactionException;

/**
 * WalletInterface
 *
 * Defines the contract for wallet implementations that manage cryptographic keys
 * and signing operations for blockchain transactions. This interface is designed
 * to support both software-based wallets and Hardware Security Module (HSM) integrations,
 * providing a unified abstraction for key management across different security models.
 *
 * ## Key Responsibilities
 *
 * 1. **Public Key Access**: Provide access to the wallet's public key for verification
 *    and address derivation purposes.
 *
 * 2. **Transaction Signing**: Sign transaction payloads (raw bytes) prepared by
 *    transaction builders, supporting both hot wallets and cold storage solutions.
 *
 * 3. **Address Derivation**: Return the blockchain-specific address associated with
 *    this wallet for receiving funds and transaction operations.
 *
 * ## Security Considerations (SEC-001)
 *
 * **CRITICAL SECURITY REQUIREMENT**: Implementations of this interface MUST adhere to
 * the following security guidelines:
 *
 * - **Private Key Protection**: Private keys MUST NEVER be exposed through any public
 *   methods, logged to any output (files, console, monitoring systems), or serialized
 *   in any form. Private key material must remain internal to the implementation.
 *
 * - **Secure Key Handling**: All private key operations must be performed in secure
 *   memory contexts. Consider using:
 *   - Hardware Security Modules (HSMs) for production environments
 *   - Secure enclaves or trusted execution environments
 *   - Memory encryption and secure erasure after use
 *
 * - **HSM Compatibility**: This interface is designed to work seamlessly with HSM
 *   implementations where private keys never leave the secure hardware. The signing
 *   operation should delegate to the HSM's signing capabilities.
 *
 * - **Audit Trail**: While private keys must not be logged, signing operations should
 *   be auditable through metadata (timestamps, request IDs) without exposing key material.
 *
 * ## Integration with Transaction Workflows
 *
 * This interface is designed to integrate with:
 * - **Transaction Builders**: Accept raw transaction payloads for signing
 * - **Transaction Queues**: Support asynchronous signing in batched operations
 * - **Batch Processors**: Enable efficient signing of multiple transactions
 * - **Driver Implementations**: Provide blockchain-specific signing across different networks
 *
 * ## Expected Return Formats
 *
 * - **getPublicKey()**: Returns public key in format appropriate for the blockchain
 *   (e.g., hex-encoded for EVM chains, base58 for Solana)
 *
 * - **sign()**: Returns cryptographic signature in format expected by the target
 *   blockchain (e.g., hex-encoded ECDSA signature, base58-encoded Ed25519 signature)
 *
 * - **getAddress()**: Returns blockchain-specific address format (e.g., 0x-prefixed
 *   for Ethereum, base58 for Solana, bech32 for Bitcoin/Cosmos)
 *
 * @package Blockchain\Wallet
 *
 * @example Software Wallet Implementation
 * ```php
 * use Blockchain\Wallet\WalletInterface;
 *
 * class SoftwareWallet implements WalletInterface
 * {
 *     private string $privateKey; // NEVER expose or log this
 *
 *     public function getPublicKey(): string
 *     {
 *         // Derive public key from private key
 *         return $this->derivePublicKey();
 *     }
 *
 *     public function sign(string $payload): string
 *     {
 *         // Sign payload with private key
 *         return $this->cryptoSign($payload);
 *     }
 *
 *     public function getAddress(): string
 *     {
 *         // Derive address from public key
 *         return $this->deriveAddress();
 *     }
 * }
 * ```
 *
 * @example HSM Wallet Implementation
 * ```php
 * use Blockchain\Wallet\WalletInterface;
 *
 * class HsmWallet implements WalletInterface
 * {
 *     private HsmClient $hsmClient;
 *     private string $keyIdentifier;
 *
 *     public function getPublicKey(): string
 *     {
 *         // Retrieve public key from HSM
 *         return $this->hsmClient->getPublicKey($this->keyIdentifier);
 *     }
 *
 *     public function sign(string $payload): string
 *     {
 *         // Delegate signing to HSM - private key never leaves HSM
 *         return $this->hsmClient->sign($this->keyIdentifier, $payload);
 *     }
 *
 *     public function getAddress(): string
 *     {
 *         // Derive address from public key
 *         return $this->deriveAddress($this->getPublicKey());
 *     }
 * }
 * ```
 */
interface WalletInterface
{
    /**
     * Retrieve the public key associated with this wallet.
     *
     * Returns the public key in a format appropriate for the target blockchain network.
     * The exact encoding (hex, base58, etc.) depends on the blockchain requirements.
     *
     * For EVM-compatible chains (Ethereum, Polygon, BSC):
     * - Returns hex-encoded public key (64 bytes / 128 hex characters)
     * - May be compressed or uncompressed depending on implementation
     *
     * For Solana:
     * - Returns base58-encoded Ed25519 public key (32 bytes)
     *
     * For Bitcoin/Bitcoin-like chains:
     * - Returns hex or base58-encoded public key
     * - Format depends on address type (P2PKH, P2SH, bech32)
     *
     * @return string Public key in blockchain-appropriate format (hex, base58, etc.)
     */
    public function getPublicKey(): string;

    /**
     * Sign a transaction payload with the wallet's private key.
     *
     * This method receives raw bytes (typically a transaction hash or serialized
     * transaction data) from transaction builders and returns a cryptographic signature.
     * The signing operation must be performed securely without exposing the private key.
     *
     * **Security Note**: Implementations must never log or expose the private key during
     * signing operations. For HSM implementations, this method should delegate to the
     * HSM's signing capabilities, ensuring the private key never leaves secure hardware.
     *
     * **Payload Format**: The $payload parameter contains raw bytes prepared by transaction
     * builders. This may be:
     * - A transaction hash (Keccak-256 for Ethereum)
     * - Serialized transaction data ready for signing
     * - A message digest specific to the blockchain protocol
     *
     * **Signature Format**: The returned signature format depends on the blockchain:
     * - Ethereum: Hex-encoded ECDSA signature with v, r, s components (65 bytes)
     * - Solana: Base58-encoded Ed25519 signature (64 bytes)
     * - Bitcoin: DER-encoded ECDSA signature
     *
     * @param string $payload Raw bytes to sign (transaction hash, message digest, or
     *                        serialized transaction data from transaction builders)
     *
     * @throws TransactionException If signing operation fails due to:
     *                               - Invalid payload format
     *                               - Cryptographic errors
     *                               - HSM communication failures
     *                               - Insufficient permissions
     *
     * @return string Cryptographic signature in blockchain-appropriate format
     */
    public function sign(string $payload): string;

    /**
     * Retrieve the blockchain address associated with this wallet.
     *
     * Returns the address in the format expected by the target blockchain network.
     * The address is derived from the public key according to blockchain-specific rules.
     *
     * **Address Formats by Blockchain**:
     *
     * Ethereum & EVM-compatible chains:
     * - Returns checksummed address (0x-prefixed, 40 hex characters)
     * - Example: 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0
     *
     * Solana:
     * - Returns base58-encoded address (32-44 characters)
     * - Example: 7EqQdEUFxDcPkE7cGNL9UuZkxFJDLZPrXmKNMqAJBW4K
     *
     * Bitcoin:
     * - Returns base58-encoded (P2PKH, P2SH) or bech32-encoded (SegWit) address
     * - Examples:
     *   - P2PKH: 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa
     *   - Bech32: bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4
     *
     * Cosmos SDK chains:
     * - Returns bech32-encoded address with chain-specific prefix
     * - Example: cosmos1vls7h6j3j9d2g5lnqz7h3c9c8yggqg6s0c5vvj
     *
     * @return string Blockchain-specific address for this wallet
     */
    public function getAddress(): string;
}
