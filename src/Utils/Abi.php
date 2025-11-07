<?php

declare(strict_types=1);

namespace Blockchain\Utils;

/**
 * Abi provides ABI (Application Binary Interface) encoding and decoding helpers
 * for interacting with Ethereum smart contracts.
 *
 * This utility class supports encoding function calls and decoding responses
 * for Ethereum smart contract interactions.
 *
 * @package Blockchain\Utils
 */
class Abi
{
    /**
     * Generate function selector (first 4 bytes of keccak256 hash).
     *
     * Takes a function signature like "balanceOf(address)" and returns
     * the function selector used in Ethereum contract calls.
     *
     * @param string $signature Function signature (e.g., "balanceOf(address)")
     * @return string Function selector with 0x prefix (e.g., "0x70a08231")
     *
     * @example
     * ```php
     * $selector = Abi::getFunctionSelector('balanceOf(address)');
     * // Returns: '0x70a08231'
     * ```
     */
    public static function getFunctionSelector(string $signature): string
    {
        // Calculate keccak256 hash (Ethereum uses Keccak, not SHA3)
        $hash = Keccak::hash($signature);
        
        // Return first 4 bytes (8 hex characters) with 0x prefix
        return '0x' . substr($hash, 0, 8);
    }

    /**
     * Encode a function call with parameters.
     *
     * Encodes a function signature and its parameters into the format
     * required for Ethereum contract calls.
     *
     * @param string $signature Function signature (e.g., "balanceOf(address)")
     * @param array<int,mixed> $params Array of parameters to encode
     * @return string Encoded function call as hex string with 0x prefix
     *
     * @example
     * ```php
     * $data = Abi::encodeFunctionCall('balanceOf(address)', ['0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb']);
     * ```
     */
    public static function encodeFunctionCall(string $signature, array $params): string
    {
        // Get function selector
        $selector = self::getFunctionSelector($signature);
        
        // Extract parameter types from signature
        $types = self::extractTypes($signature);
        
        // Encode each parameter
        $encodedParams = '';
        foreach ($params as $index => $value) {
            if (isset($types[$index])) {
                $encodedParams .= self::encodeParameter($types[$index], $value);
            }
        }
        
        // Return selector + encoded parameters
        return $selector . $encodedParams;
    }

    /**
     * Encode a single parameter based on its type.
     *
     * @param string $type Parameter type (address, uint256, bool, string)
     * @param mixed $value Parameter value
     * @return string Encoded parameter as hex string (without 0x prefix)
     */
    private static function encodeParameter(string $type, mixed $value): string
    {
        // Remove array notation and whitespace from type
        $type = trim($type);
        
        return match (true) {
            $type === 'address' => self::encodeAddress($value),
            str_starts_with($type, 'uint') => self::encodeUint256($value),
            $type === 'bool' => self::encodeBool($value),
            $type === 'string' => self::encodeString($value),
            default => throw new \InvalidArgumentException("Unsupported type: {$type}"),
        };
    }

