<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\Tasks\SecurityAuditTask;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test suite for SecurityAuditTask.
 *
 * @package Blockchain\Tests\Agent
 */
class SecurityAuditTaskTest extends TestCase
{
    private SecurityAuditTask $task;
    private TaskRegistry $registry;
    private OperatorConsole $console;
    private string $projectRoot;
    private string $reportDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test project root
        $this->projectRoot = dirname(__DIR__, 2);
        $this->reportDir = sys_get_temp_dir() . '/test-security-reports-' . uniqid();

        // Create test report directory
        if (!is_dir($this->reportDir)) {
            mkdir($this->reportDir, 0700, true);
        }

        // Create mocked console that auto-approves
        $auditLogPath = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
        $this->console = new OperatorConsole($auditLogPath, false);
        $this->console->setSimulatedApproval(true);

        // Create real registry
        $this->registry = new TaskRegistry();

        // Create task instance with test project root
        $this->task = new SecurityAuditTask(
            $this->registry,
            $this->console,
            $this->projectRoot
        );
    }

    protected function tearDown(): void
    {
        // Clean up test report directory
        if (is_dir($this->reportDir)) {
            $this->recursiveRemoveDirectory($this->reportDir);
        }

        Mockery::close();
        parent::tearDown();
    }

    public function testValidateTaskDefinitionExists(): void
    {
        // Verify the task is registered
        $task = $this->registry->getTask('security-audit');

        $this->assertIsArray($task);
        $this->assertEquals('security-audit', $task['id']);
        $this->assertEquals('security', $task['category']);
    }

    public function testExecuteWithDefaultInputs(): void
    {
        $inputs = [];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('findings', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('report_path', $result);
        $this->assertArrayHasKey('audit_log_entry', $result);
        $this->assertIsArray($result['findings']);
        $this->assertIsArray($result['summary']);
    }

    public function testExecuteWithFullScanType(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'severity_threshold' => 'low',
            'fail_on_finding' => false,
            'include_recommendations' => true,
            'output_format' => 'both',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('full', $result['scan_type']);
        $this->assertEquals('low', $result['severity_threshold']);
        
        // Should have run all scan types
        $this->assertIsArray($result['findings']);
        $this->assertIsArray($result['summary']);
    }

    public function testExecuteWithStaticScanOnly(): void
    {
        $inputs = [
            'scan_type' => 'static',
            'severity_threshold' => 'medium',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('static', $result['scan_type']);
    }

    public function testExecuteWithDependencyScanOnly(): void
    {
        $inputs = [
            'scan_type' => 'dependencies',
            'severity_threshold' => 'high',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('dependencies', $result['scan_type']);
    }

    public function testExecuteWithConfigScanOnly(): void
    {
        $inputs = [
            'scan_type' => 'config',
            'severity_threshold' => 'critical',
        ];

        $result = $this->task->execute($inputs);

        $this->assertTrue($result['success']);
        $this->assertEquals('config', $result['scan_type']);
    }

    public function testSummaryStructure(): void
    {
        $inputs = ['scan_type' => 'full'];

        $result = $this->task->execute($inputs);
        $summary = $result['summary'];

        $this->assertArrayHasKey('total_findings', $summary);
        $this->assertArrayHasKey('critical', $summary);
        $this->assertArrayHasKey('high', $summary);
        $this->assertArrayHasKey('medium', $summary);
        $this->assertArrayHasKey('low', $summary);
        $this->assertArrayHasKey('passed', $summary);

        $this->assertIsInt($summary['total_findings']);
        $this->assertIsInt($summary['critical']);
        $this->assertIsInt($summary['high']);
        $this->assertIsInt($summary['medium']);
        $this->assertIsInt($summary['low']);
        $this->assertIsBool($summary['passed']);
    }

    public function testReportGeneration(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'output_format' => 'both',
        ];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('report_path', $result);
        $this->assertNotEmpty($result['report_path']);
        
        // Verify report file exists (if we can write to storage)
        if (is_writable($this->projectRoot . '/storage')) {
            $this->assertFileExists($result['report_path']);
        }
    }

    public function testMarkdownReportFormat(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'output_format' => 'markdown',
        ];

        $result = $this->task->execute($inputs);

        $this->assertStringContainsString('.md', $result['report_path']);
    }

    public function testJsonReportFormat(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'output_format' => 'json',
        ];

        $result = $this->task->execute($inputs);

        $this->assertStringContainsString('.json', $result['report_path']);
    }

    public function testSeverityThresholdFiltering(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'severity_threshold' => 'critical',
        ];

        $result = $this->task->execute($inputs);

        // With critical threshold, only critical findings should be included
        foreach ($result['findings'] as $finding) {
            $this->assertEquals('critical', $finding['severity']);
        }
    }

    public function testFailOnFinding(): void
    {
        $this->expectException(ValidationException::class);

        // Create a test file with a security issue
        $testFile = $this->projectRoot . '/src/TestSecurityIssue.php';
        $testContent = "<?php\neval('echo \"test\";');\n";
        
        file_put_contents($testFile, $testContent);

        try {
            $inputs = [
                'scan_type' => 'static',
                'fail_on_finding' => true,
                'severity_threshold' => 'low',
            ];

            $this->task->execute($inputs);
        } finally {
            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testRecommendationsGeneration(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'include_recommendations' => true,
        ];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertIsArray($result['recommendations']);
    }

    public function testNoRecommendationsWhenDisabled(): void
    {
        $inputs = [
            'scan_type' => 'full',
            'include_recommendations' => false,
        ];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertEmpty($result['recommendations']);
    }

    public function testInvalidScanType(): void
    {
        $this->expectException(ValidationException::class);

        $inputs = [
            'scan_type' => 'invalid',
        ];

        $this->task->execute($inputs);
    }

    public function testInvalidSeverityThreshold(): void
    {
        $this->expectException(ValidationException::class);

        $inputs = [
            'severity_threshold' => 'invalid',
        ];

        $this->task->execute($inputs);
    }

    public function testInvalidOutputFormat(): void
    {
        $this->expectException(ValidationException::class);

        $inputs = [
            'output_format' => 'invalid',
        ];

        $this->task->execute($inputs);
    }

    public function testAuditLogEntry(): void
    {
        $inputs = ['scan_type' => 'full'];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('audit_log_entry', $result);
        $this->assertNotEmpty($result['audit_log_entry']);
    }

    public function testDurationTracking(): void
    {
        $inputs = ['scan_type' => 'full'];

        $result = $this->task->execute($inputs);

        $this->assertArrayHasKey('duration', $result);
        $this->assertIsFloat($result['duration']);
        $this->assertGreaterThan(0, $result['duration']);
    }

    public function testFindingStructure(): void
    {
        // Create a test file with a known security issue
        $testFile = $this->projectRoot . '/src/TestSecurityPattern.php';
        $testContent = "<?php\n// Test file\neval('test');\n";
        
        file_put_contents($testFile, $testContent);

        try {
            $inputs = [
                'scan_type' => 'static',
                'severity_threshold' => 'low',
            ];

            $result = $this->task->execute($inputs);

            // Check if we found any findings
            if (!empty($result['findings'])) {
                $finding = $result['findings'][0];
                
                // Verify finding structure
                $this->assertArrayHasKey('type', $finding);
                $this->assertArrayHasKey('tool', $finding);
                $this->assertArrayHasKey('severity', $finding);
                $this->assertArrayHasKey('message', $finding);
            }
        } finally {
            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testSensitiveDataRedaction(): void
    {
        // Create a test config file with sensitive data
        $testConfigDir = $this->projectRoot . '/config';
        if (!is_dir($testConfigDir)) {
            mkdir($testConfigDir, 0755, true);
        }

        $testFile = $testConfigDir . '/test-sensitive.php';
        $testContent = "<?php\nreturn ['api_key' => 'test_key_1234567890abcdefghijklmnopqrst'];\n";
        
        file_put_contents($testFile, $testContent);

        try {
            $inputs = [
                'scan_type' => 'config',
                'severity_threshold' => 'low',
            ];

            $result = $this->task->execute($inputs);

            // Check findings for redacted content
            foreach ($result['findings'] as $finding) {
                foreach ($finding as $value) {
                    if (is_string($value)) {
                        // Ensure no actual API key is present (using prefix from test)
                        $this->assertStringNotContainsString('test_key_1234567890', $value);
                    }
                }
            }
        } finally {
            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    public function testSecurityPatternDetection(): void
    {
        // Create a test file with various security patterns
        $testFile = $this->projectRoot . '/src/TestPatterns.php';
        $testContent = "<?php\n";
        $testContent .= "// Test dangerous functions\n";
        $testContent .= "eval('test');\n";
        $testContent .= "exec('ls');\n";
        $testContent .= "system('whoami');\n";
        
        file_put_contents($testFile, $testContent);

        try {
            $inputs = [
                'scan_type' => 'static',
                'severity_threshold' => 'low',
            ];

            $result = $this->task->execute($inputs);

            // Should find at least some security patterns
            $patternFindings = array_filter(
                $result['findings'],
                fn($f) => ($f['type'] ?? '') === 'security_pattern'
            );

            $this->assertNotEmpty($patternFindings, 'Should detect security patterns');
        } finally {
            // Clean up test file
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $dir Directory path
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
