<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TaskRegistry class.
 *
 * Verifies that the TaskRegistry correctly:
 * - Loads task definitions from YAML files
 * - Validates task structure and metadata
 * - Provides access to task information
 * - Enforces path restrictions based on safety flags
 */
class TaskRegistryTest extends TestCase
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

    public function testConstructorLoadsDefaultRegistry(): void
    {
        $registry = new TaskRegistry();
        
        $tasks = $registry->getAllTasks();
        $this->assertIsArray($tasks);
        $this->assertNotEmpty($tasks);
    }

    public function testConstructorThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Task registry file not found');
        
        new TaskRegistry('/nonexistent/path/registry.yaml');
    }

    public function testGetTaskReturnsTaskDefinition(): void
    {
        $registry = new TaskRegistry();
        
        $task = $registry->getTask('create-driver');
        
        $this->assertIsArray($task);
        $this->assertArrayHasKey('id', $task);
        $this->assertEquals('create-driver', $task['id']);
        $this->assertArrayHasKey('name', $task);
        $this->assertArrayHasKey('description', $task);
        $this->assertArrayHasKey('category', $task);
        $this->assertArrayHasKey('scopes', $task);
    }

    public function testGetTaskThrowsExceptionForNonExistentTask(): void
    {
        $registry = new TaskRegistry();
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Task 'non-existent-task' not found");
        
        $registry->getTask('non-existent-task');
    }

    public function testHasTaskReturnsTrueForExistingTask(): void
    {
        $registry = new TaskRegistry();
        
        $this->assertTrue($registry->hasTask('create-driver'));
        $this->assertTrue($registry->hasTask('test-driver'));
        $this->assertTrue($registry->hasTask('update-readme'));
    }

    public function testHasTaskReturnsFalseForNonExistentTask(): void
    {
        $registry = new TaskRegistry();
        
        $this->assertFalse($registry->hasTask('non-existent-task'));
    }

    public function testGetAllTasksReturnsAllTasks(): void
    {
        $registry = new TaskRegistry();
        
        $tasks = $registry->getAllTasks();
        
        $this->assertIsArray($tasks);
        $this->assertGreaterThanOrEqual(4, count($tasks));
        $this->assertArrayHasKey('create-driver', $tasks);
        $this->assertArrayHasKey('test-driver', $tasks);
        $this->assertArrayHasKey('update-readme', $tasks);
        $this->assertArrayHasKey('run-static-analysis', $tasks);
    }

    public function testGetTaskIdsReturnsArrayOfIds(): void
    {
        $registry = new TaskRegistry();
        
        $taskIds = $registry->getTaskIds();
        
        $this->assertIsArray($taskIds);
        $this->assertContains('create-driver', $taskIds);
        $this->assertContains('test-driver', $taskIds);
        $this->assertContains('update-readme', $taskIds);
        $this->assertContains('run-static-analysis', $taskIds);
    }

    public function testGetTasksByCategoryReturnsFilteredTasks(): void
    {
        $registry = new TaskRegistry();
        
        $generationTasks = $registry->getTasksByCategory('generation');
        $testingTasks = $registry->getTasksByCategory('testing');
        $docTasks = $registry->getTasksByCategory('documentation');
        
        $this->assertIsArray($generationTasks);
        $this->assertArrayHasKey('create-driver', $generationTasks);
        
        $this->assertIsArray($testingTasks);
        $this->assertArrayHasKey('test-driver', $testingTasks);
        
        $this->assertIsArray($docTasks);
        $this->assertArrayHasKey('update-readme', $docTasks);
    }

    public function testGetTasksRequiringApprovalReturnsFiltered(): void
    {
        $registry = new TaskRegistry();
        
        $requiresApproval = $registry->getTasksRequiringApproval();
        
        $this->assertIsArray($requiresApproval);
        $this->assertArrayHasKey('create-driver', $requiresApproval);
        $this->assertArrayHasKey('update-readme', $requiresApproval);
    }

    public function testGetTasksByScopeReturnsFilteredTasks(): void
    {
        $registry = new TaskRegistry();
        
        $writeScopes = $registry->getTasksByScope('filesystem:write');
        
        $this->assertIsArray($writeScopes);
        $this->assertArrayHasKey('create-driver', $writeScopes);
        $this->assertArrayHasKey('update-readme', $writeScopes);
    }

    public function testGetMetadataReturnsMetadata(): void
    {
        $registry = new TaskRegistry();
        
        $metadata = $registry->getMetadata();
        
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('audit_log_path', $metadata);
    }

    public function testGetAuditLogPathReturnsPath(): void
    {
        $registry = new TaskRegistry();
        
        $path = $registry->getAuditLogPath();
        
        $this->assertIsString($path);
        $this->assertStringContainsString('storage/agent-audit.log', $path);
    }

    public function testGetDefaultSafetyFlagsReturnsFlags(): void
    {
        $registry = new TaskRegistry();
        
        $flags = $registry->getDefaultSafetyFlags();
        
        $this->assertIsArray($flags);
        $this->assertArrayHasKey('requires_approval', $flags);
        $this->assertArrayHasKey('deny_patterns', $flags);
    }

    public function testValidateTaskInputsSucceedsWithValidInputs(): void
    {
        $registry = new TaskRegistry();
        
        $inputs = [
            'driver_name' => 'ethereum',
            'rpc_url' => 'https://mainnet.infura.io',
        ];
        
        $result = $registry->validateTaskInputs('create-driver', $inputs);
        
        $this->assertTrue($result);
    }

    public function testValidateTaskInputsThrowsExceptionForMissingRequired(): void
    {
        $registry = new TaskRegistry();
        
        $inputs = []; // Missing required 'driver_name'
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('input validation failed');
        
        $registry->validateTaskInputs('create-driver', $inputs);
    }

    public function testValidateTaskInputsThrowsExceptionForInvalidType(): void
    {
        $registry = new TaskRegistry();
        
        $inputs = [
            'driver_name' => 123, // Should be string
        ];
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be of type string');
        
        $registry->validateTaskInputs('create-driver', $inputs);
    }

    public function testValidateTaskInputsThrowsExceptionForInvalidPattern(): void
    {
        $registry = new TaskRegistry();
        
        $inputs = [
            'driver_name' => 'Invalid-Name!', // Doesn't match pattern
        ];
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not match validation pattern');
        
        $registry->validateTaskInputs('create-driver', $inputs);
    }

    public function testIsPathAllowedReturnsTrueForAllowedPath(): void
    {
        $registry = new TaskRegistry();
        
        $this->assertTrue($registry->isPathAllowed('create-driver', 'src/Drivers/EthereumDriver.php'));
        $this->assertTrue($registry->isPathAllowed('create-driver', 'tests/Drivers/EthereumDriverTest.php'));
    }

    public function testIsPathAllowedReturnsFalseForDeniedPattern(): void
    {
        $registry = new TaskRegistry();
        
        $this->assertFalse($registry->isPathAllowed('create-driver', '.env'));
        $this->assertFalse($registry->isPathAllowed('create-driver', 'config/.env.local'));
        $this->assertFalse($registry->isPathAllowed('create-driver', 'private.key'));
    }

    public function testIsPathAllowedReturnsFalseForUnallowedPath(): void
    {
        $registry = new TaskRegistry();
        
        $this->assertFalse($registry->isPathAllowed('create-driver', 'vendor/autoload.php'));
        $this->assertFalse($registry->isPathAllowed('create-driver', 'config/database.php'));
    }

    public function testLoadRegistryWithInvalidYamlThrowsException(): void
    {
        $invalidYaml = "invalid: yaml: content:\n  - this is\n  broken";
        $file = $this->createTempFile('invalid.yaml', $invalidYaml);
        
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Failed to parse task registry YAML');
        
        new TaskRegistry($file);
    }

    public function testLoadRegistryWithoutTasksSectionThrowsException(): void
    {
        $yamlContent = "metadata:\n  version: 1.0.0\n";
        $file = $this->createTempFile('notasks.yaml', $yamlContent);
        
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("must contain a 'tasks' section");
        
        new TaskRegistry($file);
    }

    public function testLoadRegistryValidatesTaskStructure(): void
    {
        $yamlContent = <<<YAML
tasks:
  invalid-task:
    name: "Invalid Task"
    # Missing required fields: id, description, category, scopes
YAML;
        $file = $this->createTempFile('invalid-structure.yaml', $yamlContent);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Task registry validation failed');
        
        new TaskRegistry($file);
    }

    public function testLoadRegistryValidatesTaskIdMatchesKey(): void
    {
        $yamlContent = <<<YAML
tasks:
  task-key:
    id: "different-id"
    name: "Task Name"
    description: "Task description"
    category: "testing"
    scopes:
      - "filesystem:read"
YAML;
        $file = $this->createTempFile('mismatched-id.yaml', $yamlContent);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("does not match key");
        
        new TaskRegistry($file);
    }

    public function testValidateTaskInputsWithNoInputsReturnsTrue(): void
    {
        $registry = new TaskRegistry();
        
        // test-driver has inputs but they're not all required
        $result = $registry->validateTaskInputs('test-driver', ['driver_name' => 'solana']);
        
        $this->assertTrue($result);
    }

    public function testGetTasksByCategoryReturnsEmptyForNonExistentCategory(): void
    {
        $registry = new TaskRegistry();
        
        $tasks = $registry->getTasksByCategory('non-existent-category');
        
        $this->assertIsArray($tasks);
        $this->assertEmpty($tasks);
    }

    public function testGetTasksByScopeReturnsEmptyForNonExistentScope(): void
    {
        $registry = new TaskRegistry();
        
        $tasks = $registry->getTasksByScope('non-existent:scope');
        
        $this->assertIsArray($tasks);
        $this->assertEmpty($tasks);
    }

    /**
     * Create a temporary file for testing.
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
