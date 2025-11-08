<?php

declare(strict_types=1);

namespace Blockchain\Operations;

/**
 * Idempotency
 *
 * Utility class for generating and managing idempotency tokens to prevent
 * duplicate transaction broadcasts across retries and batch dispatches.
 *
 * ## Key Responsibilities
 *
 * 1. **Secure Token Generation**: Creates cryptographically secure random tokens
 *    suitable for preventing replay attacks and duplicate processing.
 *
 * 2. **Deterministic Derivation**: Generates consistent tokens from hints
 *    (e.g., wallet address + payload fingerprint) for idempotent retry scenarios.
 *
 * 3. **SEC-001 Compliance**: Ensures tokens don't expose sensitive transaction
 *    payload data through proper hashing and encoding.
 *
 * ## Usage Examples
 *
 * ### Random Token Generation
 *
 * ```php
 * use Blockchain\Operations\Idempotency;
 *
 * // Generate a random token for one-time use
 * $token = Idempotency::generate();
 * // Returns: "a1b2c3d4e5f6..."
 * ```
 *
 * ### Deterministic Token with Hint
 *
 * ```php
 * // Generate deterministic token from hint
 * $hint = $walletAddress . '|' . json_encode($payload);
 * $token = Idempotency::generate($hint);
 * // Same hint will always produce the same token
 * ```
 *
 * ### Integration with TransactionBuilder
 *
 * ```php
 * $metadata = [
 *     'idempotencyToken' => Idempotency::generate(),
 *     // ... other metadata
 * ];
 * ```
 *
 * ## Security Considerations (SEC-001)
 *
 * - Random tokens use cryptographically secure random bytes (random_bytes)
 * - Deterministic tokens use SHA-256 hashing to prevent payload exposure
 * - Tokens are hex-encoded for safe transmission and storage
 * - No sensitive data is logged or exposed through token generation
 *
 * @package Blockchain\Operations
 */
class Idempotency
{
    /**
     * Token length in bytes (before hex encoding)
     */
    private const TOKEN_BYTES = 32;

    /**
     * Generate an idempotency token
     *
     * Generates either a cryptographically secure random token or a deterministic
     * token derived from the provided hint. Random tokens are suitable for
     * one-time operations, while deterministic tokens enable consistent
     * identification across retries.
     *
     * ## Random Generation (no hint)
     *
     * Uses PHP's `random_bytes()` function to generate cryptographically secure
     * random data. The result is hex-encoded for safe transmission.
     *
     * ```php
     * $token = Idempotency::generate();
     * // Example: "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef12345"
     * ```
     *
     * ## Deterministic Generation (with hint)
     *
     * Creates a consistent token by hashing the hint using SHA-256. The same
     * hint will always produce the same token, enabling deduplication across
     * retries and batch operations.
     *
     * ```php
     * $hint = $walletAddress . '|' . json_encode($payload);
     * $token = Idempotency::generate($hint);
     * // Same hint = same token every time
     * ```
     *
     * **Security Note**: When using hints, ensure they contain sufficient
     * entropy to prevent collision attacks. Recommended hint format:
     * `"{wallet_address}|{timestamp}|{nonce}|{payload_hash}"`
     *
     * @param string|null $hint Optional hint for deterministic generation.
     *                          Should be a string combining wallet address,
     *                          timestamp, nonce, and payload fingerprint.
     *
     * @return string Hex-encoded idempotency token (64 characters for 32 bytes)
     *
     * @throws \Exception If random_bytes() fails (extremely rare)
     */
    public static function generate(?string $hint = null): string
    {
        if ($hint !== null) {
            // Deterministic generation: hash the hint
            // SHA-256 produces 32 bytes of output
            return hash('sha256', $hint);
        }

        // Random generation: use cryptographically secure random bytes
        $randomBytes = random_bytes(self::TOKEN_BYTES);

        // Convert to hex string for safe transmission/storage
        return bin2hex($randomBytes);
    }
}
