<?php

declare(strict_types=1);

namespace Blockchain\Agent\Tasks;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;

/**
 * TestDriverTask orchestrates driver test execution.
 *
 * This task:
 * - Runs PHPUnit tests for specified blockchain drivers
 * - Supports unit tests, integration tests, or both
 * - Generates code coverage reports when requested
 * - Provides clear pass/fail status with detailed error reporting
 * - Parses test output to extract meaningful metrics
 *
 * @package Blockchain\Agent\Tasks
 */
class TestDriverTask
{
    /**
     * Task registry instance.
     */
    private TaskRegistry $registry;

    /**
     * Operator console instance.
     */
    private OperatorConsole $console;

    /**
     * Project root directory.
     */
    private string $projectRoot;

    /**
     * PHPUnit binary path.
     */
    private string $phpunitBin;

    /**
     * Create a new TestDriverTask instance.
     *
     * @param TaskRegistry|null $registry Optional registry instance
     * @param OperatorConsole|null $console Optional console instance
     * @param string|null $projectRoot Optional project root path
     */
    public function __construct(
        ?TaskRegistry $registry = null,
        ?OperatorConsole $console = null,
        ?string $projectRoot = null
    ) {
        $this->registry = $registry ?? new TaskRegistry();
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->phpunitBin = $this->projectRoot . '/vendor/bin/phpunit';
        
        // Initialize console with audit log path from registry
        $auditLogPath = $this->registry->getAuditLogPath();
        $this->console = $console ?? new OperatorConsole($auditLogPath);
    }

    /**
     * Execute the test driver task.
     *
     * @param array<string, mixed> $inputs Task inputs
     * @return array<string, mixed> Task results
     * @throws ValidationException If validation fails
     */
    public function execute(array $inputs): array
    {
        // Get task definition
        $task = $this->registry->getTask('test-driver');

        // Validate inputs
        $this->registry->validateTaskInputs('test-driver', $inputs);

        // Extract inputs
        $driverName = $inputs['driver_name'];
        $testType = $inputs['test_type'] ?? 'all';
        $coverage = $inputs['coverage'] ?? false;
        $stopOnFailure = $inputs['stop_on_failure'] ?? false;
        $filter = $inputs['filter'] ?? null;
        $verbose = $inputs['verbose'] ?? false;

        // Verify PHPUnit is available
        $this->verifyPhpUnitAvailable();

        // Verify driver test files exist
        $testFiles = $this->locateTestFiles($driverName, $testType);

        if (empty($testFiles)) {
            throw new ValidationException(
                "No test files found for driver '{$driverName}'. " .
                "Expected test file at tests/Drivers/{$driverName}DriverTest.php"
            );
        }

        // Display test plan
        $this->displayTestPlan($driverName, $testType, $testFiles);

        // Build PHPUnit command
        $command = $this->buildPhpUnitCommand(
            $testFiles,
            $coverage,
            $stopOnFailure,
            $filter,
            $verbose
        );

        // Execute tests
        $this->reportProgress('Running tests...');
        $output = $this->executeTests($command);

        // Parse results
        $testResults = $this->parseTestResults($output);
        $coverageResults = $coverage ? $this->parseCoverageResults($output) : null;

        // Extract failure details if any
        $failureDetails = [];
        if (!$testResults['passed']) {
            $failureDetails = $this->extractFailureDetails($output);
        }

        // Build result
        $result = [
            'success' => true,
            'test_results' => $testResults,
            'failure_details' => $failureDetails,
            'summary' => $this->generateSummary($testResults, $failureDetails),
        ];

        if ($coverageResults !== null) {
            $result['coverage'] = $coverageResults;
        }

        // Display results
        $this->displayResults($result);

        return $result;
    }

    /**
     * Verify PHPUnit is available.
     *
     * @throws ValidationException If PHPUnit is not found
     */
    private function verifyPhpUnitAvailable(): void
    {
        // Check for PHPUnit binary
        if (!file_exists($this->phpunitBin)) {
            // Try to find phpunit in vendor
            $phpunitAlt = $this->projectRoot . '/vendor/phpunit/phpunit/phpunit';
            
            if (file_exists($phpunitAlt)) {
                $this->phpunitBin = $phpunitAlt;
                return;
            }

            throw new ValidationException(
                "PHPUnit not found at {$this->phpunitBin}. " .
                "Please run 'composer install' to install dependencies."
            );
        }
    }

