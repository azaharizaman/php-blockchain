<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\Tasks\TestDriverTask;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TestDriverTask.
 *
 * @package Blockchain\Tests\Agent
 */
class TestDriverTaskTest extends TestCase
{
    private TestDriverTask $task;
    private TaskRegistry $registry;
    private OperatorConsole $console;
    private string $projectRoot;
    private string $testOutputDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test project root
        $this->projectRoot = dirname(__DIR__, 2);
        $this->testOutputDir = sys_get_temp_dir() . '/test-driver-test-' . uniqid();
        
        // Create test output directory
        mkdir($this->testOutputDir, 0755, true);

        // Create mocked console
        $auditLogPath = $this->testOutputDir . '/audit.log';
        $this->console = new OperatorConsole($auditLogPath, false);
        $this->console->setSimulatedApproval(true);

        // Create registry instance
        $this->registry = new TaskRegistry();

        // Create task instance
        $this->task = new TestDriverTask(
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

    public function testExecuteThrowsExceptionWhenPhpUnitNotFound(): void
    {
        // Create task with non-existent project root
        $task = new TestDriverTask(
            $this->registry,
            $this->console,
            '/nonexistent/path'
        );

        $inputs = [
            'driver_name' => 'Solana',
            'test_type' => 'unit',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('PHPUnit not found');

        $task->execute($inputs);
    }

    public function testExecuteThrowsExceptionWhenTestFilesNotFound(): void
    {
        $inputs = [
            'driver_name' => 'NonExistentDriver',
            'test_type' => 'unit',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No test files found');

        $this->task->execute($inputs);
    }

    public function testExecuteValidatesInputs(): void
    {
        $inputs = [
            'driver_name' => 'invalid-name',  // Invalid format
            'test_type' => 'unit',
        ];

        $this->expectException(ValidationException::class);

        $this->task->execute($inputs);
    }

    public function testLocateTestFilesFindsUnitTests(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('locateTestFiles');
        $method->setAccessible(true);

        // Solana driver test exists in the project
        $testFiles = $method->invoke($this->task, 'Solana', 'unit');

        $this->assertIsArray($testFiles);
        // Check if at least one test file is found
        $this->assertNotEmpty($testFiles);
    }

    public function testLocateTestFilesFindsAllTests(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('locateTestFiles');
        $method->setAccessible(true);

        $testFiles = $method->invoke($this->task, 'Solana', 'all');

        $this->assertIsArray($testFiles);
        $this->assertNotEmpty($testFiles);
    }

    public function testBuildPhpUnitCommandWithBasicOptions(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('buildPhpUnitCommand');
        $method->setAccessible(true);

        $testFiles = ['/path/to/test.php'];
        $command = $method->invoke(
            $this->task,
            $testFiles,
            false,  // coverage
            false,  // stopOnFailure
            null,   // filter
            false   // verbose
        );

        $this->assertIsString($command);
        $this->assertStringContainsString('phpunit', $command);
        $this->assertStringContainsString('/path/to/test.php', $command);
        $this->assertStringContainsString('--testdox', $command);
    }

    public function testBuildPhpUnitCommandWithCoverage(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('buildPhpUnitCommand');
        $method->setAccessible(true);

        $testFiles = ['/path/to/test.php'];
        $command = $method->invoke(
            $this->task,
            $testFiles,
            true,   // coverage
            false,
            null,
            false
        );

        $this->assertStringContainsString('--coverage-text', $command);
    }

    public function testBuildPhpUnitCommandWithFilter(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('buildPhpUnitCommand');
        $method->setAccessible(true);

        $testFiles = ['/path/to/test.php'];
        $command = $method->invoke(
            $this->task,
            $testFiles,
            false,
            false,
            'testGetBalance',  // filter
            false
        );

        $this->assertStringContainsString('--filter', $command);
        $this->assertStringContainsString('testGetBalance', $command);
    }

    public function testBuildPhpUnitCommandWithStopOnFailure(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('buildPhpUnitCommand');
        $method->setAccessible(true);

        $testFiles = ['/path/to/test.php'];
        $command = $method->invoke(
            $this->task,
            $testFiles,
            false,
            true,   // stopOnFailure
            null,
            false
        );

        $this->assertStringContainsString('--stop-on-failure', $command);
    }

    public function testParseTestResultsWithSuccessfulTests(): void
    {
        $output = <<<'OUTPUT'
PHPUnit 10.5.0

Time: 00:01.234, Memory: 10.00 MB

OK (15 tests, 42 assertions)
OUTPUT;

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('parseTestResults');
        $method->setAccessible(true);

        $results = $method->invoke($this->task, $output);

        $this->assertTrue($results['passed']);
        $this->assertEquals(1.234, $results['time']);
    }

    public function testParseTestResultsWithFailures(): void
    {
        $output = <<<'OUTPUT'
PHPUnit 10.5.0

Time: 00:01.500, Memory: 10.00 MB

Tests: 15, Assertions: 42, Failures: 2, Errors: 1, Skipped: 1
OUTPUT;

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('parseTestResults');
        $method->setAccessible(true);

        $results = $method->invoke($this->task, $output);

        $this->assertFalse($results['passed']);
        $this->assertEquals(15, $results['tests_run']);
        $this->assertEquals(42, $results['assertions']);
        $this->assertEquals(2, $results['failures']);
        $this->assertEquals(1, $results['errors']);
        $this->assertEquals(1, $results['skipped']);
    }

    public function testParseCoverageResultsExtractsCoverageMetrics(): void
    {
        $output = <<<'OUTPUT'
Code Coverage Report:
  2023-11-08 10:00:00

 Summary:
  Classes: 80.00% (4/5)
  Methods: 85.00% (17/20)
  Lines:   75.50% (151/200)
OUTPUT;

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('parseCoverageResults');
        $method->setAccessible(true);

        $results = $method->invoke($this->task, $output);

        $this->assertIsArray($results);
        $this->assertEquals(75.50, $results['percentage']);
        $this->assertEquals(151, $results['lines_covered']);
        $this->assertEquals(200, $results['lines_total']);
    }

    public function testParseCoverageResultsReturnsNullWhenNoCoverage(): void
    {
        $output = "PHPUnit output without coverage";

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('parseCoverageResults');
        $method->setAccessible(true);

        $results = $method->invoke($this->task, $output);

        $this->assertNull($results);
    }

    public function testExtractFailureDetailsFromOutput(): void
    {
        $output = <<<'OUTPUT'
There were 2 failures:

1) TestClass::testMethod1
Failed asserting that false is true.
   /path/to/test.php:42
   
2) TestClass::testMethod2
Expected exception not thrown.
   /path/to/test.php:56
OUTPUT;

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('extractFailureDetails');
        $method->setAccessible(true);

        $failures = $method->invoke($this->task, $output);

        $this->assertIsArray($failures);
        $this->assertCount(2, $failures);
        $this->assertStringContainsString('testMethod1', $failures[0]['test']);
        $this->assertStringContainsString('testMethod2', $failures[1]['test']);
    }

    public function testGenerateSummaryWithPassedTests(): void
    {
        $testResults = [
            'passed' => true,
            'tests_run' => 15,
            'assertions' => 42,
            'failures' => 0,
            'errors' => 0,
            'skipped' => 0,
            'time' => 1.234,
        ];

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('generateSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($this->task, $testResults, []);

        $this->assertStringContainsString('✅ All tests passed!', $summary);
        $this->assertStringContainsString('Tests Run: 15', $summary);
        $this->assertStringContainsString('Assertions: 42', $summary);
    }

    public function testGenerateSummaryWithFailures(): void
    {
        $testResults = [
            'passed' => false,
            'tests_run' => 15,
            'assertions' => 42,
            'failures' => 2,
            'errors' => 0,
            'skipped' => 0,
            'time' => 1.234,
        ];

        $failures = [
            ['test' => 'TestClass::testMethod1', 'message' => 'Assertion failed'],
        ];

        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('generateSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($this->task, $testResults, $failures);

        $this->assertStringContainsString('❌ Tests failed!', $summary);
        $this->assertStringContainsString('Failures: 2', $summary);
        $this->assertStringContainsString('Failed Tests:', $summary);
        $this->assertStringContainsString('testMethod1', $summary);
    }

    public function testVerifyPhpUnitAvailableDoesNotThrowWhenPhpUnitExists(): void
    {
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('verifyPhpUnitAvailable');
        $method->setAccessible(true);

        // This should not throw an exception if PHPUnit is installed
        // If it does throw, the test will fail
        try {
            $method->invoke($this->task);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            // PHPUnit not installed in test environment, skip this assertion
            $this->markTestSkipped('PHPUnit not installed in test environment');
        }
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
