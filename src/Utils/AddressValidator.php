<?php

declare(strict_types=1);

namespace Blockchain\Utils;

/**
 * AddressValidator provides validation and normalization for blockchain addresses.
 *
 * This utility class supports multiple blockchain networks and provides
 * methods to validate address formats and normalize address strings.
 *
 * @package Blockchain\Utils
 */
class AddressValidator
{
    /**
     * Validate a blockchain address for a specific network.
     *
     * Performs basic validation including length and character set checks.
     * Currently supports Solana (base58 encoding, 32-44 characters).
     *
     * @param string $address The blockchain address to validate
     * @param string $network The blockchain network (default: 'solana')
     * @return bool True if the address is valid, false otherwise
     *
     * @example
     * ```php
     * $isValid = AddressValidator::isValid('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM', 'solana');
     * ```
     */
    public static function isValid(string $address, string $network = 'solana'): bool
    {
        if (empty($address)) {
            return false;
        }

        // Normalize network name to lowercase
        $network = strtolower($network);

        return match ($network) {
            'solana' => self::validateSolanaAddress($address),
            default => false, // Unsupported networks return false
        };
    }

    /**
     * Normalize a blockchain address by trimming whitespace and converting to appropriate case.
     *
     * @param string $address The address to normalize
     * @return string The normalized address
     *
     * @example
     * ```php
     * $normalized = AddressValidator::normalize('  0x1234ABCD  ');
     * // Returns: '0x1234abcd'
     * ```
     */
    public static function normalize(string $address): string
    {
        // Trim whitespace
        $address = trim($address);

        // Convert to lowercase for hex addresses (Ethereum, etc.)
        // Solana addresses are case-sensitive, but this is safe for most formats
        if (str_starts_with($address, '0x')) {
            return strtolower($address);
        }

        return $address;
    }

    /**
     * Validate a Solana address.
     *
     * Solana addresses are base58 encoded and typically 32-44 characters long.
     * They should only contain valid base58 characters (no 0, O, I, l).
     *
     * @param string $address The Solana address to validate
     * @return bool True if valid, false otherwise
     */
    private static function validateSolanaAddress(string $address): bool
    {
        // Check length (Solana addresses are typically 32-44 characters)
        $length = strlen($address);
        if ($length < 32 || $length > 44) {
            return false;
        }

        // Check for valid base58 characters
        // Base58 alphabet excludes: 0, O, I, l (to avoid confusion)
        $base58Pattern = '/^[1-9A-HJ-NP-Za-km-z]+$/';
        if (!preg_match($base58Pattern, $address)) {
            return false;
        }

        return true;
    }
}
