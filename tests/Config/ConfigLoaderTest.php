<?php

declare(strict_types=1);

namespace Blockchain\Tests\Config;

use Blockchain\Config\ConfigLoader;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ConfigLoader class.
 *
 * Verifies that the ConfigLoader correctly:
 * - Loads configuration from arrays, environment variables, and files
 * - Validates configuration against driver schemas
 * - Throws appropriate exceptions for invalid configurations
 * - Provides descriptive error messages
 */
class ConfigLoaderTest extends TestCase
{
    /**
     * Temporary test files created during tests.
     *
     * @var array<string>
     */
    private array $tempFiles = [];

    /**
     * Original environment variables to restore after tests.
     *
     * @var array<string,string|false>
     */
    private array $originalEnv = [];

    /**
     * Clean up temporary files and restore environment after each test.
     */
    protected function tearDown(): void
    {
        // Clean up temporary test files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
        $this->originalEnv = [];

        parent::tearDown();
    }

    /**
     * Test fromArray returns the configuration array unchanged.
     */
    public function testFromArrayReturnsConfigUnchanged(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
        ];

        $result = ConfigLoader::fromArray($config);

        $this->assertSame($config, $result);
    }

    /**
     * Test fromArray with empty array.
     */
    public function testFromArrayWithEmptyArray(): void
    {
        $result = ConfigLoader::fromArray([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test fromEnv reads environment variables with default prefix.
     */
    public function testFromEnvReadsEnvironmentVariables(): void
    {
        $this->setEnv('BLOCKCHAIN_RPC_URL', 'https://api.mainnet-beta.solana.com');
        $this->setEnv('BLOCKCHAIN_TIMEOUT', '30');

        $config = ConfigLoader::fromEnv();

        $this->assertArrayHasKey('rpc_url', $config);
        $this->assertSame('https://api.mainnet-beta.solana.com', $config['rpc_url']);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertSame(30, $config['timeout']);
    }

    /**
     * Test fromEnv with custom prefix.
     */
    public function testFromEnvWithCustomPrefix(): void
    {
        $this->setEnv('SOLANA_RPC_URL', 'https://api.devnet.solana.com');
        $this->setEnv('SOLANA_COMMITMENT', 'confirmed');

        $config = ConfigLoader::fromEnv('SOLANA_');

        $this->assertArrayHasKey('rpc_url', $config);
        $this->assertSame('https://api.devnet.solana.com', $config['rpc_url']);
        $this->assertArrayHasKey('commitment', $config);
        $this->assertSame('confirmed', $config['commitment']);
    }

    /**
     * Test fromEnv parses boolean values.
     */
    public function testFromEnvParsesBooleanValues(): void
    {
        $this->setEnv('BLOCKCHAIN_DEBUG', 'true');
        $this->setEnv('BLOCKCHAIN_CACHE_ENABLED', 'false');

        $config = ConfigLoader::fromEnv();

        $this->assertArrayHasKey('debug', $config);
        $this->assertIsBool($config['debug']);
        $this->assertTrue($config['debug']);
        $this->assertArrayHasKey('cache_enabled', $config);
        $this->assertIsBool($config['cache_enabled']);
        $this->assertFalse($config['cache_enabled']);
    }

    /**
     * Test fromEnv parses numeric values.
     */
    public function testFromEnvParsesNumericValues(): void
    {
        $this->setEnv('BLOCKCHAIN_PORT', '8545');
        $this->setEnv('BLOCKCHAIN_RATE_LIMIT', '100.5');

        $config = ConfigLoader::fromEnv();

        $this->assertArrayHasKey('port', $config);
        $this->assertIsInt($config['port']);
        $this->assertSame(8545, $config['port']);
        $this->assertArrayHasKey('rate_limit', $config);
        $this->assertIsFloat($config['rate_limit']);
        $this->assertSame(100.5, $config['rate_limit']);
    }

    /**
     * Test fromEnv returns empty array when no matching variables exist.
     */
    public function testFromEnvReturnsEmptyArrayWhenNoVariables(): void
    {
        $config = ConfigLoader::fromEnv('NONEXISTENT_');

        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    /**
     * Test fromFile loads PHP configuration file.
     */
    public function testFromFileLoadsPhpConfig(): void
    {
        $configData = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ];

        $file = $this->createTempPhpConfig($configData);
        $config = ConfigLoader::fromFile($file);

        $this->assertSame($configData, $config);
    }

    /**
     * Test fromFile loads JSON configuration file.
     */
    public function testFromFileLoadsJsonConfig(): void
    {
        $configData = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ];

        $file = $this->createTempJsonConfig($configData);
        $config = ConfigLoader::fromFile($file);

        $this->assertSame($configData, $config);
    }

    /**
     * Test fromFile throws exception when file doesn't exist.
     */
    public function testFromFileThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Configuration file not found');

        ConfigLoader::fromFile('/nonexistent/path/config.php');
    }

    /**
     * Test fromFile throws exception when PHP file doesn't return array.
     */
    public function testFromFileThrowsExceptionWhenPhpFileNotArray(): void
    {
        $file = $this->createTempFile('invalid.php', "<?php\nreturn 'not an array';");

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('PHP configuration file must return an array');

        ConfigLoader::fromFile($file);
    }

    /**
     * Test fromFile throws exception when JSON is invalid.
     */
    public function testFromFileThrowsExceptionWhenJsonInvalid(): void
    {
        $file = $this->createTempFile('invalid.json', '{invalid json}');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid JSON in configuration file');

        ConfigLoader::fromFile($file);
    }

    /**
     * Test fromFile throws exception for unsupported file format.
     */
    public function testFromFileThrowsExceptionForUnsupportedFormat(): void
    {
        $file = $this->createTempFile('config.yaml', 'key: value');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported configuration file format: yaml');

        ConfigLoader::fromFile($file);
    }

    /**
     * Test validateConfig returns true for valid Solana configuration.
     */
    public function testValidateConfigReturnsTrueForValidSolanaConfig(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
        ];

        $result = ConfigLoader::validateConfig($config, 'solana');

        $this->assertTrue($result);
    }

    /**
     * Test validateConfig accepts endpoint field for backward compatibility.
     */
    public function testValidateConfigAcceptsEndpointField(): void
    {
        $config = [
            'endpoint' => 'https://api.mainnet-beta.solana.com',
        ];

        $result = ConfigLoader::validateConfig($config, 'solana');

        $this->assertTrue($result);
    }

    /**
     * Test validateConfig with all optional Solana fields.
     */
    public function testValidateConfigWithAllSolanaFields(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 30,
            'commitment' => 'finalized',
        ];

        $result = ConfigLoader::validateConfig($config, 'solana');

        $this->assertTrue($result);
    }

    /**
     * Test validateConfig throws exception when required field is missing.
     */
    public function testValidateConfigThrowsExceptionForMissingRequiredField(): void
    {
        $config = [
            'timeout' => 30,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("One of the following fields is required: 'rpc_url' or 'endpoint'");

        ConfigLoader::validateConfig($config, 'solana');
    }

    /**
     * Test validateConfig throws exception for invalid URL.
     */
    public function testValidateConfigThrowsExceptionForInvalidUrl(): void
    {
        $config = [
            'rpc_url' => 'not-a-valid-url',
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('must be a valid URL', $e->getMessage());
            $errors = $e->getErrors();
            $this->assertArrayHasKey('rpc_url', $errors);
        }
    }

    /**
     * Test validateConfig throws exception for non-http URL scheme.
     */
    public function testValidateConfigThrowsExceptionForNonHttpScheme(): void
    {
        $config = [
            'rpc_url' => 'ftp://example.com',
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('http or https scheme', $e->getMessage());
            $errors = $e->getErrors();
            $this->assertArrayHasKey('rpc_url', $errors);
        }
    }

    /**
     * Test validateConfig throws exception for invalid timeout value.
     */
    public function testValidateConfigThrowsExceptionForInvalidTimeout(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => 0,
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('must be greater than or equal to 1', $e->getMessage());
            $errors = $e->getErrors();
            $this->assertArrayHasKey('timeout', $errors);
        }
    }

    /**
     * Test validateConfig throws exception for invalid timeout type.
     */
    public function testValidateConfigThrowsExceptionForInvalidTimeoutType(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'timeout' => '30',
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('must be an integer', $e->getMessage());
            $errors = $e->getErrors();
            $this->assertArrayHasKey('timeout', $errors);
        }
    }

    /**
     * Test validateConfig throws exception for invalid commitment value.
     */
    public function testValidateConfigThrowsExceptionForInvalidCommitment(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'commitment' => 'invalid',
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('must be one of', $e->getMessage());
            $this->assertStringContainsString('finalized', $e->getMessage());
            $this->assertStringContainsString('confirmed', $e->getMessage());
            $this->assertStringContainsString('processed', $e->getMessage());
            $errors = $e->getErrors();
            $this->assertArrayHasKey('commitment', $errors);
        }
    }

    /**
     * Test validateConfig allows unknown fields.
     */
    public function testValidateConfigAllowsUnknownFields(): void
    {
        $config = [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'custom_field' => 'custom_value',
        ];

        $result = ConfigLoader::validateConfig($config, 'solana');

        $this->assertTrue($result);
    }

    /**
     * Test validateConfig with unknown driver returns true for any config.
     */
    public function testValidateConfigWithUnknownDriverReturnsTrue(): void
    {
        $config = [
            'some_field' => 'some_value',
        ];

        $result = ConfigLoader::validateConfig($config, 'unknown_driver');

        $this->assertTrue($result);
    }

    /**
     * Test validateConfig provides detailed error messages for multiple errors.
     */
    public function testValidateConfigProvidesDetailedErrorsForMultipleIssues(): void
    {
        $config = [
            'rpc_url' => 'not-a-url',
            'timeout' => -5,
            'commitment' => 'wrong',
        ];

        try {
            ConfigLoader::validateConfig($config, 'solana');
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(3, $errors);
            $this->assertArrayHasKey('rpc_url', $errors);
            $this->assertArrayHasKey('timeout', $errors);
            $this->assertArrayHasKey('commitment', $errors);
        }
    }

    /**
     * Set an environment variable and store original value for cleanup.
     *
     * @param string $key Environment variable name
     * @param string $value Environment variable value
     */
    private function setEnv(string $key, string $value): void
    {
        if (!isset($this->originalEnv[$key])) {
            $this->originalEnv[$key] = getenv($key);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }

    /**
     * Create a temporary PHP configuration file.
     *
     * @param array<string,mixed> $config Configuration data
     * @return string Path to temporary file
     */
    private function createTempPhpConfig(array $config): string
    {
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        return $this->createTempFile('config.php', $content);
    }

    /**
     * Create a temporary JSON configuration file.
     *
     * @param array<string,mixed> $config Configuration data
     * @return string Path to temporary file
     */
    private function createTempJsonConfig(array $config): string
    {
        $content = json_encode($config, JSON_PRETTY_PRINT);
        return $this->createTempFile('config.json', $content);
    }

    /**
     * Create a temporary file with content.
     *
     * @param string $filename Filename
     * @param string $content File content
     * @return string Path to temporary file
     */
    private function createTempFile(string $filename, string $content): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('phpblockchain_test_') . '_' . $filename;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }
}