    /**
     * Encode an address parameter.
     *
     * @param string $address Ethereum address with or without 0x prefix
     * @return string Padded address (64 hex characters)
     */
    private static function encodeAddress(string $address): string
    {
        // Remove 0x prefix if present
        $address = str_starts_with($address, '0x') ? substr($address, 2) : $address;
        
        // Pad to 32 bytes (64 hex characters)
        return str_pad($address, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a uint256 parameter.
     *
     * @param string|int $value Numeric value (string or int)
     * @return string Padded hex value (64 hex characters)
     */
    private static function encodeUint256(string|int $value): string
    {
        // Convert to string for GMP operations
        $valueStr = (string) $value;
        
        // Convert to hex using GMP for large number support
        $hex = gmp_strval(gmp_init($valueStr, 10), 16);
        
        // Pad to 32 bytes (64 hex characters)
        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode a boolean parameter.
     *
     * @param bool $value Boolean value
     * @return string Encoded as uint256 (0 or 1)
     */
    private static function encodeBool(bool $value): string
    {
        return self::encodeUint256($value ? 1 : 0);
    }

    /**
     * Encode a string parameter.
     *
     * Strings in ABI are dynamic types: offset (32 bytes) + length (32 bytes) + data (padded to 32 bytes).
     * For simplicity in single-parameter calls, we encode inline.
     *
     * @param string $value String value
     * @return string Encoded string: length + padded data
     */
    private static function encodeString(string $value): string
    {
        $length = strlen($value);
        $hex = bin2hex($value);
        
        // Pad to multiple of 32 bytes
        $paddedHex = str_pad($hex, ceil($length / 32) * 64, '0', STR_PAD_RIGHT);
        
        // Return: length (32 bytes) + padded data
        return self::encodeUint256($length) . $paddedHex;
    }

    /**
     * Decode a response based on return type.
     *
     * Decodes hex-encoded contract response data based on the expected return type.
     *
     * @param string $returnType Expected return type (uint256, address, bool, string)
     * @param string $data Hex-encoded response data with or without 0x prefix
     * @return mixed Decoded value in appropriate PHP type
     *
     * @example
     * ```php
     * $balance = Abi::decodeResponse('uint256', '0x00000000000000000000000000000000000000000000000000000000000003e8');
     * // Returns: "1000"
     * ```
     */
    public static function decodeResponse(string $returnType, string $data): mixed
    {
        // Remove 0x prefix if present
        $data = str_starts_with($data, '0x') ? substr($data, 2) : $data;
        
        return match ($returnType) {
            'uint256' => self::decodeUint256($data),
            'address' => self::decodeAddress($data),
            'bool' => self::decodeBool($data),
            'string' => self::decodeString($data),
            default => throw new \InvalidArgumentException("Unsupported return type: {$returnType}"),
        };
    }

    /**
     * Decode a uint256 value from hex.
     *
     * @param string $hex Hex string (without 0x prefix)
     * @return string Decimal string representation (preserves precision)
     */
    private static function decodeUint256(string $hex): string
    {
        // Take first 64 characters (32 bytes)
        $hex = substr($hex, 0, 64);
        
        // Convert hex to decimal using GMP for large number support
        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /**
     * Decode an address value from hex.
     *
     * @param string $hex Hex string (without 0x prefix)
     * @return string Ethereum address with 0x prefix
     */
    private static function decodeAddress(string $hex): string
    {
        // Take first 64 characters (32 bytes) and extract last 40 characters (20 bytes)
        $hex = substr($hex, 0, 64);
        $address = substr($hex, 24, 40);
        
        return '0x' . $address;
    }

    /**
     * Decode a boolean value from hex.
     *
     * @param string $hex Hex string (without 0x prefix)
     * @return bool Boolean value
     */
    private static function decodeBool(string $hex): bool
    {
        $value = self::decodeUint256($hex);
        return $value !== '0';
    }

    /**
     * Decode a string value from hex.
     *
     * @param string $hex Hex string (without 0x prefix)
     * @return string Decoded string value
     */
    private static function decodeString(string $hex): string
    {
        // First 32 bytes is the length
        $length = (int) self::decodeUint256(substr($hex, 0, 64));
        
        // Following bytes are the string data
        $dataHex = substr($hex, 64, $length * 2);
        
        return hex2bin($dataHex) ?: '';
    }

    /**
     * Extract parameter types from function signature.
     *
     * @param string $signature Function signature
     * @return array<int,string> Array of parameter types
     */
    private static function extractTypes(string $signature): array
    {
        // Extract parameter types from signature like "transfer(address,uint256)"
        if (!preg_match('/\((.*?)\)/', $signature, $matches)) {
            return [];
        }
        
        $paramsStr = $matches[1];
        if (empty($paramsStr)) {
            return [];
        }
        
        // Split by comma and trim whitespace
        return array_map('trim', explode(',', $paramsStr));
    }

    /**
     * Convenience method to encode ERC-20 balanceOf function call.
     *
     * @param string $address Ethereum address to check balance for
     * @return string Encoded function call
     *
     * @example
     * ```php
     * $data = Abi::encodeBalanceOf('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
     * ```
     */
    public static function encodeBalanceOf(string $address): string
    {
        return self::encodeFunctionCall('balanceOf(address)', [$address]);
    }

    /**
     * Convenience method to encode ERC-20 transfer function call.
     *
     * @param string $to Recipient address
     * @param string $amount Amount to transfer (as string to preserve precision)
     * @return string Encoded function call
     *
     * @example
     * ```php
     * $data = Abi::encodeTransfer('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb', '1000000000000000000');
     * ```
     */
    public static function encodeTransfer(string $to, string $amount): string
    {
        return self::encodeFunctionCall('transfer(address,uint256)', [$to, $amount]);
    }
}
