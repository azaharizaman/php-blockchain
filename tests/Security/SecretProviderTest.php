<?php

declare(strict_types=1);

namespace Blockchain\Tests\Security;

use Blockchain\Security\SecretProviderInterface;
use Blockchain\Security\EnvSecretProvider;
use Blockchain\Security\HsmSecretProvider;
use Blockchain\Exceptions\SecurityException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for secret provider implementations.
 *
 * Verifies that all secret providers:
 * - Implement the SecretProviderInterface correctly
 * - Handle missing secrets appropriately
 * - Throw SecurityException for error conditions
 * - Do not expose secrets in logs or error messages inadvertently
 */
class SecretProviderTest extends TestCase
{
    /**
     * Test that EnvSecretProvider can be instantiated.
     */
    public function testEnvSecretProviderCanBeInstantiated(): void
    {
        $provider = new EnvSecretProvider();
        
        $this->assertInstanceOf(EnvSecretProvider::class, $provider);
        $this->assertInstanceOf(SecretProviderInterface::class, $provider);
    }

    /**
     * Test that EnvSecretProvider can be instantiated with a prefix.
     */
    public function testEnvSecretProviderCanBeInstantiatedWithPrefix(): void
    {
        $provider = new EnvSecretProvider('APP_');
        
        $this->assertInstanceOf(EnvSecretProvider::class, $provider);
        $this->assertSame('APP_', $provider->getPrefix());
    }

    /**
     * Test that EnvSecretProvider retrieves existing environment variables.
     */
    public function testEnvSecretProviderRetrievesExistingSecret(): void
    {
        // Set a test environment variable
        putenv('TEST_SECRET_KEY=test_secret_value');
        
        $provider = new EnvSecretProvider();
        $value = $provider->get('TEST_SECRET_KEY');
        
        $this->assertSame('test_secret_value', $value);
        
        // Clean up
        putenv('TEST_SECRET_KEY');
    }

    /**
     * Test that EnvSecretProvider uses prefix correctly.
     */
    public function testEnvSecretProviderUsesPrefix(): void
    {
        // Set a test environment variable with prefix
        putenv('APP_SECRET_KEY=prefixed_value');
        
        $provider = new EnvSecretProvider('APP_');
        $value = $provider->get('SECRET_KEY');
        
        $this->assertSame('prefixed_value', $value);
        
        // Clean up
        putenv('APP_SECRET_KEY');
    }

    /**
     * Test that EnvSecretProvider throws SecurityException for missing secrets.
     */
    public function testEnvSecretProviderThrowsExceptionForMissingSecret(): void
    {
        $provider = new EnvSecretProvider();
        
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Secret "NONEXISTENT_SECRET" not found in environment variables');
        
        $provider->get('NONEXISTENT_SECRET');
    }

    /**
     * Test that EnvSecretProvider has() returns true for existing secrets.
     */
    public function testEnvSecretProviderHasReturnsTrueForExistingSecret(): void
    {
        putenv('TEST_HAS_SECRET=value');
        
        $provider = new EnvSecretProvider();
        
        $this->assertTrue($provider->has('TEST_HAS_SECRET'));
        
        // Clean up
        putenv('TEST_HAS_SECRET');
    }

    /**
     * Test that EnvSecretProvider has() returns false for missing secrets.
     */
    public function testEnvSecretProviderHasReturnsFalseForMissingSecret(): void
    {
        $provider = new EnvSecretProvider();
        
        $this->assertFalse($provider->has('NONEXISTENT_SECRET_KEY'));
    }

    /**
     * Test that EnvSecretProvider handles empty string values correctly.
     */
    public function testEnvSecretProviderHandlesEmptyStrings(): void
    {
        putenv('EMPTY_SECRET=');
        
        $provider = new EnvSecretProvider();
        
        // Empty string is a valid value, not false
        $this->assertTrue($provider->has('EMPTY_SECRET'));
        $this->assertSame('', $provider->get('EMPTY_SECRET'));
        
        // Clean up
        putenv('EMPTY_SECRET');
    }

    /**
     * Test that HsmSecretProvider can be instantiated with valid config.
     */
    public function testHsmSecretProviderCanBeInstantiated(): void
    {
        $config = ['endpoint' => 'https://vault.example.com'];
        $provider = new HsmSecretProvider($config);
        
        $this->assertInstanceOf(HsmSecretProvider::class, $provider);
        $this->assertInstanceOf(SecretProviderInterface::class, $provider);
    }

