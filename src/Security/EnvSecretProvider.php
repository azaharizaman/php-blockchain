<?php

declare(strict_types=1);

namespace Blockchain\Security;

use Blockchain\Exceptions\SecurityException;

/**
 * EnvSecretProvider
 *
 * Retrieves secrets from environment variables. This provider is suitable
 * for development and production environments where secrets are injected
 * via environment configuration.
 *
 * Usage:
 * ```php
 * $provider = new EnvSecretProvider();
 * $apiKey = $provider->get('BLOCKCHAIN_API_KEY');
 * ```
 *
 * Security considerations:
 * - Environment variables should be set with appropriate permissions
 * - Avoid logging retrieved secrets
 * - Clear sensitive values from memory when no longer needed
 *
 * @package Blockchain\Security
 */
class EnvSecretProvider implements SecretProviderInterface
{
    /**
     * Optional prefix for environment variable names.
     *
     * @var string
     */
    private string $prefix;

    /**
     * Create a new environment secret provider.
     *
     * @param string $prefix Optional prefix to prepend to all secret names
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Retrieve a secret value from environment variables.
     *
     * @param string $name The name of the environment variable
     *
     * @throws SecurityException If the environment variable doesn't exist
     *
     * @return string The secret value
     */
    public function get(string $name): string
    {
        $envName = $this->prefix . $name;
        $value = getenv($envName);

        if ($value === false) {
            throw new SecurityException(
                sprintf('Secret "%s" not found in environment variables', $name)
            );
        }

        return $value;
    }

    /**
     * Check if an environment variable exists.
     *
     * @param string $name The name of the environment variable to check
     *
     * @return bool True if the environment variable exists
     */
    public function has(string $name): bool
    {
        $envName = $this->prefix . $name;
        return getenv($envName) !== false;
    }

    /**
     * Get the configured prefix.
     *
     * @return string The prefix used for environment variable names
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
