<?php

declare(strict_types=1);

namespace Blockchain\Tests\Bin;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for switch-network.php CLI script.
 *
 * These tests verify the behavior of the network profile switcher CLI tool.
 * Since the CLI script uses functions in the global namespace, we execute it
 * as a separate process and validate its output and exit codes.
 */
class SwitchNetworkTest extends TestCase
{
    /**
     * Path to the CLI script
     */
    private string $scriptPath;

    /**
     * Temporary directory for test outputs
     */
    private string $tempDir;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->scriptPath = __DIR__ . '/../../bin/switch-network.php';
        $this->tempDir = sys_get_temp_dir() . '/switch-network-test-' . uniqid();
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Clean up test environment
     */
    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Execute the CLI script and return output, error, and exit code
     *
     * @param string $args Command-line arguments
     * @return array{output: string, error: string, exitCode: int}
     */
    private function executeScript(string $args): array
    {
        $command = sprintf('php %s %s 2>&1', escapeshellarg($this->scriptPath), $args);
        
        exec($command, $output, $exitCode);
        
        $outputStr = implode("\n", $output);
        
        // Separate stdout and stderr (simple heuristic)
        $errorStr = '';
        if (str_contains($outputStr, 'Error:')) {
            $parts = explode('Error:', $outputStr, 2);
            if (count($parts) === 2) {
                $errorStr = 'Error:' . $parts[1];
            }
        }
        
        return [
            'output' => $outputStr,
            'error' => $errorStr,
            'exitCode' => $exitCode,
        ];
    }

    /**
     * Test that --help flag displays usage information and exits with code 0
     */
    public function testHelpFlagDisplaysUsage(): void
    {
        $result = $this->executeScript('--help');

        $this->assertEquals(0, $result['exitCode'], 'Help should exit with code 0');
        $this->assertStringContainsString('Usage:', $result['output']);
        $this->assertStringContainsString('Options:', $result['output']);
        $this->assertStringContainsString('Available Profiles:', $result['output']);
        $this->assertStringContainsString('solana.mainnet', $result['output']);
        $this->assertStringContainsString('ethereum.localhost', $result['output']);
    }

    /**
     * Test that missing profile argument shows usage and exits with code 1
     */
    public function testMissingProfileArgumentShowsUsage(): void
    {
        $result = $this->executeScript('');

        $this->assertEquals(1, $result['exitCode'], 'Missing profile should exit with code 1');
        $this->assertStringContainsString('Usage:', $result['output']);
        $this->assertStringContainsString('Available Profiles:', $result['output']);
    }

    /**
     * Test JSON output format (default)
     */
    public function testJsonOutputFormat(): void
    {
        $result = $this->executeScript('solana.mainnet');

        $this->assertEquals(0, $result['exitCode'], 'Valid profile should exit with code 0');
        
        // Verify it's valid JSON
        $decoded = json_decode($result['output'], true);
        $this->assertIsArray($decoded);
        $this->assertEquals('solana', $decoded['driver']);
        $this->assertEquals('https://api.mainnet-beta.solana.com', $decoded['endpoint']);
        $this->assertEquals(30, $decoded['timeout']);
        $this->assertEquals('finalized', $decoded['commitment']);
    }

    /**
     * Test PHP array output format
     */
    public function testPhpOutputFormat(): void
    {
        $result = $this->executeScript('ethereum.localhost --format=php');

        $this->assertEquals(0, $result['exitCode']);
        $this->assertStringContainsString('<?php', $result['output']);
        $this->assertStringContainsString('return array', $result['output']);
        $this->assertStringContainsString("'driver' => 'ethereum'", $result['output']);
        $this->assertStringContainsString("'endpoint' => 'http://localhost:8545'", $result['output']);
    }

    /**
     * Test ENV format output
     */
    public function testEnvOutputFormat(): void
    {
        $result = $this->executeScript('solana.devnet --format=env');

        $this->assertEquals(0, $result['exitCode']);
        $this->assertStringContainsString('DRIVER=solana', $result['output']);
        $this->assertStringContainsString('ENDPOINT=https://api.devnet.solana.com', $result['output']);
        $this->assertStringContainsString('TIMEOUT=30', $result['output']);
        $this->assertStringContainsString('COMMITMENT=finalized', $result['output']);
    }

    /**
     * Test that invalid profile name returns error and exits with code 1
     */
    public function testInvalidProfileReturnsError(): void
    {
        $result = $this->executeScript('invalid.profile');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertStringContainsString('Error:', $result['output']);
        $this->assertStringContainsString('invalid.profile', $result['output']);
        $this->assertStringContainsString('Available profiles:', $result['output']);
    }

    /**
     * Test that invalid format returns error
     */
    public function testInvalidFormatReturnsError(): void
    {
        $result = $this->executeScript('solana.mainnet --format=xml');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertStringContainsString('Error:', $result['output']);
        $this->assertStringContainsString('Invalid format', $result['output']);
    }

