<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * ConfigurationException is thrown when driver configuration is invalid or missing.
 *
 * This exception is used throughout the blockchain SDK when:
 * - Required configuration parameters are missing
 * - Configuration values are invalid or malformed
 * - A driver is not properly configured before use
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\BlockchainManager;
 * use Blockchain\Exceptions\ConfigurationException;
 *
 * try {
 *     $manager = new BlockchainManager();
 *     // Trying to use manager without setting a driver
 *     $balance = $manager->getBalance('address');
 * } catch (ConfigurationException $e) {
 *     echo "Configuration error: " . $e->getMessage();
 * }
 * ```
 */
class ConfigurationException extends Exception
{
    //
}
