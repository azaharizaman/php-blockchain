<?php

declare(strict_types=1);

namespace Blockchain\Contracts;

interface BlockchainDriverInterface
{
    /**
     * Connect to the blockchain network with the given configuration.
     */
    public function connect(array $config): void;

    /**
     * Get the balance of an address.
     */
    public function getBalance(string $address): float;

    /**
     * Send a transaction from one address to another.
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string;

    /**
     * Get transaction details by hash.
     */
    public function getTransaction(string $txHash): array;

    /**
     * Get block information by number or hash.
     */
    public function getBlock(int|string $blockNumber): array;

    /**
     * Estimate gas for a transaction (optional).
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int;

    /**
     * Get token balance for a specific token (optional).
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float;

    /**
     * Get network information (optional).
     */
    public function getNetworkInfo(): ?array;
}