    /**
     * Locate test files for a driver.
     *
     * @param string $driverName Driver name
     * @param string $testType Type of tests ('unit', 'integration', 'all')
     * @return array<string> List of test file paths
     */
    private function locateTestFiles(string $driverName, string $testType): array
    {
        $testFiles = [];

        if ($testType === 'unit' || $testType === 'all') {
            $unitTestPath = $this->projectRoot . "/tests/Drivers/{$driverName}DriverTest.php";
            if (file_exists($unitTestPath)) {
                $testFiles[] = $unitTestPath;
            }
        }

        if ($testType === 'integration' || $testType === 'all') {
            $integrationTestPath = $this->projectRoot . "/tests/Integration/{$driverName}IntegrationTest.php";
            if (file_exists($integrationTestPath)) {
                $testFiles[] = $integrationTestPath;
            }
        }

        return $testFiles;
    }

    /**
     * Display test execution plan.
     *
     * @param string $driverName Driver name
     * @param string $testType Test type
     * @param array<string> $testFiles Test files to execute
     */
    private function displayTestPlan(string $driverName, string $testType, array $testFiles): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST EXECUTION PLAN\n";
        echo str_repeat("=", 80) . "\n\n";
        echo "Driver: {$driverName}\n";
        echo "Test Type: {$testType}\n";
        echo "Test Files:\n";
        foreach ($testFiles as $file) {
            $relativePath = str_replace($this->projectRoot . '/', '', $file);
            echo "  - {$relativePath}\n";
        }
        echo "\n" . str_repeat("=", 80) . "\n\n";
    }

    /**
     * Build PHPUnit command.
     *
     * @param array<string> $testFiles Test files
     * @param bool $coverage Generate coverage
     * @param bool $stopOnFailure Stop on first failure
     * @param string|null $filter Test filter pattern
     * @param bool $verbose Verbose output
     * @return string PHPUnit command
     */
    private function buildPhpUnitCommand(
        array $testFiles,
        bool $coverage,
        bool $stopOnFailure,
        ?string $filter,
        bool $verbose
    ): string {
        $command = 'php ' . escapeshellarg($this->phpunitBin);

        // Add test files
        foreach ($testFiles as $file) {
            $command .= ' ' . escapeshellarg($file);
        }

        // Add options
        if ($coverage) {
            $command .= ' --coverage-text';
        }

        if ($stopOnFailure) {
            $command .= ' --stop-on-failure';
        }

        if ($filter !== null) {
            $command .= ' --filter ' . escapeshellarg($filter);
        }

        if ($verbose) {
            $command .= ' --verbose';
        }

        // Always add testdox for better output
        $command .= ' --testdox';

        // Redirect stderr to stdout
        $command .= ' 2>&1';

        return $command;
    }

    /**
     * Execute PHPUnit tests.
     *
     * @param string $command PHPUnit command
     * @return string Test output
     */
    private function executeTests(string $command): string
    {
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        return implode("\n", $output);
    }

    /**
     * Parse test results from PHPUnit output.
     *
     * @param string $output PHPUnit output
     * @return array<string, mixed> Parsed test results
     */
    private function parseTestResults(string $output): array
    {
        $results = [
            'passed' => false,
            'tests_run' => 0,
            'assertions' => 0,
            'failures' => 0,
            'errors' => 0,
            'skipped' => 0,
            'time' => 0.0,
            'raw_output' => $output,
        ];

        // Parse test counts
        if (preg_match('/Tests:\s+(\d+)/', $output, $matches)) {
            $results['tests_run'] = (int)$matches[1];
        }

        if (preg_match('/Assertions:\s+(\d+)/', $output, $matches)) {
            $results['assertions'] = (int)$matches[1];
        }

        if (preg_match('/Failures:\s+(\d+)/', $output, $matches)) {
            $results['failures'] = (int)$matches[1];
        }

        if (preg_match('/Errors:\s+(\d+)/', $output, $matches)) {
            $results['errors'] = (int)$matches[1];
        }

        if (preg_match('/Skipped:\s+(\d+)/', $output, $matches)) {
            $results['skipped'] = (int)$matches[1];
        }

        if (preg_match('/Time:\s+([\d.]+)/', $output, $matches)) {
            $results['time'] = (float)$matches[1];
        }

        // Determine if tests passed
        $results['passed'] = $results['failures'] === 0 && $results['errors'] === 0;

        // Check for "OK" indicator
        if (str_contains($output, 'OK (')) {
            $results['passed'] = true;
        }

        return $results;
    }

    /**
     * Parse coverage results from PHPUnit output.
     *
     * @param string $output PHPUnit output
     * @return array<string, mixed>|null Parsed coverage results or null
     */
    private function parseCoverageResults(string $output): ?array
    {
        // Look for coverage summary
        if (preg_match('/Lines:\s+([\d.]+)%\s+\((\d+)\/(\d+)\)/', $output, $matches)) {
            return [
                'percentage' => (float)$matches[1],
                'lines_covered' => (int)$matches[2],
                'lines_total' => (int)$matches[3],
            ];
        }

        return null;
    }

    /**
     * Extract failure details from test output.
     *
     * @param string $output Test output
     * @return array<array<string, mixed>> List of failure details
     */
    private function extractFailureDetails(string $output): array
    {
        $failures = [];
        
        // Split output by failure markers
        $lines = explode("\n", $output);
        $currentFailure = null;
        
        foreach ($lines as $line) {
            // Look for failure start
            if (preg_match('/^\d+\)\s+(.+)$/', $line, $matches)) {
                if ($currentFailure !== null) {
                    $failures[] = $currentFailure;
                }
                $currentFailure = [
                    'test' => trim($matches[1]),
                    'message' => '',
                    'trace' => [],
                ];
            } elseif ($currentFailure !== null) {
                // Add to current failure
                if (empty($currentFailure['message']) && !empty(trim($line))) {
                    $currentFailure['message'] = trim($line);
                } elseif (str_starts_with($line, '   ') || str_starts_with($line, '/')) {
                    $currentFailure['trace'][] = trim($line);
                }
            }
        }
        
        if ($currentFailure !== null) {
            $failures[] = $currentFailure;
        }

        return $failures;
    }

    /**
     * Generate test summary.
     *
     * @param array<string, mixed> $testResults Test results
     * @param array<array<string, mixed>> $failures Failure details
     * @return string Summary text
     */
    private function generateSummary(array $testResults, array $failures): string
    {
        $summary = "Test Execution Summary:\n";
        
        if ($testResults['passed']) {
            $summary .= "✅ All tests passed!\n";
        } else {
            $summary .= "❌ Tests failed!\n";
        }
        
        $summary .= "\n";
        $summary .= "Tests Run: {$testResults['tests_run']}\n";
        $summary .= "Assertions: {$testResults['assertions']}\n";
        $summary .= "Failures: {$testResults['failures']}\n";
        $summary .= "Errors: {$testResults['errors']}\n";
        $summary .= "Skipped: {$testResults['skipped']}\n";
        $summary .= "Time: {$testResults['time']}s\n";

        if (!empty($failures)) {
            $summary .= "\nFailed Tests:\n";
            foreach ($failures as $i => $failure) {
                $summary .= ($i + 1) . ". {$failure['test']}\n";
                if (!empty($failure['message'])) {
                    $summary .= "   Message: {$failure['message']}\n";
                }
            }
        }

        return $summary;
    }

    /**
     * Display test results.
     *
     * @param array<string, mixed> $result Task results
     */
    private function displayResults(array $result): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST RESULTS\n";
        echo str_repeat("=", 80) . "\n\n";

        echo $result['summary'] . "\n";

        if (isset($result['coverage'])) {
            echo "\n📊 Code Coverage:\n";
            echo "   Lines: {$result['coverage']['percentage']}% ";
            echo "({$result['coverage']['lines_covered']}/{$result['coverage']['lines_total']})\n";
        }

        if (!empty($result['failure_details'])) {
            echo "\n📝 Failure Details:\n";
            foreach ($result['failure_details'] as $i => $failure) {
                echo "\n" . ($i + 1) . ". {$failure['test']}\n";
                if (!empty($failure['message'])) {
                    echo "   {$failure['message']}\n";
                }
                if (!empty($failure['trace'])) {
                    echo "   Stack Trace:\n";
                    foreach (array_slice($failure['trace'], 0, 3) as $trace) {
                        echo "     {$trace}\n";
                    }
                }
            }
        }

        echo "\n" . str_repeat("=", 80) . "\n";
    }

    /**
     * Report progress to console.
     *
     * @param string $message Progress message
     */
    private function reportProgress(string $message): void
    {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
}
