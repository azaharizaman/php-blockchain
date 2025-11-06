<?php

declare(strict_types=1);

namespace Blockchain\Contracts;

/**
 * BlockchainDriverInterface
 *
 * Main contract interface that all blockchain drivers must implement.
 * This interface defines the core methods for blockchain interactions across
 * different blockchain networks (both EVM and non-EVM chains).
 *
 * Implementations of this interface should handle network-specific details
 * while providing a consistent API for blockchain operations such as:
 * - Connecting to blockchain networks
 * - Querying account balances
 * - Sending transactions
 * - Retrieving transaction and block information
 * - Estimating gas/fees
 * - Managing token balances
 *
 * @package Blockchain\Contracts
 */
interface BlockchainDriverInterface
{
    /**
     * Connect to the blockchain network with the given configuration.
     *
     * This method initializes the connection to the blockchain network using
     * the provided configuration parameters. The exact configuration structure
     * may vary by driver implementation, but common parameters include:
     * - endpoint: The RPC endpoint URL
     * - timeout: Request timeout in seconds
     * - api_key: Optional API key for authenticated requests
     * - network: Network identifier (mainnet, testnet, etc.)
     *
     * @param array<string,mixed> $config Configuration array containing connection parameters
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If required configuration is missing or invalid
     *
     * @return void
     */
    public function connect(array $config): void;

    /**
     * Get the balance of an address.
     *
     * Retrieves the native token balance for the specified blockchain address.
     * The balance is returned as a float representing the amount in the native
     * token's standard unit (e.g., ETH for Ethereum, SOL for Solana).
     *
     * @param string $address The blockchain address to query
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the balance query fails
     *
     * @return float The balance in native token units
     */
    public function getBalance(string $address): float;

    /**
     * Send a transaction from one address to another.
     *
     * Creates and broadcasts a transaction on the blockchain. The transaction
     * transfers the specified amount of native tokens from the sender to the
     * recipient address.
     *
     * @param string $from The sender's blockchain address
     * @param string $to The recipient's blockchain address
     * @param float $amount The amount to transfer in native token units
     * @param array<string,mixed> $options Additional transaction options (e.g., gas limit, nonce, memo)
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the transaction fails to send
     *
     * @return string The transaction hash/signature
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string;

    /**
     * Get transaction details by hash.
     *
     * Retrieves comprehensive information about a specific transaction using
     * its unique hash or signature. The returned array structure varies by
     * blockchain but typically includes:
     * - status: Transaction status (confirmed, pending, failed)
     * - blockNumber: Block containing the transaction
     * - from: Sender address
     * - to: Recipient address
     * - value: Transaction amount
     * - fee: Transaction fee paid
     *
     * @param string $hash The transaction hash or signature
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the transaction query fails
     *
     * @return array<string,mixed> Transaction details
     */
    public function getTransaction(string $hash): array;

    /**
     * Get block information by number or hash.
     *
     * Retrieves comprehensive information about a specific block using its
     * block number or block hash. The returned array structure varies by
     * blockchain but typically includes:
     * - number: Block number/height
     * - hash: Block hash
     * - timestamp: Block creation timestamp
     * - transactions: List of transaction hashes in the block
     * - parentHash: Hash of the previous block
     *
     * @param int|string $blockIdentifier The block number (int) or block hash (string)
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the block query fails
     *
     * @return array<string,mixed> Block information
     */
    public function getBlock(int|string $blockIdentifier): array;

    /**
     * Estimate gas for a transaction (optional).
     *
     * Estimates the gas/compute units required for executing a transaction.
     * This method is primarily used for EVM-compatible chains. Some blockchains
     * may not support gas estimation and will return null.
     *
     * @param string $from The sender's blockchain address
     * @param string $to The recipient's blockchain address
     * @param float $amount The amount to transfer in native token units
     * @param array<string,mixed> $options Additional transaction options (e.g., data, contract call)
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the gas estimation fails
     *
     * @return int|null Estimated gas units, or null if not supported by the blockchain
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int;

    /**
     * Get token balance for a specific token (optional).
     *
     * Retrieves the balance of a specific token (e.g., ERC-20, SPL) for the
     * given address.
     *
     * Returns null if the blockchain or driver does not support token balance queries.
     * Returns 0.0 if the address has no balance of the specified token.
     *
     * @param string $address The wallet address to query
     * @param string $tokenAddress The token contract/mint address
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the token balance query fails
     *
     * @return float|null The token balance, or null if not supported
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float;

    /**
     * Get network information (optional).
     *
     * Retrieves general information about the blockchain network. The returned
     * array structure varies by blockchain but may include:
     * - chainId: Network/chain identifier
     * - blockHeight: Current block height
     * - networkVersion: Network protocol version
     * - epoch: Current epoch (for applicable blockchains)
     *
     * Returns null if the blockchain or driver does not support network info queries.
     *
     * @throws \Blockchain\Exceptions\ConfigurationException If the driver is not connected
     * @throws \Exception If the network info query fails
     *
     * @return array<string,mixed>|null Network information, or null if not supported
     */
    public function getNetworkInfo(): ?array;
}
