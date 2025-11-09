<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\Tasks\RefactorSuggestionsTask;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test suite for RefactorSuggestionsTask.
 *
 * @package Blockchain\Tests\Agent
 */
class RefactorSuggestionsTaskTest extends TestCase
{
    private RefactorSuggestionsTask $task;
    private TaskRegistry $registry;
    private OperatorConsole $console;
    private string $projectRoot;
    private string $fixturesPath;
    private string $testReportDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test project root
        $this->projectRoot = dirname(__DIR__, 2);
        $this->fixturesPath = $this->projectRoot . '/tests/fixtures/agent/refactor';
        $this->testReportDir = sys_get_temp_dir() . '/test-refactor-reports-' . uniqid();

        // Create test fixtures directory if it doesn't exist
        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }

        // Create test report directory
        if (!is_dir($this->testReportDir)) {
            mkdir($this->testReportDir, 0755, true);
        }

        // Create mocked console that auto-approves
        $auditLogPath = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
        $this->console = new OperatorConsole($auditLogPath, false);
        $this->console->setSimulatedApproval(true);

        // Create real registry
        $this->registry = new TaskRegistry();

        // Create task instance with test project root
        $this->task = new RefactorSuggestionsTask(
            $this->registry,
            $this->console,
            $this->projectRoot
        );
    }

    protected function tearDown(): void
    {
        // Clean up test report directory
        if (is_dir($this->testReportDir)) {
            $this->recursiveRemoveDirectory($this->testReportDir);
        }

        // Clean up test fixtures
        if (is_dir($this->fixturesPath)) {
            $fixtureFile = $this->fixturesPath . '/HighComplexityClass.php';
            if (file_exists($fixtureFile)) {
                unlink($fixtureFile);
            }
        }

        Mockery::close();
        parent::tearDown();
    }

    public function testValidateTaskDefinitionExists(): void
    {
        // Verify the task is registered
        $task = $this->registry->getTask('refactor-suggestions');

        $this->assertIsArray($task);
        $this->assertEquals('refactor-suggestions', $task['id']);
        $this->assertEquals('analysis', $task['category']);
    }

    public function testExecuteWithDefaultInputs(): void
    {
        $inputs = [
            'scan_paths' => ['src/'],  // Use real directory
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('report_path', $result);
        $this->assertArrayHasKey('audit_log_entry', $result);
        $this->assertIsArray($result['suggestions']);
        $this->assertIsArray($result['summary']);
    }

    public function testExecuteComplexityAnalysisOnly(): void
    {
        $inputs = [
            'analysis_type' => 'complexity',
            'complexity_threshold' => 5,
            'scan_paths' => ['src/'],
            'output_format' => 'json',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('complexity', $result['analysis_type']);
        $this->assertEquals(5, $result['complexity_threshold']);
        $this->assertIsArray($result['suggestions']);
    }

    public function testExecuteUnusedCodeDetectionOnly(): void
    {
        $inputs = [
            'analysis_type' => 'unused',
            'scan_paths' => ['src/'],
            'risk_threshold' => 'low',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('unused', $result['analysis_type']);
        $this->assertIsArray($result['suggestions']);
    }

    public function testExecuteWithHighRiskThreshold(): void
    {
        $inputs = [
            'analysis_type' => 'full',
            'complexity_threshold' => 20,
            'risk_threshold' => 'high',
            'scan_paths' => ['src/'],
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('high', $result['risk_threshold']);
        
        // All suggestions should be high risk
        foreach ($result['suggestions'] as $suggestion) {
            $this->assertEquals('high', $suggestion['risk']);
        }
    }

    public function testExecuteWithPatchGeneration(): void
    {
        $inputs = [
            'analysis_type' => 'complexity',
            'complexity_threshold' => 5,
            'scan_paths' => ['src/'],
            'generate_patches' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        
        // Check if suggestions have patches
        if (!empty($result['suggestions'])) {
            foreach ($result['suggestions'] as $suggestion) {
                $this->assertTrue($suggestion['has_patch'] ?? false);
            }
        }
    }

    public function testExecuteWithoutPatchGeneration(): void
    {
        $inputs = [
            'analysis_type' => 'unused',
            'scan_paths' => ['src/'],
            'generate_patches' => false,
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        
        // Suggestions should not have patches
        foreach ($result['suggestions'] as $suggestion) {
            $this->assertFalse($suggestion['has_patch'] ?? false);
        }
    }

    public function testSummaryStructure(): void
    {
        $inputs = [
            'scan_paths' => ['src/'],
        ];

        $result = $this->task->execute($inputs);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_suggestions', $summary);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertArrayHasKey('by_risk', $summary);
        
        $this->assertIsInt($summary['total_suggestions']);
        $this->assertIsArray($summary['by_type']);
        $this->assertIsArray($summary['by_risk']);
        
        $this->assertArrayHasKey('complexity', $summary['by_type']);
        $this->assertArrayHasKey('unused_code', $summary['by_type']);
        
        $this->assertArrayHasKey('low', $summary['by_risk']);
        $this->assertArrayHasKey('medium', $summary['by_risk']);
        $this->assertArrayHasKey('high', $summary['by_risk']);
    }

    public function testReportGeneration(): void
    {
        $inputs = [
            'scan_paths' => ['src/'],
            'output_format' => 'both',
        ];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('report_path', $result);
        $reportPath = $result['report_path'];
        
        // Check if report file exists
        $this->assertFileExists($reportPath);
        
        // Check if report has content
        $content = file_get_contents($reportPath);
        $this->assertNotEmpty($content);
        
        // For markdown format, check for expected sections
        if (str_ends_with($reportPath, '.md')) {
            $this->assertStringContainsString('# Refactoring Suggestions Report', $content);
            $this->assertStringContainsString('## Executive Summary', $content);
        }
    }

    public function testInvalidAnalysisTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        
        $inputs = [
            'analysis_type' => 'invalid',
            'scan_paths' => ['src/'],
        ];

        $this->task->execute($inputs);
    }

    public function testInvalidRiskThresholdThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        
        $inputs = [
            'risk_threshold' => 'invalid',
            'scan_paths' => ['src/'],
        ];

        $this->task->execute($inputs);
    }

    public function testInvalidOutputFormatThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        
        $inputs = [
            'output_format' => 'invalid',
            'scan_paths' => ['src/'],
        ];

        $this->task->execute($inputs);
    }

    public function testRespectsAllowedPaths(): void
    {
        // Create a fixture file with high complexity
        $this->createHighComplexityFixture();
        
        $inputs = [
            'analysis_type' => 'complexity',
            'complexity_threshold' => 5,
            'scan_paths' => ['tests/fixtures/agent/refactor/'],
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        
        // Should find the high complexity method in fixture
        if (!empty($result['suggestions'])) {
            $foundFixture = false;
            foreach ($result['suggestions'] as $suggestion) {
                if (str_contains($suggestion['file_path'], 'fixtures/agent/refactor/')) {
                    $foundFixture = true;
                    break;
                }
            }
            $this->assertTrue($foundFixture, 'Should find suggestions in fixture directory');
        }
    }

    public function testComplexityThresholdFiltering(): void
    {
        $inputs = [
            'analysis_type' => 'complexity',
            'complexity_threshold' => 50,  // Very high threshold
            'scan_paths' => ['src/'],
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        
        // With such high threshold, should have very few or no suggestions
        $complexitySuggestions = array_filter(
            $result['suggestions'],
            fn($s) => $s['type'] === 'complexity'
        );
        
        // Each suggestion should have complexity >= 50
        foreach ($complexitySuggestions as $suggestion) {
            $this->assertGreaterThanOrEqual(50, $suggestion['current_metric'] ?? 0);
        }
    }

    public function testAuditLogEntry(): void
    {
        $inputs = [
            'scan_paths' => ['src/'],
        ];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('audit_log_entry', $result);
        $this->assertNotEmpty($result['audit_log_entry']);
        
        // Audit log entry should be a timestamp
        $timestamp = $result['audit_log_entry'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }

    /**
     * Create a fixture file with high complexity for testing.
     */
    private function createHighComplexityFixture(): void
    {
        $fixtureContent = <<<'PHP'
<?php

namespace Blockchain\Tests\Fixtures;

class HighComplexityClass
{
    public function highComplexityMethod($input)
    {
        if ($input > 10) {
            if ($input > 20) {
                if ($input > 30) {
                    return "very high";
                } elseif ($input > 25) {
                    return "high";
                } else {
                    return "medium-high";
                }
            } elseif ($input > 15) {
                return "medium";
            } else {
                return "low-medium";
            }
        } else {
            if ($input > 5) {
                return "low";
            } elseif ($input > 0) {
                return "very low";
            } else {
                return "zero or negative";
            }
        }
    }
}
PHP;

        $fixturePath = $this->fixturesPath . '/HighComplexityClass.php';
        $result = file_put_contents($fixturePath, $fixtureContent);
        if ($result === false) {
            $this->fail("Failed to create test fixture file: {$fixturePath}");
        }
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * @param string $directory Directory path
     */
    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($directory);
    }
}
