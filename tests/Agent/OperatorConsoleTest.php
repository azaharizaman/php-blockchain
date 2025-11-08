<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for OperatorConsole class.
 *
 * Verifies that the OperatorConsole correctly:
 * - Manages operator approval workflow
 * - Records audit trail entries
 * - Validates file path access
 * - Provides audit statistics
 */
class OperatorConsoleTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        // Clean up temporary test files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    public function testConstructorCreatesAuditLogDirectory(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $this->assertFileExists(dirname($logPath));
    }

    public function testRequestApprovalAutoApprovesWhenNotRequired(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => [
                'requires_approval' => false,
            ],
        ];
        
        $approved = $console->requestApproval('test-task', $task, []);
        
        $this->assertTrue($approved);
    }

    public function testRequestApprovalWithSimulatedApproval(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => [
                'requires_approval' => true,
            ],
        ];
        
        $approved = $console->requestApproval('test-task', $task, ['input' => 'value']);
        
        $this->assertTrue($approved);
    }

    public function testRequestApprovalWithSimulatedDenial(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(false);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => [
                'requires_approval' => true,
            ],
        ];
        
        $approved = $console->requestApproval('test-task', $task, ['input' => 'value']);
        
        $this->assertFalse($approved);
    }

    public function testRequestApprovalLogsOperation(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => [
                'requires_approval' => true,
            ],
        ];
        
        $inputs = ['driver_name' => 'ethereum'];
        $affectedPaths = ['src/Drivers/EthereumDriver.php'];
        
        $console->requestApproval('test-task', $task, $inputs, $affectedPaths);
        
        $this->assertFileExists($logPath);
        $logContent = file_get_contents($logPath);
        $this->assertNotFalse($logContent);
        $this->assertStringContainsString('test-task', $logContent);
        $this->assertStringContainsString('ethereum', $logContent);
        $this->assertStringContainsString('APPROVED', $logContent);
    }

    public function testReadAuditLogReturnsEntries(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task-1',
            'name' => 'Test Task 1',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        $console->requestApproval('test-task-1', $task, ['input1' => 'value1']);
        $console->requestApproval('test-task-1', $task, ['input2' => 'value2']);
        
        $entries = $console->readAuditLog();
        
        $this->assertIsArray($entries);
        $this->assertCount(2, $entries);
        $this->assertArrayHasKey('timestamp', $entries[0]);
        $this->assertArrayHasKey('task_id', $entries[0]);
        $this->assertArrayHasKey('operator', $entries[0]);
        $this->assertArrayHasKey('outcome', $entries[0]);
    }

    public function testReadAuditLogWithLimit(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        // Create 5 entries
        for ($i = 0; $i < 5; $i++) {
            $console->requestApproval('test-task', $task, ['iteration' => $i]);
        }
        
        $entries = $console->readAuditLog(3);
        
        $this->assertCount(3, $entries);
    }

    public function testReadAuditLogWithTaskFilter(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task1 = [
            'id' => 'task-1',
            'name' => 'Task 1',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        $task2 = [
            'id' => 'task-2',
            'name' => 'Task 2',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        $console->requestApproval('task-1', $task1, []);
        $console->requestApproval('task-2', $task2, []);
        $console->requestApproval('task-1', $task1, []);
        
        $entries = $console->readAuditLog(null, 'task-1');
        
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertEquals('task-1', $entry['task_id']);
        }
    }

    public function testReadAuditLogReturnsEmptyWhenFileDoesNotExist(): void
    {
        $logPath = $this->getTempPath('nonexistent-audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $entries = $console->readAuditLog();
        
        $this->assertIsArray($entries);
        $this->assertEmpty($entries);
    }

    public function testGetAuditStatsReturnsStatistics(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        // Create approved and denied entries
        $console->setSimulatedApproval(true);
        $console->requestApproval('test-task', $task, []);
        $console->requestApproval('test-task', $task, []);
        
        $console->setSimulatedApproval(false);
        $console->requestApproval('test-task', $task, []);
        
        $stats = $console->getAuditStats();
        
        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_operations']);
        $this->assertEquals(2, $stats['approved']);
        $this->assertEquals(1, $stats['denied']);
        $this->assertArrayHasKey('by_task', $stats);
        $this->assertEquals(3, $stats['by_task']['test-task']);
    }

    public function testValidatePathsSucceedsForAllowedPaths(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $registry = new TaskRegistry();
        
        $paths = [
            'src/Drivers/EthereumDriver.php',
            'tests/Drivers/EthereumDriverTest.php',
        ];
        
        // Should not throw exception
        $console->validatePaths($registry, 'create-driver', $paths);
        
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testValidatePathsThrowsExceptionForDeniedPaths(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $registry = new TaskRegistry();
        
        $paths = [
            'src/Drivers/EthereumDriver.php',
            '.env', // This should be denied
        ];
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not have permission');
        
        $console->validatePaths($registry, 'create-driver', $paths);
    }

    public function testValidatePathsThrowsExceptionForUnallowedPaths(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $registry = new TaskRegistry();
        
        $paths = [
            'vendor/autoload.php', // Not in allowed_paths
        ];
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not have permission');
        
        $console->validatePaths($registry, 'create-driver', $paths);
    }

    public function testClearAuditLogRemovesContent(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        $console->requestApproval('test-task', $task, []);
        
        $this->assertFileExists($logPath);
        $this->assertGreaterThan(0, filesize($logPath));
        
        $console->clearAuditLog();
        
        $this->assertEquals(0, filesize($logPath));
    }

    public function testSetSimulatedApprovalChangesMode(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        // Test approval
        $console->setSimulatedApproval(true);
        $result1 = $console->requestApproval('test-task', $task, []);
        $this->assertTrue($result1);
        
        // Test denial
        $console->setSimulatedApproval(false);
        $result2 = $console->requestApproval('test-task', $task, []);
        $this->assertFalse($result2);
    }

    public function testRequestApprovalRecordsAffectedPaths(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        $console->setSimulatedApproval(true);
        
        $task = [
            'id' => 'test-task',
            'name' => 'Test Task',
            'safety_flags' => ['requires_approval' => true],
        ];
        
        $affectedPaths = [
            'src/Drivers/Driver1.php',
            'src/Drivers/Driver2.php',
            'tests/Drivers/Driver1Test.php',
        ];
        
        $console->requestApproval('test-task', $task, [], $affectedPaths);
        
        $entries = $console->readAuditLog();
        
        $this->assertCount(1, $entries);
        $this->assertArrayHasKey('affected_paths', $entries[0]);
        $this->assertCount(3, $entries[0]['affected_paths']);
        $this->assertContains('src/Drivers/Driver1.php', $entries[0]['affected_paths']);
    }

    public function testGetAuditStatsWithNoEntriesReturnsZeros(): void
    {
        $logPath = $this->getTempPath('audit.log');
        $console = new OperatorConsole($logPath, false);
        
        $stats = $console->getAuditStats();
        
        $this->assertEquals(0, $stats['total_operations']);
        $this->assertEquals(0, $stats['approved']);
        $this->assertEquals(0, $stats['denied']);
        $this->assertEmpty($stats['by_task']);
        $this->assertEmpty($stats['by_operator']);
    }

    /**
     * Get a temporary file path for testing.
     *
     * @param string $filename Filename
     * @return string Path to temporary file
     */
    private function getTempPath(string $filename): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('phpblockchain_test_') . '_' . $filename;
        $this->tempFiles[] = $path;
        // Also track directory for cleanup
        $dir = dirname($path);
        if (!in_array($dir, $this->tempFiles, true)) {
            $this->tempFiles[] = $dir;
        }
        return $path;
    }
}
