<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\Tasks\UpdateReadmeTask;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for UpdateReadmeTask.
 *
 * @package Blockchain\Tests\Agent
 */
class UpdateReadmeTaskTest extends TestCase
{
    private UpdateReadmeTask $task;
    private TaskRegistry $registry;
    private OperatorConsole $console;
    private string $projectRoot;
    private string $testOutputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test project root
        $this->projectRoot = dirname(__DIR__, 2);
        $this->testOutputDir = sys_get_temp_dir() . '/update-readme-test-' . uniqid();
        
        // Create test output directory
        mkdir($this->testOutputDir, 0755, true);

        // Create mocked console that auto-approves
        $auditLogPath = $this->testOutputDir . '/audit.log';
        $this->console = new OperatorConsole($auditLogPath, false);
        $this->console->setSimulatedApproval(true);

        // Create registry instance
        $this->registry = new TaskRegistry();

        // Create task instance
        $this->task = new UpdateReadmeTask(
            $this->registry,
            $this->console,
            $this->projectRoot
        );
    }

    protected function tearDown(): void
    {
        // Clean up test output directory
        $this->cleanupDirectory($this->testOutputDir);
        
        parent::tearDown();
    }

    public function testExecutePreviewOnlyMode(): void
    {
        $inputs = [
            'update_type' => 'drivers',
            'preview_only' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['preview_only']);
        $this->assertArrayHasKey('drivers_found', $result);
        $this->assertArrayHasKey('preview_diff', $result);
        $this->assertArrayHasKey('diff_summary', $result);
        $this->assertIsArray($result['drivers_found']);
    }

    public function testExecuteUpdatesDriverDocumentation(): void
    {
        // Use preview mode to avoid modifying actual files
        $inputs = [
            'update_type' => 'drivers',
            'preview_only' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['changes_proposed']);
    }

    public function testExecuteUpdatesAllDocumentation(): void
    {
        $inputs = [
            'update_type' => 'all',
            'preview_only' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('drivers_found', $result);
    }

    public function testExecuteWithSpecificDrivers(): void
    {
        $inputs = [
            'update_type' => 'drivers',
            'driver_names' => ['Solana'],
            'preview_only' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['drivers_found']);
    }

    public function testExecuteInvalidUpdateType(): void
    {
        $inputs = [
            'update_type' => 'invalid_type',
        ];

        $this->expectException(ValidationException::class);

        $this->task->execute($inputs);
    }

    public function testDiscoverDriversFindsExistingDrivers(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('discoverDrivers');
        $method->setAccessible(true);

        $drivers = $method->invoke($this->task, []);

        $this->assertIsArray($drivers);
        // Should find at least Solana driver in the project
        $this->assertNotEmpty($drivers);
    }

    public function testExtractDriverInfoParsesDriverFile(): void
    {
        // Create a test driver file
        $testDriver = $this->testOutputDir . '/TestDriver.php';
        $driverCode = <<<'PHP'
<?php
namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;

/**
 * TestDriver for testing purposes.
 * @network-type non-evm
 */
class TestDriver implements BlockchainDriverInterface
{
    private const CURRENCY = 'TEST';
    
    public function connect(array $config): void {}
    public function getBalance(string $address): float { return 0.0; }
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { return ''; }
    public function getTransaction(string $hash): array { return []; }
    public function getBlock(string|int $blockIdentifier): array { return []; }
    public function estimateGas(array $transaction): int { return 0; }
    public function getTokenBalance(string $address, string $tokenAddress): float { return 0.0; }
    public function getNetworkInfo(): array { return []; }
}
PHP;
        file_put_contents($testDriver, $driverCode);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('extractDriverInfo');
        $method->setAccessible(true);

        $info = $method->invoke($this->task, $testDriver);

        $this->assertIsArray($info);
        $this->assertEquals('Test', $info['name']);
        $this->assertEquals('TestDriver', $info['class']);
        $this->assertEquals('Non-EVM', $info['network_type']);
        $this->assertEquals('TEST', $info['native_currency']);
    }

    public function testDetermineDriverStatusIdentifiesReadyDriver(): void
    {
        // Create a complete driver file
        $testDriver = $this->testOutputDir . '/CompleteDriver.php';
        $driverCode = <<<'PHP'
<?php
namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;

class CompleteDriver implements BlockchainDriverInterface
{
    public function connect(array $config): void {
        // Implementation
        $this->endpoint = $config['endpoint'];
    }
    
    public function getBalance(string $address): float {
        // Implementation
        return 100.0;
    }
    
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string { return 'tx'; }
    public function getTransaction(string $hash): array { return []; }
    public function getBlock(string|int $blockIdentifier): array { return []; }
    public function estimateGas(array $transaction): int { return 21000; }
    public function getTokenBalance(string $address, string $tokenAddress): float { return 0.0; }
    public function getNetworkInfo(): array { return []; }
}
PHP;
        file_put_contents($testDriver, $driverCode);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('determineDriverStatus');
        $method->setAccessible(true);

        $status = $method->invoke($this->task, $testDriver);

        $this->assertEquals('✅ Ready', $status);
    }

    public function testDetermineDriverStatusIdentifiesPlannedDriver(): void
    {
        // Create a driver with many TODOs
        $testDriver = $this->testOutputDir . '/PlannedDriver.php';
        $driverCode = <<<'PHP'
<?php
namespace Blockchain\Drivers;

class PlannedDriver
{
    // TODO: Implement connect
    // TODO: Implement getBalance
    // TODO: Implement sendTransaction
    // TODO: Implement getTransaction
    // TODO: Implement getBlock
    // TODO: Implement estimateGas
}
PHP;
        file_put_contents($testDriver, $driverCode);

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('determineDriverStatus');
        $method->setAccessible(true);

        $status = $method->invoke($this->task, $testDriver);

        $this->assertEquals('🔄 Planned', $status);
    }

    public function testGenerateDriverDocumentationCreatesValidMarkdown(): void
    {
        $driverInfo = [
            'name' => 'Test',
            'class' => 'TestDriver',
            'network_type' => 'Non-EVM',
            'native_currency' => 'TEST',
            'status' => '✅ Ready',
        ];

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('generateDriverDocumentation');
        $method->setAccessible(true);

        $doc = $method->invoke($this->task, $driverInfo, null);

        $this->assertStringContainsString('# Test Driver', $doc);
        $this->assertStringContainsString('TestDriver', $doc);
        $this->assertStringContainsString('Non-EVM', $doc);
        $this->assertStringContainsString('TEST', $doc);
        $this->assertStringContainsString('## Basic Usage', $doc);
        $this->assertStringContainsString('use Blockchain\\BlockchainManager', $doc);
    }

    public function testGenerateDiffCreatesValidDiff(): void
    {
        $change = [
            'file' => $this->testOutputDir . '/test.md',
            'old_content' => "Line 1\nLine 2\nLine 3\n",
            'new_content' => "Line 1\nLine 2 Modified\nLine 3\nLine 4\n",
            'type' => 'test',
        ];

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('generateDiff');
        $method->setAccessible(true);

        $diff = $method->invoke($this->task, $change);

        $this->assertIsArray($diff);
        $this->assertArrayHasKey('file', $diff);
        $this->assertArrayHasKey('additions', $diff);
        $this->assertArrayHasKey('deletions', $diff);
        $this->assertArrayHasKey('unified_diff', $diff);
        $this->assertGreaterThan(0, $diff['additions']);
    }

    public function testUpdateReadmeDriverTableGeneratesCorrectFormat(): void
    {
        $drivers = [
            'Test' => [
                'name' => 'Test',
                'class' => 'TestDriver',
                'status' => '✅ Ready',
                'network_type' => 'Non-EVM',
                'has_documentation' => true,
            ],
        ];

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('updateReadmeDriverTable');
        $method->setAccessible(true);

        $changes = $method->invoke($this->task, $drivers);

        // Should return empty array if README doesn't need updating or doesn't exist
        $this->assertIsArray($changes);
    }

    /**
     * Clean up a directory recursively.
     *
     * @param string $directory Directory path
     */
    private function cleanupDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($directory);
    }
}
