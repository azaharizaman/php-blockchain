<?php

declare(strict_types=1);

namespace Blockchain\Security;

use Blockchain\Exceptions\SecurityException;

/**
 * SecretProviderInterface
 *
 * Contract interface for secret providers that retrieve sensitive configuration
 * values from secure sources such as environment variables, key vaults, or HSMs.
 *
 * Implementations must ensure:
 * - Secrets are retrieved securely without logging
 * - Appropriate exceptions are thrown for missing or inaccessible secrets
 * - Memory handling minimizes plaintext exposure where possible
 *
 * @package Blockchain\Security
 */
interface SecretProviderInterface
{
    /**
     * Retrieve a secret value by name.
     *
     * @param string $name The name/key of the secret to retrieve
     *
     * @throws SecurityException If the secret cannot be retrieved or doesn't exist
     *
     * @return string The secret value
     */
    public function get(string $name): string;

    /**
     * Check if a secret exists without retrieving its value.
     *
     * @param string $name The name/key of the secret to check
     *
     * @return bool True if the secret exists, false otherwise
     */
    public function has(string $name): bool;
}