    /**
     * Test that HsmSecretProvider throws exception with empty config.
     */
    public function testHsmSecretProviderThrowsExceptionWithEmptyConfig(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('HSM configuration cannot be empty');
        
        new HsmSecretProvider([]);
    }

    /**
     * Test that HsmSecretProvider get() throws not implemented exception.
     */
    public function testHsmSecretProviderGetThrowsNotImplementedException(): void
    {
        $config = ['endpoint' => 'https://vault.example.com'];
        $provider = new HsmSecretProvider($config);
        
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('HSM provider is not fully implemented');
        
        $provider->get('test-key');
    }

    /**
     * Test that HsmSecretProvider has() returns false for skeleton implementation.
     */
    public function testHsmSecretProviderHasReturnsFalse(): void
    {
        $config = ['endpoint' => 'https://vault.example.com'];
        $provider = new HsmSecretProvider($config);
        
        $this->assertFalse($provider->has('test-key'));
    }

    /**
     * Test that HsmSecretProvider sign() throws not implemented exception.
     */
    public function testHsmSecretProviderSignThrowsNotImplementedException(): void
    {
        $config = ['endpoint' => 'https://vault.example.com'];
        $provider = new HsmSecretProvider($config);
        
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('HSM signing is not fully implemented');
        
        $provider->sign('test-key', 'data-to-sign');
    }

    /**
     * Test that HsmSecretProvider sanitizes config when retrieved.
     */
    public function testHsmSecretProviderSanitizesConfig(): void
    {
        $config = [
            'endpoint' => 'https://vault.example.com',
            'client_secret' => 'super_secret_value',
            'api_key' => 'secret_api_key',
            'other_field' => 'public_value'
        ];
        
        $provider = new HsmSecretProvider($config);
        $sanitized = $provider->getConfig();
        
        $this->assertSame('https://vault.example.com', $sanitized['endpoint']);
        $this->assertSame('***REDACTED***', $sanitized['client_secret']);
        $this->assertSame('***REDACTED***', $sanitized['api_key']);
        $this->assertSame('public_value', $sanitized['other_field']);
    }

    /**
     * Test that secret values are not logged in exception messages.
     *
     * This test verifies that when a secret retrieval fails, the actual
     * secret value (if known) is not included in the exception message.
     */
    public function testSecretValuesNotExposedInExceptions(): void
    {
        $provider = new EnvSecretProvider();
        
        try {
            $provider->get('SUPER_SECRET_KEY');
            $this->fail('Expected SecurityException was not thrown');
        } catch (SecurityException $e) {
            $message = $e->getMessage();
            
            // Exception message should contain the key name for debugging
            $this->assertStringContainsString('SUPER_SECRET_KEY', $message);
            
            // But should NOT contain any actual secret value
            // We ensure the message is reasonable and doesn't leak data
            $this->assertStringNotContainsString('password', strtolower($message));
            $this->assertStringNotContainsString('secret_value', strtolower($message));
        }
    }

    /**
     * Test that multiple providers can coexist.
     */
    public function testMultipleProvidersCanCoexist(): void
    {
        putenv('ENV_SECRET=env_value');
        
        $envProvider = new EnvSecretProvider();
        $hsmProvider = new HsmSecretProvider(['endpoint' => 'https://vault.example.com']);
        
        $this->assertInstanceOf(SecretProviderInterface::class, $envProvider);
        $this->assertInstanceOf(SecretProviderInterface::class, $hsmProvider);
        
        $this->assertTrue($envProvider->has('ENV_SECRET'));
        $this->assertFalse($hsmProvider->has('ENV_SECRET'));
        
        // Clean up
        putenv('ENV_SECRET');
    }

    /**
     * Test that EnvSecretProvider works with numeric values.
     */
    public function testEnvSecretProviderHandlesNumericValues(): void
    {
        putenv('NUMERIC_SECRET=12345');
        
        $provider = new EnvSecretProvider();
        $value = $provider->get('NUMERIC_SECRET');
        
        $this->assertIsString($value);
        $this->assertSame('12345', $value);
        
        // Clean up
        putenv('NUMERIC_SECRET');
    }

    /**
     * Test that EnvSecretProvider works with special characters.
     */
    public function testEnvSecretProviderHandlesSpecialCharacters(): void
    {
        $specialValue = 'secret!@#$%^&*()_+-=[]{}|;:,.<>?';
        putenv('SPECIAL_SECRET=' . $specialValue);
        
        $provider = new EnvSecretProvider();
        $value = $provider->get('SPECIAL_SECRET');
        
        $this->assertSame($specialValue, $value);
        
        // Clean up
        putenv('SPECIAL_SECRET');
    }
}
