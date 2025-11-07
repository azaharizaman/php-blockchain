<?php

declare(strict_types=1);

namespace Blockchain\Config;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\ValidationException;

/**
 * ConfigLoader handles loading and validating blockchain driver configurations.
 *
 * This class supports loading configuration from multiple sources:
 * - Array: Direct configuration arrays
 * - Environment: Environment variables with a prefix
 * - File: PHP or JSON configuration files
 *
 * It also provides schema validation to ensure configurations meet
 * driver requirements before use.
 *
 * @package Blockchain\Config
 *
 * @example
 * ```php
 * use Blockchain\Config\ConfigLoader;
 *
 * // Load from array
 * $config = ConfigLoader::fromArray(['rpc_url' => 'https://api.mainnet-beta.solana.com']);
 *
 * // Load from environment variables
 * $config = ConfigLoader::fromEnv('BLOCKCHAIN_');
 *
 * // Load from file
 * $config = ConfigLoader::fromFile(__DIR__ . '/config/solana.php');
 *
 * // Validate configuration
 * ConfigLoader::validateConfig($config, 'solana');
 * ```
 */
class ConfigLoader
{
    /**
     * Load configuration from an array.
     *
     * @param array<string,mixed> $config Configuration array
     * @return array<string,mixed> Validated configuration array
     */
    public static function fromArray(array $config): array
    {
        return $config;
    }