    /**
     * Test writing to file with --output flag
     */
    public function testWriteToFile(): void
    {
        $outputFile = $this->tempDir . '/config.json';
        $result = $this->executeScript("solana.mainnet --output={$outputFile} --force");

        $this->assertEquals(0, $result['exitCode']);
        $this->assertStringContainsString('Configuration written to:', $result['output']);
        $this->assertFileExists($outputFile);

        // Verify file contents
        $contents = file_get_contents($outputFile);
        $this->assertNotFalse($contents);
        
        $decoded = json_decode($contents, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('solana', $decoded['driver']);
    }

    /**
     * Test writing PHP format to file
     */
    public function testWritePhpFormatToFile(): void
    {
        $outputFile = $this->tempDir . '/config.php';
        $result = $this->executeScript("ethereum.localhost --output={$outputFile} --format=php --force");

        $this->assertEquals(0, $result['exitCode']);
        $this->assertFileExists($outputFile);

        // Verify file contents
        $contents = file_get_contents($outputFile);
        $this->assertNotFalse($contents);
        $this->assertStringContainsString('<?php', $contents);
        $this->assertStringContainsString('return array', $contents);
    }

    /**
     * Test writing ENV format to file
     */
    public function testWriteEnvFormatToFile(): void
    {
        $outputFile = $this->tempDir . '/config.env';
        $result = $this->executeScript("solana.testnet --output={$outputFile} --format=env --force");

        $this->assertEquals(0, $result['exitCode']);
        $this->assertFileExists($outputFile);

        // Verify file contents
        $contents = file_get_contents($outputFile);
        $this->assertNotFalse($contents);
        $this->assertStringContainsString('DRIVER=solana', $contents);
        $this->assertStringContainsString('ENDPOINT=', $contents);
    }

    /**
     * Test that directory is created if it doesn't exist
     */
    public function testCreatesDirectoryIfNotExists(): void
    {
        $subDir = $this->tempDir . '/nested/deep/path';
        $outputFile = $subDir . '/config.json';
        
        $this->assertFalse(is_dir($subDir), 'Directory should not exist initially');
        
        $result = $this->executeScript("solana.mainnet --output={$outputFile} --force");

        $this->assertEquals(0, $result['exitCode']);
        $this->assertDirectoryExists($subDir);
        $this->assertFileExists($outputFile);

        // Clean up nested directories
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
        if (is_dir($subDir)) {
            rmdir($subDir);
            rmdir(dirname($subDir));
            rmdir(dirname(dirname($subDir)));
        }
    }

    /**
     * Test that --dry-run flag prevents file writing
     */
    public function testDryRunPreventsFileWriting(): void
    {
        $outputFile = $this->tempDir . '/config.json';
        $result = $this->executeScript("solana.mainnet --output={$outputFile} --dry-run");

        // In dry-run mode, output should go to stdout
        $this->assertEquals(0, $result['exitCode']);
        
        // File should not be created
        $this->assertFalse(file_exists($outputFile), 'File should not exist in dry-run mode');
        
        // Output should contain JSON
        $decoded = json_decode($result['output'], true);
        $this->assertIsArray($decoded);
    }

    /**
     * Test all available profiles can be retrieved
     */
    public function testAllProfilesCanBeRetrieved(): void
    {
        $profiles = [
            'solana.mainnet',
            'solana.devnet',
            'solana.testnet',
            'ethereum.mainnet',
            'ethereum.goerli',
            'ethereum.sepolia',
            'ethereum.localhost',
        ];

        foreach ($profiles as $profile) {
            $result = $this->executeScript($profile);
            
            $this->assertEquals(0, $result['exitCode'], "Profile '{$profile}' should be valid");
            
            $decoded = json_decode($result['output'], true);
            $this->assertIsArray($decoded, "Profile '{$profile}' should produce valid JSON");
            $this->assertArrayHasKey('driver', $decoded);
            $this->assertArrayHasKey('endpoint', $decoded);
        }
    }

    /**
     * Test that file permissions are set correctly (0600)
     */
    public function testFilePermissionsAreSetCorrectly(): void
    {
        $outputFile = $this->tempDir . '/config.json';
        $result = $this->executeScript("solana.mainnet --output={$outputFile} --force");

        $this->assertEquals(0, $result['exitCode']);
        $this->assertFileExists($outputFile);

        // Check file permissions
        $perms = fileperms($outputFile);
        $octal = substr(sprintf('%o', $perms), -4);
        
        // Should be 0600 (owner read/write only)
        $this->assertEquals('0600', $octal, 'File should have 0600 permissions');
    }

    /**
     * Test output format with all three formats
     */
    public function testAllOutputFormatsAreValid(): void
    {
        $formats = ['json', 'php', 'env'];

        foreach ($formats as $format) {
            $result = $this->executeScript("ethereum.localhost --format={$format}");
            
            $this->assertEquals(0, $result['exitCode'], "Format '{$format}' should succeed");
            
            // Validate format-specific output
            switch ($format) {
                case 'json':
                    $decoded = json_decode($result['output'], true);
                    $this->assertIsArray($decoded);
                    $this->assertArrayHasKey('driver', $decoded);
                    break;
                case 'php':
                    $this->assertStringContainsString('<?php', $result['output']);
                    $this->assertStringContainsString('return array', $result['output']);
                    break;
                case 'env':
                    $this->assertStringContainsString('DRIVER=', $result['output']);
                    $this->assertStringContainsString('ENDPOINT=', $result['output']);
                    break;
            }
        }
    }

    /**
     * Test that script exits with code 0 on success in dry-run mode
     */
    public function testExitsWithZeroOnSuccessInDryRun(): void
    {
        $result = $this->executeScript('solana.mainnet');
        
        $this->assertEquals(0, $result['exitCode'], 'Dry-run mode should exit with code 0 on success');
    }

    /**
     * Test that script exits with code 0 on successful file write
     */
    public function testExitsWithZeroOnSuccessfulFileWrite(): void
    {
        $outputFile = $this->tempDir . '/config.json';
        $result = $this->executeScript("solana.mainnet --output={$outputFile} --force");
        
        $this->assertEquals(0, $result['exitCode'], 'File write should exit with code 0 on success');
    }
}
