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
     * @param array<string,mixed> $config
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
     * @param array<string,mixed> $options
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string;

    /**
     * Get transaction details by hash.
     *
     * @return array<string,mixed>
     */
    public function getTransaction(string $hash): array;

    /**
     * Get block information by number or hash.
     *
     * @return array<string,mixed>
     */
    public function getBlock(int|string $blockIdentifier): array;

    /**
     * Estimate gas for a transaction (optional).
     *
     * @param array<string,mixed> $options
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
     * @return array<string,mixed>|null
     */
    public function getNetworkInfo(): ?array;
}
