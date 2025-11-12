<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Test the check-driver-docs.php script.
 */
class CheckDriverDocsTest extends TestCase
{
    private string $fixtureDir;
    private string $docsDir;
    private string $scriptPath;

    protected function setUp(): void
    {
        $this->scriptPath = dirname(__DIR__, 2) . '/scripts/check-driver-docs.php';
        
        // Create temporary directories for testing
        $this->fixtureDir = sys_get_temp_dir() . '/test-drivers-check-' . uniqid();
        $this->docsDir = sys_get_temp_dir() . '/test-docs-check-' . uniqid();
        
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

    public function testScriptPassesWhenAllDocsExist(): void
    {
        // Create driver and documentation
        file_put_contents(
            $this->fixtureDir . '/CompleteDriver.php',
            '<?php namespace Blockchain\Drivers; class CompleteDriver {}'
        );
        
        $docContent = <<<'MD'
# Complete Driver

## Overview
This is a complete driver.

## Installation
Install via composer.
MD;
        
        file_put_contents($this->docsDir . '/complete.md', $docContent);

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should pass with exit code 0
        $this->assertEquals(0, $exitCode, "Script should pass when all docs exist. Output: $output");
        $this->assertStringContainsString('Valid driver documentation', $output);
        $this->assertStringContainsString('CompleteDriver', $output);
    }

    public function testScriptFailsWhenDocsAreMissing(): void
    {
        // Create driver without documentation
        file_put_contents(
            $this->fixtureDir . '/MissingDriver.php',
            '<?php namespace Blockchain\Drivers; class MissingDriver {}'
        );

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should fail with exit code 1
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Missing driver documentation', $output);
        $this->assertStringContainsString('MissingDriver', $output);
    }

    public function testScriptDetectsIncompleteDocs(): void
    {
        // Create driver
        file_put_contents(
            $this->fixtureDir . '/IncompleteDriver.php',
            '<?php namespace Blockchain\Drivers; class IncompleteDriver {}'
        );

        // Create incomplete documentation (missing required sections)
        $incompleteDoc = <<<'MD'
# Incomplete Driver

Some content but no proper sections.
MD;
        
        file_put_contents($this->docsDir . '/incomplete.md', $incompleteDoc);

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should fail
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Incomplete driver documentation', $output);
        $this->assertStringContainsString('IncompleteDriver', $output);
    }

    public function testScriptChecksRequiredSections(): void
    {
        // Create driver
        file_put_contents(
            $this->fixtureDir . '/SectionDriver.php',
            '<?php namespace Blockchain\Drivers; class SectionDriver {}'
        );

        // Create doc with Overview but no Installation
        $docContent = <<<'MD'
# Section Driver

## Overview
This driver has overview.

## Usage
But no installation section.
MD;
        
        file_put_contents($this->docsDir . '/section.md', $docContent);

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should fail and mention missing Installation
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Installation', $output);
    }

    public function testScriptHandlesMissingDriversDirectory(): void
    {
        $nonExistentDir = sys_get_temp_dir() . '/non-existent-check-' . uniqid();
        
        // Run script with non-existent directory
        $output = $this->runScript($nonExistentDir, $this->docsDir, $exitCode);

        // Should fail with error code 2
        $this->assertEquals(2, $exitCode);
        $this->assertStringContainsString('Drivers directory not found', $output);
    }

    public function testScriptHandlesMissingDocsDirectory(): void
    {
        // Create driver directory but no docs directory
        file_put_contents(
            $this->fixtureDir . '/TestDriver.php',
            '<?php namespace Blockchain\Drivers; class TestDriver {}'
        );

        $nonExistentDocsDir = sys_get_temp_dir() . '/non-existent-docs-' . uniqid();

        // Run script
        $output = $this->runScript($this->fixtureDir, $nonExistentDocsDir, $exitCode);

        // Should fail with error code 2
        $this->assertEquals(2, $exitCode);
        $this->assertStringContainsString('Docs directory not found', $output);
    }

    public function testScriptHandlesEmptyDriversDirectory(): void
    {
        // Empty drivers directory
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should pass (no drivers to check)
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('All drivers have complete documentation', $output);
    }

    public function testScriptHandlesMultipleDrivers(): void
    {
        // Create multiple drivers with docs
        file_put_contents(
            $this->fixtureDir . '/FirstDriver.php',
            '<?php namespace Blockchain\Drivers; class FirstDriver {}'
        );
        file_put_contents(
            $this->fixtureDir . '/SecondDriver.php',
            '<?php namespace Blockchain\Drivers; class SecondDriver {}'
        );

        // Create documentation for both
        $docTemplate = <<<'MD'
# %s Driver

## Overview
Driver overview.

## Installation
Installation instructions.
MD;

        file_put_contents($this->docsDir . '/first.md', sprintf($docTemplate, 'First'));
        file_put_contents($this->docsDir . '/second.md', sprintf($docTemplate, 'Second'));

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should pass
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('FirstDriver', $output);
        $this->assertStringContainsString('SecondDriver', $output);
    }

    public function testScriptDetectsMixedCompleteAndIncomplete(): void
    {
        // Create two drivers
        file_put_contents(
            $this->fixtureDir . '/GoodDriver.php',
            '<?php namespace Blockchain\Drivers; class GoodDriver {}'
        );
        file_put_contents(
            $this->fixtureDir . '/BadDriver.php',
            '<?php namespace Blockchain\Drivers; class BadDriver {}'
        );

        // Complete doc for first driver
        $completeDoc = <<<'MD'
# Good Driver

## Overview
Complete overview.

## Installation
Complete installation.
MD;
        file_put_contents($this->docsDir . '/good.md', $completeDoc);

        // Incomplete doc for second driver
        file_put_contents($this->docsDir . '/bad.md', '# Bad Driver');

        // Run the check script
        $output = $this->runScript($this->fixtureDir, $this->docsDir, $exitCode);

        // Should fail overall
        $this->assertEquals(1, $exitCode);
        
        // Should show both valid and incomplete
        $this->assertStringContainsString('Valid driver documentation', $output);
        $this->assertStringContainsString('GoodDriver', $output);
        $this->assertStringContainsString('Incomplete driver documentation', $output);
        $this->assertStringContainsString('BadDriver', $output);
    }

    /**
     * Run the check-driver-docs.php script with custom directories.
     *
     * @param string $driversDir Path to drivers directory
     * @param string $docsDir Path to docs directory
     * @param int|null $exitCode Variable to store exit code
     *
     * @return string Script output
     */
    private function runScript(string $driversDir, string $docsDir, ?int &$exitCode = null): string
    {
        // Run the check-driver-docs.php script with custom directories as arguments
        $cmd = "php " . escapeshellarg($this->scriptPath) . " " . escapeshellarg($driversDir) . " " . escapeshellarg($docsDir) . " 2>&1";
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
