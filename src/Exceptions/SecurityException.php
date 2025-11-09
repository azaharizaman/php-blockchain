<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * SecurityException is thrown when security-related operations fail.
 *
 * This exception is used throughout the blockchain SDK when:
 * - Secret providers fail to retrieve secrets
 * - Invalid security configuration is detected
 * - Cryptographic operations fail
 * - Access to sensitive resources is denied
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\Security\EnvSecretProvider;
 * use Blockchain\Exceptions\SecurityException;
 *
 * try {
 *     $provider = new EnvSecretProvider();
 *     $secret = $provider->get('MISSING_SECRET');
 * } catch (SecurityException $e) {
 *     echo "Security error: " . $e->getMessage();
 * }
 * ```
 */
class SecurityException extends Exception
{
    //
}
