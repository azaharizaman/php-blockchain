<?php

declare(strict_types=1);

namespace Blockchain\Agent\Tasks;

use Blockchain\Agent\Generator\DriverScaffolder;
use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;

/**
 * CreateDriverTask orchestrates the creation of new blockchain drivers.
 *
 * This task coordinates:
 * - Specification parsing
 * - Code generation (driver, tests, docs)
 * - Validation (PHPStan, PHPUnit)
 * - Summary reporting
 *
 * @package Blockchain\Agent\Tasks
 */
class CreateDriverTask
{
    /**
     * Driver scaffolder instance.
     */
    private DriverScaffolder $scaffolder;

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
     * Create a new CreateDriverTask instance.
     *
     * @param DriverScaffolder|null $scaffolder Optional scaffolder instance
     * @param TaskRegistry|null $registry Optional registry instance
     * @param OperatorConsole|null $console Optional console instance
     * @param string|null $projectRoot Optional project root path
     */
    public function __construct(
        ?DriverScaffolder $scaffolder = null,
        ?TaskRegistry $registry = null,
        ?OperatorConsole $console = null,
        ?string $projectRoot = null
    ) {
        $this->scaffolder = $scaffolder ?? new DriverScaffolder();
        $this->registry = $registry ?? new TaskRegistry();
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        
        // Initialize console with audit log path from registry
        $auditLogPath = $this->registry->getAuditLogPath();
        $this->console = $console ?? new OperatorConsole($auditLogPath);
    }

    /**
     * Execute the create driver task.
     *
     * @param array<string, mixed> $inputs Task inputs
     * @return array<string, mixed> Task results
     * @throws ValidationException If validation fails
     */
    public function execute(array $inputs): array
    {
        // Get task definition
        $task = $this->registry->getTask('create-new-driver');

        // Validate inputs
        $this->registry->validateTaskInputs('create-new-driver', $inputs);

        // Extract inputs
        $driverName = $inputs['driver_name'];
        $specSource = $inputs['spec_source'];
        $networkType = $inputs['network_type'];
        $authToken = $inputs['auth_token'] ?? null;

        // Check if driver already exists
        $this->checkDriverExists($driverName);

        // Determine affected paths
        $affectedPaths = $this->calculateAffectedPaths($driverName);

        // Validate paths
        $this->console->validatePaths($this->registry, 'create-new-driver', $affectedPaths);

        // Request operator approval
        $approved = $this->console->requestApproval(
            'create-new-driver',
            $task,
            $inputs,
            $affectedPaths
        );

        if (!$approved) {
            throw new ValidationException('Operator denied task execution');
        }

        // Parse specification
        $this->reportProgress('Parsing RPC specification...');
        $spec = $this->scaffolder->parseSpecification($specSource, $authToken);

        // Generate driver class
        $this->reportProgress('Generating driver class...');
        $driverCode = $this->scaffolder->generateDriverClass(
            $driverName,
            $networkType,
            $spec,
            $inputs
        );
        $driverPath = $this->writeDriverClass($driverName, $driverCode);

        // Generate test class
        $this->reportProgress('Generating test class...');
        $testCode = $this->scaffolder->generateTestClass($driverName, $spec);
        $testPath = $this->writeTestClass($driverName, $testCode);

        // Generate documentation
        $this->reportProgress('Generating documentation...');
        $docContent = $this->scaffolder->generateDocumentation($driverName, $spec, $inputs);
        $docPath = $this->writeDocumentation($driverName, $docContent);

        // Collect generated files
        $filesCreated = [
            'driver' => $driverPath,
            'test' => $testPath,
            'documentation' => $docPath,
        ];

        // Run post-generation validation
        $this->reportProgress('Running validation checks...');
        $validationResults = $this->runValidation($driverPath, $testPath);

        // Build result summary
        $result = [
            'success' => true,
            'files_created' => $filesCreated,
            'driver_class' => "Blockchain\\Drivers\\{$driverName}Driver",
            'test_class' => "Blockchain\\Tests\\Drivers\\{$driverName}DriverTest",
            'validation_results' => $validationResults,
            'next_steps' => $this->generateNextSteps($driverName, $validationResults),
        ];

        // Generate and display summary report
        $this->displaySummaryReport($result);

        return $result;
    }

