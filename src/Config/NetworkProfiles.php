<?php

declare(strict_types=1);

namespace Blockchain\Config;

/**
 * NetworkProfiles provides a registry of pre-configured blockchain network profiles.
 *
 * This class manages a collection of network configuration templates that map
 * logical network names to complete driver configurations. It enables quick
 * switching between different blockchain networks (mainnet, testnet, devnet, etc.)
 * without manually specifying configuration details.
 *
 * Profiles include:
 * - Solana networks: mainnet, devnet, testnet
 * - Ethereum networks: mainnet, goerli, sepolia, localhost
 *
 * Environment variables can be used for API keys (e.g., INFURA_API_KEY, ALCHEMY_API_KEY).
 *
 * @package Blockchain\Config
 *
 * @example
 * ```php
 * // Get a profile configuration
 * $config = NetworkProfiles::get('solana.mainnet');
 *
 * // Check if a profile exists
 * if (NetworkProfiles::has('ethereum.mainnet')) {
 *     $config = NetworkProfiles::get('ethereum.mainnet');
 * }
 *
 * // List all available profiles
 * $profiles = NetworkProfiles::all();
 * ```
 */
class NetworkProfiles
{
    /**
     * Registry of built-in network profiles.
     *
     * Each profile contains:
     * - driver: The blockchain driver name (e.g., 'solana', 'ethereum')
     * - endpoint: The RPC endpoint URL
     * - chainId: Network/chain identifier (optional)
     * - options: Additional driver-specific options (optional)
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $profiles = [
        // Solana Networks
        'solana.mainnet' => [
            'driver' => 'solana',
            'endpoint' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ],
        'solana.devnet' => [
            'driver' => 'solana',
            'endpoint' => 'https://api.devnet.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ],
        'solana.testnet' => [
            'driver' => 'solana',
            'endpoint' => 'https://api.testnet.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ],

        // Ethereum Networks
        'ethereum.mainnet' => [
            'driver' => 'ethereum',
            'endpoint' => 'https://mainnet.infura.io/v3/${INFURA_API_KEY}',
            'chainId' => '0x1',
            'timeout' => 30,
        ],
        'ethereum.goerli' => [
            'driver' => 'ethereum',
            'endpoint' => 'https://goerli.infura.io/v3/${INFURA_API_KEY}',
            'chainId' => '0x5',
            'timeout' => 30,
        ],
        'ethereum.sepolia' => [
            'driver' => 'ethereum',
            'endpoint' => 'https://sepolia.infura.io/v3/${INFURA_API_KEY}',
            'chainId' => '0xaa36a7',
            'timeout' => 30,
        ],
        'ethereum.localhost' => [
            'driver' => 'ethereum',
            'endpoint' => 'http://localhost:8545',
            'chainId' => '0x539',
            'timeout' => 30,
        ],
    ];

    /**
     * Get a network profile configuration by name.
     *
     * Returns the complete configuration array for the specified network profile.
     * Environment variable interpolation is performed on the configuration values.
     *
     * @param string $name Profile name like 'ethereum.mainnet' or 'solana.devnet'
     * @return array<string, mixed> Configuration array for the profile
     * @throws \InvalidArgumentException If the profile name is not found
     */
    public static function get(string $name): array
    {
        if (!self::has($name)) {
            throw new \InvalidArgumentException(
                "Network profile '{$name}' not found. Available profiles: " . implode(', ', self::all())
            );
        }

        $profile = self::$profiles[$name];

        // Perform environment variable interpolation
        return self::interpolateEnvironmentVariables($profile);
    }

    /**
     * Check if a network profile exists.
     *
     * @param string $name Profile name to check
     * @return bool True if the profile exists, false otherwise
     */
    public static function has(string $name): bool
    {
        return isset(self::$profiles[$name]);
    }

    /**
     * Get all available network profile names.
     *
     * @return array<int, string> Array of profile names
     */
    public static function all(): array
    {
        return array_keys(self::$profiles);
    }

    /**
     * Interpolate environment variables in configuration values.
     *
     * Replaces ${VAR_NAME} patterns with corresponding environment variable values.
     * If an environment variable is not set, the pattern is left unchanged.
     *
     * @param array<string, mixed> $config Configuration array
     * @return array<string, mixed> Configuration with interpolated values
     */
    private static function interpolateEnvironmentVariables(array $config): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                // Replace ${VAR_NAME} with environment variable value
                $result[$key] = preg_replace_callback(
                    '/\$\{([A-Z_][A-Z0-9_]*)\}/',
                    function ($matches) {
                        $varName = $matches[1];
                        // Check environment variable
                        $envValue = getenv($varName);
                        if ($envValue !== false) {
                            return $envValue;
                        }
                        // Check $_ENV superglobal
                        if (isset($_ENV[$varName])) {
                            return $_ENV[$varName];
                        }
                        // Return original pattern if not found
                        return $matches[0];
                    },
                    $value
                );
            } elseif (is_array($value)) {
                // Recursively interpolate nested arrays
                $result[$key] = self::interpolateEnvironmentVariables($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
