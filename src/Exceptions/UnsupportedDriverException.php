<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * UnsupportedDriverException is thrown when requested driver is not registered or available.
 *
 * This exception is used when:
 * - Attempting to use a blockchain driver that hasn't been registered
 * - Requesting a driver that doesn't exist in the registry
 * - Trying to switch to a driver that was never loaded
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\BlockchainManager;
 * use Blockchain\Exceptions\UnsupportedDriverException;
 *
 * try {
 *     // Trying to use a non-existent driver
 *     $blockchain = new BlockchainManager('nonexistent', ['endpoint' => 'https://example.com']);
 * } catch (UnsupportedDriverException $e) {
 *     echo "Driver not supported: " . $e->getMessage();
 * }
 * ```
 */
class UnsupportedDriverException extends Exception
{
    //
}