    /**
     * Load configuration from environment variables.
     *
     * Reads environment variables with the given prefix and converts them
     * to a configuration array. Variable names are converted to lowercase
     * keys with the prefix removed.
     *
     * Example:
     * - BLOCKCHAIN_RPC_URL -> ['rpc_url' => 'value']
     * - BLOCKCHAIN_TIMEOUT -> ['timeout' => 30]
     *
     * @param string $prefix Environment variable prefix (default: 'BLOCKCHAIN_')
     * @return array<string,mixed> Configuration array from environment
     */
    public static function fromEnv(string $prefix = 'BLOCKCHAIN_'): array
    {
        $config = [];
        $prefixLength = strlen($prefix);

        // Check $_ENV first (most reliable and efficient)
        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $configKey = strtolower(substr($key, $prefixLength));
                $config[$configKey] = self::parseEnvValue($value);
            }
        }

        return $config;
    }

    /**
     * Load configuration from a file.
     *
     * Supports both PHP and JSON configuration files:
     * - PHP files should return an array
     * - JSON files should contain a valid JSON object
     *
     * @param string $path Path to configuration file
     * @return array<string,mixed> Configuration array from file
     * @throws ConfigurationException If file doesn't exist or is invalid
     */
    public static function fromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new ConfigurationException("Configuration file not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            $config = include $path;
            if (!is_array($config)) {
                throw new ConfigurationException("PHP configuration file must return an array: {$path}");
            }
            return $config;
        }

        if ($extension === 'json') {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new ConfigurationException("Failed to read configuration file: {$path}");
            }

            $config = json_decode($contents, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ConfigurationException(
                    "Invalid JSON in configuration file: {$path}. Error: " . json_last_error_msg()
                );
            }

            if (!is_array($config)) {
                throw new ConfigurationException("JSON configuration file must contain an object: {$path}");
            }

            return $config;
        }

        throw new ConfigurationException(
            "Unsupported configuration file format: {$extension}. Supported formats: php, json"
        );
    }

    /**
     * Validate a configuration array against a driver schema.
     *
     * Validates that the configuration contains all required keys
     * and that values are of the correct type and format.
     *
     * @param array<string,mixed> $config Configuration to validate
     * @param string $driver Driver name (default: 'solana')
     * @return bool True if configuration is valid
     * @throws ValidationException If configuration is invalid with detailed error information
     */
    public static function validateConfig(array $config, string $driver = 'solana'): bool
    {
        $schema = self::getDriverSchema($driver);
        $errors = [];

        // Check required fields (with OR logic for alternatives)
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requirement) {
                // If requirement is an array, it's an OR condition (any one is required)
                if (is_array($requirement)) {
                    $hasAny = false;
                    foreach ($requirement as $alternativeField) {
                        if (isset($config[$alternativeField]) || array_key_exists($alternativeField, $config)) {
                            $hasAny = true;
                            break;
                        }
                    }
                    if (!$hasAny) {
                        $fieldNames = implode("' or '", $requirement);
                        $errors['_required'] = "One of the following fields is required: '{$fieldNames}'";
                    }
                } else {
                    // Single required field
                    if (!isset($config[$requirement]) && !array_key_exists($requirement, $config)) {
                        $errors[$requirement] = "Required field '{$requirement}' is missing";
                    }
                }
            }
        }

        // If required fields are missing, throw exception early
        if (!empty($errors)) {
            $exception = new ValidationException(
                "Configuration validation failed for driver '{$driver}': " . implode(', ', $errors)
            );
            $exception->setErrors($errors);
            throw $exception;
        }

        // Validate field types and formats
        foreach ($config as $field => $value) {
            if (!isset($schema['fields'][$field])) {
                continue; // Unknown fields are allowed
            }

            $fieldSchema = $schema['fields'][$field];
            $expectedType = $fieldSchema['type'];

            // Type validation
            $actualType = get_debug_type($value);
            if ($expectedType === 'string' && !is_string($value)) {
                $errors[$field] = "Field '{$field}' must be a string, {$actualType} given";
                continue;
            }
            if ($expectedType === 'int' && !is_int($value)) {
                $errors[$field] = "Field '{$field}' must be an integer, {$actualType} given";
                continue;
            }

            // Format validation
            if (isset($fieldSchema['format'])) {
                if ($fieldSchema['format'] === 'url' && !self::validateUrl($value)) {
                    $errors[$field] = "Field '{$field}' must be a valid URL with http or https scheme";
                    continue;
                }
            }

            // Range validation for integers
            if ($expectedType === 'int' && isset($fieldSchema['min']) && $value < $fieldSchema['min']) {
                $errors[$field] = "Field '{$field}' must be greater than or equal to {$fieldSchema['min']}";
                continue;
            }

            // Enum validation
            if (isset($fieldSchema['enum']) && !in_array($value, $fieldSchema['enum'], true)) {
                $allowedValues = implode(', ', $fieldSchema['enum']);
                $errors[$field] = "Field '{$field}' must be one of: {$allowedValues}";
                continue;
            }
        }

        if (!empty($errors)) {
            $exception = new ValidationException(
                "Configuration validation failed for driver '{$driver}': " . implode('; ', $errors)
            );
            $exception->setErrors($errors);
            throw $exception;
        }

        return true;
    }

    /**
     * Get the validation schema for a specific driver.
     *
     * @param string $driver Driver name
     * @return array<string,mixed> Schema definition
     */
    private static function getDriverSchema(string $driver): array
    {
        $schemas = [
            'solana' => [
                // Accept either 'rpc_url' or 'endpoint' (for backward compatibility)
                'required' => [
                    ['rpc_url', 'endpoint'], // OR condition - at least one is required
                ],
                'fields' => [
                    'rpc_url' => [
                        'type' => 'string',
                        'format' => 'url',
                    ],
                    'endpoint' => [
                        'type' => 'string',
                        'format' => 'url',
                    ],
                    'timeout' => [
                        'type' => 'int',
                        'min' => 1,
                    ],
                    'commitment' => [
                        'type' => 'string',
                        'enum' => ['finalized', 'confirmed', 'processed'],
                    ],
                ],
            ],
        ];

        if (!isset($schemas[$driver])) {
            // Return a basic schema for unknown drivers
            return [
                'required' => [],
                'fields' => [],
            ];
        }

        return $schemas[$driver];
    }

    /**
     * Validate that a string is a valid URL with http or https scheme.
     *
     * @param string $url URL to validate
     * @return bool True if URL is valid
     */
    private static function validateUrl(string $url): bool
    {
        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }

        // Check for http or https scheme
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Parse an environment variable value to its appropriate PHP type.
     *
     * Converts string values to their appropriate types:
     * - 'true' -> true (bool)
     * - 'false' -> false (bool)
     * - numeric strings -> int or float
     * - other strings -> returned as-is
     *
     * Note: Requires PHP 8.0+ for mixed return type.
     *
     * @param string $value Environment variable value
     * @return string|int|float|bool Parsed value (string, int, float, or bool)
     */
    private static function parseEnvValue(string $value): string|int|float|bool
    {
        // Boolean values
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Numeric values
        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        // Return string as-is
        return $value;
    }
}
