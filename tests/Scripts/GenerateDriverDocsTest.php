<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Test the generate-driver-docs.php script.
 */
class GenerateDriverDocsTest extends TestCase
{
    private string $fixtureDir;
    private string $docsDir;
    private string $scriptPath;

    protected function setUp(): void
    {
        $this->scriptPath = dirname(__DIR__, 2) . '/scripts/generate-driver-docs.php';
        
        // Create temporary directories for testing
        $this->fixtureDir = sys_get_temp_dir() . '/test-drivers-' . uniqid();
        $this->docsDir = sys_get_temp_dir() . '/test-docs-' . uniqid();
        
        mkdir($this->fixtureDir, 0755, true);
        mkdir($this->docsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directories
        if (is_dir($this->fixtureDir)) {
            $this->removeDirectory($this->fixtureDir);
        }
        if (is_dir($this->docsDir)) {
            $this->removeDirectory($this->docsDir);
        }
    }

    public function testScriptExistsAndIsExecutable(): void
    {
        $this->assertFileExists($this->scriptPath);
        $this->assertFileIsReadable($this->scriptPath);
    }

    public function testScriptGeneratesDocForNewDriver(): void
    {
        // Create a fixture driver file
        $driverContent = <<<'PHP'
<?php
namespace Blockchain\Drivers;

class TestDriver implements \Blockchain\Contracts\BlockchainDriverInterface
{
    public function connect(array $config): void {}
    public function getBalance(string $address): float { return 0.0; }
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { return ''; }
    public function getTransaction(string $hash): array { return []; }
    public function getBlock(int|string $blockIdentifier): array { return []; }
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int { return null; }
    public function getTokenBalance(string $address, string $tokenAddress): ?float { return null; }
    public function getNetworkInfo(): ?array { return null; }
}
PHP;
        
        file_put_contents($this->fixtureDir . '/TestDriver.php', $driverContent);

        // Run the generation script on our fixture
        $output = $this->runScript($this->fixtureDir, $this->docsDir);

        // Check that documentation was created
        $expectedDocPath = $this->docsDir . '/test.md';
        $this->assertFileExists($expectedDocPath);

        // Verify the content contains expected sections
        $docContent = file_get_contents($expectedDocPath);
        $this->assertStringContainsString('# Test Driver', $docContent);
        $this->assertStringContainsString('## Overview', $docContent);
        $this->assertStringContainsString('TestDriver', $docContent);

        // Verify output indicates creation
        $this->assertStringContainsString('Created documentation', $output);
    }

    public function testScriptSkipsExistingDocs(): void
    {
        // Create a fixture driver file
        file_put_contents(
            $this->fixtureDir . '/ExistingDriver.php',
            '<?php namespace Blockchain\Drivers; class ExistingDriver {}'
        );

        // Create existing documentation
        mkdir($this->docsDir, 0755, true);
        file_put_contents($this->docsDir . '/existing.md', '# Existing Driver');

        // Run the generation script
        $output = $this->runScript($this->fixtureDir, $this->docsDir);

        // Verify output indicates skipping
        $this->assertStringContainsString('Skipped existing documentation', $output);
        $this->assertStringContainsString('existing.md', $output);
    }

    public function testScriptHandlesNoDriverFiles(): void
    {
        // Run script on empty directory
        $output = $this->runScript($this->fixtureDir, $this->docsDir);

        // Should complete successfully with appropriate message
        $this->assertStringContainsString('Documentation generation complete', $output);
    }

    public function testScriptHandlesMissingDriversDirectory(): void
    {
        $nonExistentDir = sys_get_temp_dir() . '/non-existent-' . uniqid();
        
        // Run script with non-existent directory
        $output = $this->runScript($nonExistentDir, $this->docsDir, $exitCode);

        // Should fail with error code
        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('Drivers directory not found', $output);
    }

    public function testScriptCreatesDocsDirectoryIfMissing(): void
    {
        // Create driver but no docs directory
        file_put_contents(
            $this->fixtureDir . '/NewDriver.php',
            '<?php namespace Blockchain\Drivers; class NewDriver {}'
        );

        $newDocsDir = sys_get_temp_dir() . '/test-new-docs-' . uniqid();
        
        // Run script - it should create the docs directory
        $this->runScript($this->fixtureDir, $newDocsDir);

        // Verify docs directory was created
        $this->assertDirectoryExists($newDocsDir);

        // Clean up
        if (is_dir($newDocsDir)) {
            $this->removeDirectory($newDocsDir);
        }
    }

    public function testGeneratedDocContainsInterfaceMethods(): void
    {
        // Create a fixture driver file
        file_put_contents(
            $this->fixtureDir . '/MethodDriver.php',
            '<?php namespace Blockchain\Drivers; class MethodDriver {}'
        );

        // Run the generation script
        $this->runScript($this->fixtureDir, $this->docsDir);

        $docContent = file_get_contents($this->docsDir . '/method.md');

        // Check for standard interface methods
        $this->assertStringContainsString('connect', $docContent);
        $this->assertStringContainsString('getBalance', $docContent);
        $this->assertStringContainsString('sendTransaction', $docContent);
    }

    /**
     * Run the generate-driver-docs.php script with custom directories.
     *
     * @param string $driversDir Path to drivers directory
     * @param string $docsDir Path to docs directory
     * @param int|null $exitCode Variable to store exit code
     *
     * @return string Script output
     */
    private function runScript(string $driversDir, string $docsDir, ?int &$exitCode = null): string
    {
        // Execute the script with custom directory arguments
        $cmd = sprintf(
            'php %s --drivers-dir=%s --docs-dir=%s 2>&1',
            escapeshellarg($this->scriptPath),
            escapeshellarg($driversDir),
            escapeshellarg($docsDir)
        );
        exec($cmd, $output, $exitCode);
        return implode("\n", $output);
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * @param string $dir Directory path
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
