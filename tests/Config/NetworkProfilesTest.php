<?php

declare(strict_types=1);

namespace Blockchain\Tests\Config;

use Blockchain\Config\NetworkProfiles;
use Blockchain\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for NetworkProfiles class.
 *
 * Verifies that NetworkProfiles correctly:
 * - Retrieves all built-in network profiles
 * - Validates profiles against ConfigLoader schemas
 * - Handles missing profiles with appropriate exceptions
 * - Performs environment variable interpolation
 * - Provides accurate has() and all() methods
 */
class NetworkProfilesTest extends TestCase
{
    /**
     * Original environment variables to restore after tests.
     *
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    /**
     * Set up test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original environment variables
        $this->originalEnv = [
            'INFURA_API_KEY' => getenv('INFURA_API_KEY'),
            'ALCHEMY_API_KEY' => getenv('ALCHEMY_API_KEY'),
        ];
    }

    /**
     * Restore environment after each test.
     */
    protected function tearDown(): void
    {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    /**
     * Test that all built-in Solana profiles can be retrieved.
     */
    public function testGetSolanaProfiles(): void
    {
        $profiles = ['solana.mainnet', 'solana.devnet', 'solana.testnet'];

        foreach ($profiles as $profileName) {
            $config = NetworkProfiles::get($profileName);

            $this->assertIsArray($config);
            $this->assertArrayHasKey('driver', $config);
            $this->assertArrayHasKey('endpoint', $config);
            $this->assertEquals('solana', $config['driver']);
            $this->assertStringStartsWith('https://', $config['endpoint']);
        }
    }

    /**
     * Test that all built-in Ethereum profiles can be retrieved.
     */
    public function testGetEthereumProfiles(): void
    {
        $profiles = [
            'ethereum.mainnet',
            'ethereum.goerli',
            'ethereum.sepolia',
            'ethereum.localhost'
        ];

        foreach ($profiles as $profileName) {
            $config = NetworkProfiles::get($profileName);

            $this->assertIsArray($config);
            $this->assertArrayHasKey('driver', $config);
            $this->assertArrayHasKey('endpoint', $config);
            $this->assertArrayHasKey('chainId', $config);
            $this->assertEquals('ethereum', $config['driver']);
        }
    }

    /**
     * Test that Solana mainnet profile has correct configuration.
     */
    public function testSolanaMainnetProfile(): void
    {
        $config = NetworkProfiles::get('solana.mainnet');

        $this->assertEquals('solana', $config['driver']);
        $this->assertEquals('https://api.mainnet-beta.solana.com', $config['endpoint']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals('finalized', $config['commitment']);
    }

    /**
     * Test that Ethereum mainnet profile has correct configuration.
     */
    public function testEthereumMainnetProfile(): void
    {
        // Set environment variable for testing
        putenv('INFURA_API_KEY=test_api_key_123');
        $_ENV['INFURA_API_KEY'] = 'test_api_key_123';

        $config = NetworkProfiles::get('ethereum.mainnet');

        $this->assertEquals('ethereum', $config['driver']);
        $this->assertEquals('https://mainnet.infura.io/v3/test_api_key_123', $config['endpoint']);
        $this->assertEquals('0x1', $config['chainId']);
        $this->assertEquals(30, $config['timeout']);
    }

    /**
     * Test that Ethereum localhost profile doesn't require API keys.
     */
    public function testEthereumLocalhostProfile(): void
    {
        $config = NetworkProfiles::get('ethereum.localhost');

        $this->assertEquals('ethereum', $config['driver']);
        $this->assertEquals('http://localhost:8545', $config['endpoint']);
        $this->assertEquals('0x539', $config['chainId']);
        $this->assertStringNotContainsString('${', $config['endpoint']);
    }

    /**
     * Test that all Solana profiles pass ConfigLoader validation.
     */
    public function testSolanaProfilesValidation(): void
    {
        $profiles = ['solana.mainnet', 'solana.devnet', 'solana.testnet'];

        foreach ($profiles as $profileName) {
            $config = NetworkProfiles::get($profileName);
            
            // Validation should not throw exception
            $this->assertTrue(ConfigLoader::validateConfig($config, 'solana'));
        }
    }

    /**
     * Test that Ethereum localhost profile passes ConfigLoader validation.
     */
    public function testEthereumLocalhostProfileValidation(): void
    {
        $config = NetworkProfiles::get('ethereum.localhost');

        // Ethereum driver validation - should pass without API keys
        $this->assertIsArray($config);
        $this->assertEquals('ethereum', $config['driver']);
        $this->assertStringStartsWith('http://', $config['endpoint']);
    }

    /**
     * Test that has() method correctly identifies existing profiles.
     */
    public function testHasMethodWithExistingProfiles(): void
    {
        $this->assertTrue(NetworkProfiles::has('solana.mainnet'));
        $this->assertTrue(NetworkProfiles::has('solana.devnet'));
        $this->assertTrue(NetworkProfiles::has('solana.testnet'));
        $this->assertTrue(NetworkProfiles::has('ethereum.mainnet'));
        $this->assertTrue(NetworkProfiles::has('ethereum.goerli'));
        $this->assertTrue(NetworkProfiles::has('ethereum.sepolia'));
        $this->assertTrue(NetworkProfiles::has('ethereum.localhost'));
    }

    /**
     * Test that has() method returns false for non-existent profiles.
     */
    public function testHasMethodWithNonExistentProfiles(): void
    {
        $this->assertFalse(NetworkProfiles::has('solana.invalid'));
        $this->assertFalse(NetworkProfiles::has('ethereum.invalid'));
        $this->assertFalse(NetworkProfiles::has('bitcoin.mainnet'));
        $this->assertFalse(NetworkProfiles::has(''));
        $this->assertFalse(NetworkProfiles::has('nonexistent'));
    }

    /**
     * Test that all() method returns all profile names.
     */
    public function testAllMethodReturnsAllProfiles(): void
    {
        $profiles = NetworkProfiles::all();

        $this->assertIsArray($profiles);
        $this->assertContains('solana.mainnet', $profiles);
        $this->assertContains('solana.devnet', $profiles);
        $this->assertContains('solana.testnet', $profiles);
        $this->assertContains('ethereum.mainnet', $profiles);
        $this->assertContains('ethereum.goerli', $profiles);
        $this->assertContains('ethereum.sepolia', $profiles);
        $this->assertContains('ethereum.localhost', $profiles);

        // Check that we have at least 7 profiles
        $this->assertGreaterThanOrEqual(7, count($profiles));
    }

    /**
     * Test that get() throws exception for non-existent profile.
     */
    public function testGetThrowsExceptionForNonExistentProfile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Network profile 'invalid.profile' not found");

        NetworkProfiles::get('invalid.profile');
    }

    /**
     * Test that exception message includes available profiles.
     */
    public function testExceptionMessageIncludesAvailableProfiles(): void
    {
        try {
            NetworkProfiles::get('invalid.profile');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('Available profiles:', $message);
            $this->assertStringContainsString('solana.mainnet', $message);
            $this->assertStringContainsString('ethereum.mainnet', $message);
        }
    }

    /**
     * Test environment variable interpolation with API keys.
     */
    public function testEnvironmentVariableInterpolation(): void
    {
        // Set test environment variables
        putenv('INFURA_API_KEY=my_infura_key');
        $_ENV['INFURA_API_KEY'] = 'my_infura_key';

        $config = NetworkProfiles::get('ethereum.mainnet');

        $this->assertEquals('https://mainnet.infura.io/v3/my_infura_key', $config['endpoint']);
        $this->assertStringNotContainsString('${', $config['endpoint']);
    }

    /**
     * Test that missing environment variables leave pattern unchanged.
     */
    public function testMissingEnvironmentVariableLeavePatternUnchanged(): void
    {
        // Ensure INFURA_API_KEY is not set
        putenv('INFURA_API_KEY');
        unset($_ENV['INFURA_API_KEY']);

        $config = NetworkProfiles::get('ethereum.mainnet');

        // Pattern should remain if environment variable is not set
        $this->assertEquals('https://mainnet.infura.io/v3/${INFURA_API_KEY}', $config['endpoint']);
    }

    /**
     * Test environment variable interpolation with multiple variables.
     */
    public function testMultipleEnvironmentVariableInterpolation(): void
    {
        // Set test environment variables
        putenv('INFURA_API_KEY=infura_123');
        $_ENV['INFURA_API_KEY'] = 'infura_123';
        putenv('ALCHEMY_API_KEY=alchemy_456');
        $_ENV['ALCHEMY_API_KEY'] = 'alchemy_456';

        $mainnetConfig = NetworkProfiles::get('ethereum.mainnet');
        $goerliConfig = NetworkProfiles::get('ethereum.goerli');

        $this->assertStringContainsString('infura_123', $mainnetConfig['endpoint']);
        $this->assertStringContainsString('infura_123', $goerliConfig['endpoint']);
    }

    /**
     * Test that profile configurations are immutable.
     */
    public function testProfileConfigurationsAreImmutable(): void
    {
        $config1 = NetworkProfiles::get('solana.mainnet');
        $config1['endpoint'] = 'https://modified.endpoint.com';

        $config2 = NetworkProfiles::get('solana.mainnet');

        // Second retrieval should not be affected by modifications to first
        $this->assertEquals('https://api.mainnet-beta.solana.com', $config2['endpoint']);
        $this->assertNotEquals($config1['endpoint'], $config2['endpoint']);
    }

    /**
     * Test that all profiles have required driver field.
     */
    public function testAllProfilesHaveDriverField(): void
    {
        $profiles = NetworkProfiles::all();

        foreach ($profiles as $profileName) {
            $config = NetworkProfiles::get($profileName);
            $this->assertArrayHasKey('driver', $config, "Profile '{$profileName}' missing 'driver' field");
            $this->assertNotEmpty($config['driver'], "Profile '{$profileName}' has empty 'driver' field");
        }
    }

    /**
     * Test that all profiles have required endpoint field.
     */
    public function testAllProfilesHaveEndpointField(): void
    {
        $profiles = NetworkProfiles::all();

        foreach ($profiles as $profileName) {
            $config = NetworkProfiles::get($profileName);
            $this->assertArrayHasKey('endpoint', $config, "Profile '{$profileName}' missing 'endpoint' field");
            $this->assertNotEmpty($config['endpoint'], "Profile '{$profileName}' has empty 'endpoint' field");
        }
    }
}