    /**
     * Check if driver already exists.
     *
     * @param string $driverName Driver name
     * @throws ValidationException If driver exists
     */
    private function checkDriverExists(string $driverName): void
    {
        $driverPath = $this->projectRoot . "/src/Drivers/{$driverName}Driver.php";
        
        if (file_exists($driverPath)) {
            throw new ValidationException(
                "Driver '{$driverName}Driver' already exists at {$driverPath}. " .
                "Please choose a different name or delete the existing driver."
            );
        }
    }

    /**
     * Calculate affected file paths.
     *
     * @param string $driverName Driver name
     * @return array<string> List of file paths
     */
    private function calculateAffectedPaths(string $driverName): array
    {
        return [
            "src/Drivers/{$driverName}Driver.php",
            "tests/Drivers/{$driverName}DriverTest.php",
            "docs/drivers/" . strtolower($driverName) . ".md",
        ];
    }

    /**
     * Write driver class to file.
     *
     * @param string $driverName Driver name
     * @param string $code Driver code
     * @return string Path to written file
     */
    private function writeDriverClass(string $driverName, string $code): string
    {
        $path = $this->projectRoot . "/src/Drivers/{$driverName}Driver.php";
        $this->ensureDirectoryExists(dirname($path));
        
        if (file_put_contents($path, $code) === false) {
            throw new ValidationException("Failed to write driver file to {$path}");
        }
        
        return $path;
    }

    /**
     * Write test class to file.
     *
     * @param string $driverName Driver name
     * @param string $code Test code
     * @return string Path to written file
     */
    private function writeTestClass(string $driverName, string $code): string
    {
        $path = $this->projectRoot . "/tests/Drivers/{$driverName}DriverTest.php";
        $this->ensureDirectoryExists(dirname($path));
        
        file_put_contents($path, $code);
        
        return $path;
    }

    /**
     * Write documentation to file.
     *
     * @param string $driverName Driver name
     * @param string $content Documentation content
     * @return string Path to written file
     */
    private function writeDocumentation(string $driverName, string $content): string
    {
        $path = $this->projectRoot . "/docs/drivers/" . strtolower($driverName) . ".md";
        $this->ensureDirectoryExists(dirname($path));
        
        file_put_contents($path, $content);
        
        return $path;
    }

    /**
     * Ensure directory exists.
     *
     * @param string $directory Directory path
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Run validation checks on generated code.
     *
     * @param string $driverPath Path to driver file
     * @param string $testPath Path to test file
     * @return array<string, mixed> Validation results
     */
    private function runValidation(string $driverPath, string $testPath): array
    {
        $results = [
            'phpstan' => $this->runPhpStan($driverPath),
            'phpunit' => $this->runPhpUnit($testPath),
            'syntax_check' => $this->checkPhpSyntax($driverPath),
        ];

        return $results;
    }

