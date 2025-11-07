<?php

declare(strict_types=1);

namespace Blockchain\Utils;

use JsonException;

/**
 * Serializer provides data serialization and deserialization utilities.
 *
 * This class handles conversion between different data formats including
 * JSON and Base64 encoding, with proper error handling.
 *
 * @package Blockchain\Utils
 */
class Serializer
{
    /**
     * Convert an array to JSON string.
     *
     * @param array<string,mixed> $data The data to encode
     * @return string The JSON encoded string
     * @throws JsonException If encoding fails
     *
     * @example
     * ```php
     * $json = Serializer::toJson(['name' => 'Alice', 'balance' => 100]);
     * // Returns: '{"name":"Alice","balance":100}'
     * ```
     */
    public static function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Convert a JSON string to an array.
     *
     * @param string $json The JSON string to decode
     * @return array<string,mixed> The decoded array
     * @throws JsonException If decoding fails
     *
     * @example
     * ```php
     * $data = Serializer::fromJson('{"name":"Alice","balance":100}');
     * // Returns: ['name' => 'Alice', 'balance' => 100]
     * ```
     */
    public static function fromJson(string $json): array
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Encode a string to Base64.
     *
     * @param string $data The data to encode
     * @return string The Base64 encoded string
     *
     * @example
     * ```php
     * $encoded = Serializer::toBase64('Hello World');
     * // Returns: 'SGVsbG8gV29ybGQ='
     * ```
     */
    public static function toBase64(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * Decode a Base64 encoded string.
     *
     * @param string $encoded The Base64 encoded string
     * @return string The decoded string
     * @throws \InvalidArgumentException If the encoded string is invalid
     *
     * @example
     * ```php
     * $decoded = Serializer::fromBase64('SGVsbG8gV29ybGQ=');
     * // Returns: 'Hello World'
     * ```
     */
    public static function fromBase64(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid Base64 encoded string.');
        }
        
        return $decoded;
    }
}
