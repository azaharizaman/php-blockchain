<?php

declare(strict_types=1);

namespace Blockchain\Storage;

/**
 * InMemoryIdempotencyStore
 *
 * Basic in-memory implementation of IdempotencyStoreInterface for development,
 * testing, and low-volume production scenarios. This implementation stores
 * tokens in a PHP array that persists only for the lifetime of the process.
 *
 * ## Use Cases
 *
 * - **Development/Testing**: Simple storage for unit tests and local development
 * - **Low-Volume Production**: Suitable for applications processing <1000 transactions per hour
 * - **Proof of Concept**: Quick setup without external dependencies
 *
 * ## Limitations
 *
 * - **No Persistence**: Data is lost when the PHP process terminates
 * - **No Sharing**: Cannot detect duplicates across multiple PHP processes/servers
 * - **Memory Bound**: Large token sets may consume significant memory
 * - **No Expiration**: Tokens remain in memory indefinitely (may cause memory leaks)
 *
 * For production systems with high volume or distributed processing, consider
 * implementing a Redis or database-backed store instead.
 *
 * ## Usage Examples
 *
 * ### Basic Usage
 *
 * ```php
 * use Blockchain\Storage\InMemoryIdempotencyStore;
 * use Blockchain\Operations\TransactionQueue;
 *
 * $store = new InMemoryIdempotencyStore();
 * $queue = new TransactionQueue(
 *     options: [],
 *     idempotencyStore: $store
 * );
 * ```
 *
 * ### Manual Token Management
 *
 * ```php
 * $store = new InMemoryIdempotencyStore();
 *
 * // Record a token
 * $store->record('token123', ['jobId' => 'tx-456']);
 *
 * // Check for duplicates
 * if ($store->has('token123')) {
 *     echo "Duplicate detected!";
 * }
 * ```
 *
 * ### With Expiration (Custom Extension)
 *
 * ```php
 * // This basic implementation doesn't support expiration
 * // For TTL support, implement a custom store:
 * class ExpiringInMemoryStore implements IdempotencyStoreInterface
 * {
 *     private array $store = [];
 *
 *     public function record(string $token, array $context): void
 *     {
 *         $this->store[$token] = [
 *             'context' => $context,
 *             'expiresAt' => time() + 3600
 *         ];
 *     }
 *
 *     public function has(string $token): bool
 *     {
 *         if (!isset($this->store[$token])) {
 *             return false;
 *         }
 *         if ($this->store[$token]['expiresAt'] < time()) {
 *             unset($this->store[$token]);
 *             return false;
 *         }
 *         return true;
 *     }
 * }
 * ```
 *
 * @package Blockchain\Storage
 */
class InMemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /**
     * In-memory storage for tokens
     *
     * Structure:
     * [
     *     'token123' => ['jobId' => 'tx-456', 'timestamp' => 1234567890],
     *     'token456' => ['jobId' => 'tx-789', 'timestamp' => 1234567891],
     * ]
     *
     * @var array<string,array<string,mixed>>
     */
    private array $store = [];

    /**
     * Record an idempotency token with context
     *
     * Stores the token and its associated context in memory. If the token
     * already exists, the context is updated with the new values.
     *
     * @param string $token The idempotency token to record
     * @param array<string,mixed> $context Non-sensitive metadata for debugging/auditing
     *
     * @return void
     */
    public function record(string $token, array $context): void
    {
        $this->store[$token] = $context;
    }

    /**
     * Check if a token exists in storage
     *
     * Returns true if the token has been previously recorded in this
     * process's memory, false otherwise.
     *
     * @param string $token The idempotency token to check
     *
     * @return bool True if token exists, false otherwise
     */
    public function has(string $token): bool
    {
        return isset($this->store[$token]);
    }

    /**
     * Get the stored context for a token (for testing/debugging)
     *
     * Returns the context array associated with a token, or null if the
     * token doesn't exist. This method is not part of the interface but
     * is useful for testing and debugging.
     *
     * @param string $token The idempotency token
     *
     * @return array<string,mixed>|null Context data or null if not found
     */
    public function getContext(string $token): ?array
    {
        return $this->store[$token] ?? null;
    }

    /**
     * Clear all stored tokens (for testing)
     *
     * Removes all tokens from memory. Useful for resetting state between
     * test cases.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->store = [];
    }

    /**
     * Get the count of stored tokens (for testing/monitoring)
     *
     * Returns the number of unique tokens currently stored in memory.
     *
     * @return int Number of tokens
     */
    public function count(): int
    {
        return count($this->store);
    }
}
