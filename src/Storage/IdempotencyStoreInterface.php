<?php

declare(strict_types=1);

namespace Blockchain\Storage;

/**
 * IdempotencyStoreInterface
 *
 * Contract for idempotency token storage adapters that enable duplicate
 * transaction detection across retries and batch dispatches. Implementations
 * may wrap Redis, SQL databases, or in-memory stores.
 *
 * ## Purpose
 *
 * This interface provides a minimal abstraction for persisting and querying
 * idempotency tokens without forcing a hard dependency on specific storage
 * backends. This allows applications to choose appropriate storage based on
 * their infrastructure and scalability requirements.
 *
 * ## Implementation Patterns
 *
 * ### Redis Implementation
 *
 * ```php
 * class RedisIdempotencyStore implements IdempotencyStoreInterface
 * {
 *     private Redis $redis;
 *
 *     public function record(string $token, array $context): void
 *     {
 *         $key = "idempotency:{$token}";
 *         $ttl = 86400; // 24 hours
 *         $this->redis->setex($key, $ttl, json_encode($context));
 *     }
 *
 *     public function has(string $token): bool
 *     {
 *         return $this->redis->exists("idempotency:{$token}") > 0;
 *     }
 * }
 * ```
 *
 * ### Database Implementation
 *
 * ```php
 * class DatabaseIdempotencyStore implements IdempotencyStoreInterface
 * {
 *     private PDO $pdo;
 *
 *     public function record(string $token, array $context): void
 *     {
 *         $stmt = $this->pdo->prepare(
 *             'INSERT INTO idempotency_tokens (token, context, created_at)
 *              VALUES (?, ?, NOW())
 *              ON DUPLICATE KEY UPDATE context = VALUES(context)'
 *         );
 *         $stmt->execute([$token, json_encode($context)]);
 *     }
 *
 *     public function has(string $token): bool
 *     {
 *         $stmt = $this->pdo->prepare(
 *             'SELECT 1 FROM idempotency_tokens WHERE token = ? LIMIT 1'
 *         );
 *         $stmt->execute([$token]);
 *         return $stmt->fetch() !== false;
 *     }
 * }
 * ```
 *
 * ## Security Considerations (SEC-001)
 *
 * - Context data MUST NOT contain sensitive payload information
 * - Only metadata (job ID, timestamp, wallet address) should be stored
 * - Implementations should consider token expiration to prevent unbounded growth
 * - Token values may be stored in plain text (they're already hashed/random)
 *
 * ## Performance Considerations
 *
 * - The `has()` method is called frequently during queue operations
 * - Implementations should optimize for read performance
 * - Consider using indices on token columns for database implementations
 * - Redis implementations should use appropriate TTL values
 *
 * @package Blockchain\Storage
 */
interface IdempotencyStoreInterface
{
    /**
     * Record an idempotency token with associated context
     *
     * Persists a token to the storage backend along with metadata that may
     * be useful for debugging or auditing. If the token already exists,
     * implementations MAY update the context or silently ignore the duplicate.
     *
     * **Security Note (SEC-001)**: The context array MUST NOT contain sensitive
     * transaction payload data. Only store non-sensitive metadata such as:
     * - Job ID
     * - Wallet address (public)
     * - Timestamp
     * - Attempt count
     * - Transaction hash (after broadcast)
     *
     * ## Context Example
     *
     * ```php
     * $context = [
     *     'jobId' => 'tx-12345',
     *     'walletAddress' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
     *     'timestamp' => time(),
     *     'attempts' => 1,
     * ];
     * $store->record($token, $context);
     * ```
     *
     * @param string $token The idempotency token to record
     * @param array<string,mixed> $context Non-sensitive metadata for debugging/auditing
     *
     * @return void
     *
     * @throws \RuntimeException If storage operation fails
     */
    public function record(string $token, array $context): void;

    /**
     * Check if an idempotency token exists in storage
     *
     * Returns true if the token has been previously recorded, false otherwise.
     * This method is used to detect duplicate transactions before enqueueing
     * or broadcasting them.
     *
     * ## Usage in Queue
     *
     * ```php
     * public function enqueue(TransactionJob $job): void
     * {
     *     $token = $job->getIdempotencyToken();
     *     if ($token !== null && $this->store->has($token)) {
     *         // Skip duplicate
     *         return;
     *     }
     *     // Proceed with enqueue
     *     $this->store->record($token, ['jobId' => $job->getId()]);
     * }
     * ```
     *
     * @param string $token The idempotency token to check
     *
     * @return bool True if token exists, false otherwise
     *
     * @throws \RuntimeException If storage operation fails
     */
    public function has(string $token): bool;
}
