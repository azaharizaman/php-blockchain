<?php

declare(strict_types=1);

namespace Blockchain\Security;

use Blockchain\Exceptions\SecurityException;

/**
 * SigningProviderInterface
 *
 * Extended contract for secret providers that support cryptographic signing operations.
 * This interface extends SecretProviderInterface to add signing capabilities for
 * HSM and KeyVault integrations where keys are stored securely and signing happens
 * within the secure boundary.
 *
 * Implementations must ensure:
 * - Private keys never leave the HSM/KeyVault boundary
 * - Signing operations are performed within the secure enclave
 * - Key references are used instead of actual key material
 *
 * @package Blockchain\Security
 */
interface SigningProviderInterface extends SecretProviderInterface
{
    /**
     * Sign data using a key stored in the HSM/KeyVault.
     *
     * This method performs cryptographic signing within the HSM/KeyVault boundary,
     * ensuring that the private key never leaves the secure enclave.
     *
     * @param string $keyName The identifier of the key to use for signing
     * @param string $data The data to sign
     *
     * @throws SecurityException If signing fails or the key cannot be accessed
     *
     * @return string The signature (typically base64 or hex encoded)
     */
    public function sign(string $keyName, string $data): string;
}