    /**
     * Run PHPStan analysis.
     *
     * @param string $filePath File to analyze
     * @return array<string, mixed> Analysis results
     */
    private function runPhpStan(string $filePath): array
    {
        // Check if PHPStan is available
        $phpstanBin = $this->projectRoot . '/vendor/bin/phpstan';
        
        if (!file_exists($phpstanBin)) {
            return [
                'skipped' => true,
                'reason' => 'PHPStan binary not found',
            ];
        }

        // Run PHPStan with level 7
        $command = sprintf(
            '%s analyse --level=7 --no-progress --error-format=json %s 2>&1',
            escapeshellarg($phpstanBin),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        $result = json_decode($outputStr, true);

        if ($result === null) {
            return [
                'passed' => false,
                'errors' => ['Failed to parse PHPStan output'],
                'output' => $outputStr,
            ];
        }

        $errors = [];
        if (isset($result['files'])) {
            foreach ($result['files'] as $file => $fileErrors) {
                foreach ($fileErrors['messages'] as $error) {
                    $errors[] = sprintf(
                        'Line %d: %s',
                        $error['line'] ?? 0,
                        $error['message'] ?? 'Unknown error'
                    );
                }
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'total_errors' => count($errors),
        ];
    }

    /**
     * Run PHPUnit tests.
     *
     * @param string $testPath Path to test file
     * @return array<string, mixed> Test results
     */
    private function runPhpUnit(string $testPath): array
    {
        // Check if PHPUnit is available
        $phpunitBin = $this->projectRoot . '/vendor/bin/phpunit';
        
        if (!file_exists($phpunitBin)) {
            return [
                'skipped' => true,
                'reason' => 'PHPUnit binary not found',
            ];
        }

        // Run PHPUnit
        $command = sprintf(
            '%s --no-coverage %s 2>&1',
            escapeshellarg($phpunitBin),
            escapeshellarg($testPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        $passed = $returnCode === 0;

        return [
            'passed' => $passed,
            'output' => $outputStr,
            'exit_code' => $returnCode,
        ];
    }

    /**
     * Check PHP syntax.
     *
     * @param string $filePath File to check
     * @return array<string, mixed> Syntax check results
     */
    private function checkPhpSyntax(string $filePath): array
    {
        $command = sprintf('php -l %s 2>&1', escapeshellarg($filePath));
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        $passed = $returnCode === 0 && str_contains($outputStr, 'No syntax errors');

        return [
            'passed' => $passed,
            'output' => $outputStr,
        ];
    }

    /**
     * Generate next steps for operator.
     *
     * @param string $driverName Driver name
     * @param array<string, mixed> $validationResults Validation results
     * @return array<string> List of next steps
     */
    private function generateNextSteps(string $driverName, array $validationResults): array
    {
        $steps = [];

        // Check validation results
        $phpstanPassed = $validationResults['phpstan']['passed'] ?? false;
        $phpunitPassed = $validationResults['phpunit']['passed'] ?? false;
        $syntaxPassed = $validationResults['syntax_check']['passed'] ?? false;

        if (!$syntaxPassed) {
            $steps[] = "❌ Fix PHP syntax errors in generated driver class";
        }

        if (!$phpstanPassed) {
            $steps[] = "❌ Fix PHPStan errors in generated driver class";
        }

        if (!$phpunitPassed) {
            $steps[] = "⚠️  Fix or review PHPUnit test failures";
        }

        // Always include these steps
        $steps[] = "📝 Review TODO annotations in driver and implement missing functionality";
        $steps[] = "🔐 Implement transaction signing in sendTransaction() method";
        $steps[] = "📚 Review and customize documentation in docs/drivers/" . strtolower($driverName) . ".md";
        $steps[] = "✅ Add driver to README.md supported blockchains table";
        $steps[] = "🧪 Run integration tests with live network (optional)";
        $steps[] = "📦 Register driver in BlockchainManager if needed";

        return $steps;
    }

    /**
     * Display summary report to operator.
     *
     * @param array<string, mixed> $result Task results
     */
    private function displaySummaryReport(array $result): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "DRIVER GENERATION SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        // Files created
        echo "📁 Files Created:\n";
        foreach ($result['files_created'] as $type => $path) {
            $relativePath = str_replace($this->projectRoot . '/', '', $path);
            echo "  - {$type}: {$relativePath}\n";
        }
        echo "\n";

        // Class names
        echo "📋 Generated Classes:\n";
        echo "  - Driver: {$result['driver_class']}\n";
        echo "  - Test: {$result['test_class']}\n";
        echo "\n";

        // Validation results
        echo "✅ Validation Results:\n";
        
        $syntaxPassed = $result['validation_results']['syntax_check']['passed'] ?? false;
        echo "  - PHP Syntax: " . ($syntaxPassed ? "✓ PASSED" : "✗ FAILED") . "\n";

        if (isset($result['validation_results']['phpstan'])) {
            $phpstan = $result['validation_results']['phpstan'];
            if ($phpstan['skipped'] ?? false) {
                echo "  - PHPStan: ⊘ SKIPPED ({$phpstan['reason']})\n";
            } else {
                $passed = $phpstan['passed'] ?? false;
                $errorCount = $phpstan['total_errors'] ?? 0;
                echo "  - PHPStan: " . ($passed ? "✓ PASSED" : "✗ FAILED ({$errorCount} errors)") . "\n";
            }
        }

        if (isset($result['validation_results']['phpunit'])) {
            $phpunit = $result['validation_results']['phpunit'];
            if ($phpunit['skipped'] ?? false) {
                echo "  - PHPUnit: ⊘ SKIPPED ({$phpunit['reason']})\n";
            } else {
                $passed = $phpunit['passed'] ?? false;
                echo "  - PHPUnit: " . ($passed ? "✓ PASSED" : "⚠ REVIEW NEEDED") . "\n";
            }
        }
        echo "\n";

        // Next steps
        echo "📝 Next Steps:\n";
        foreach ($result['next_steps'] as $step) {
            echo "  {$step}\n";
        }
        echo "\n";

        echo str_repeat("=", 80) . "\n";
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

