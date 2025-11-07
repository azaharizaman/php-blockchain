<?php

declare(strict_types=1);

namespace Blockchain\Utils;

/**
 * Keccak hash wrapper for Ethereum.
 *
 * This class provides Keccak-256 hashing which is used by Ethereum.
 * Note: This is different from SHA3-256.
 *
 * @package Blockchain\Utils
 */
class Keccak
{
    /**
     * Calculate Keccak-256 hash.
     *
     * @param string $input Input string
     * @return string Hex-encoded hash (no 0x prefix)
     */
    public static function hash(string $input): string
    {
        return KeccakLib::hash($input, 256);
    }
}
