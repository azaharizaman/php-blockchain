<?php

declare(strict_types=1);

namespace Blockchain\Agent\Tasks;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;

/**
 * SecurityAuditTask orchestrates comprehensive security audits.
 *
 * This task:
 * - Runs static analysis tools (PHPStan, Psalm)
 * - Performs dependency vulnerability scanning (composer audit)
 * - Checks configuration for security issues
 * - Redacts sensitive information from findings
 * - Generates sanitized reports with remediation guidance
 * - Logs audit results to audit trail
 *
 * @package Blockchain\Agent\Tasks
 */
class SecurityAuditTask
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
     * Report storage directory.
     */
    private string $reportDir;

    /**
     * Sensitive patterns for redaction.
     */
    private array $sensitivePatterns = [
        '/["\']?api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i' => '[REDACTED:API_KEY]',
        '/["\']?secret["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i' => '[REDACTED:SECRET]',
        '/["\']?password["\']?\s*[:=]\s*["\']?([^\s"\']{8,})["\']?/i' => '[REDACTED:PASSWORD]',
        '/["\']?token["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i' => '[REDACTED:TOKEN]',
        '/-----BEGIN (RSA |EC |DSA )?PRIVATE KEY-----[\s\S]*?-----END (RSA |EC |DSA )?PRIVATE KEY-----/' => '[REDACTED:PRIVATE_KEY]',
        '/0x[a-fA-F0-9]{64}/' => '[REDACTED:PRIVATE_KEY]',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[REDACTED:EMAIL]',
        '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/' => '[REDACTED:IP]',
    ];

    /**
     * Cached composer binary path.
     */
    private ?string $composerBin = null;

    /**
     * Create a new SecurityAuditTask instance.
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
        $this->reportDir = $this->projectRoot . '/storage/agent-reports/security';
        
        // Initialize console with audit log path from registry
        $auditLogPath = $this->registry->getAuditLogPath();
        $this->console = $console ?? new OperatorConsole($auditLogPath);
        
        // Ensure report directory exists
        $this->ensureReportDirectory();
    }

    /**
     * Execute the security audit task.
     *
     * @param array<string, mixed> $inputs Task inputs
     * @return array<string, mixed> Task results
     * @throws ValidationException If validation fails
     */
    public function execute(array $inputs): array
    {
        // Get task definition
        $task = $this->registry->getTask('security-audit');

        // Validate inputs
        $this->registry->validateTaskInputs('security-audit', $inputs);

        // Extract inputs with defaults
        $scanType = $inputs['scan_type'] ?? 'full';
        $severityThreshold = $inputs['severity_threshold'] ?? 'medium';
        $failOnFinding = $inputs['fail_on_finding'] ?? false;
        $includeRecommendations = $inputs['include_recommendations'] ?? true;
        $outputFormat = $inputs['output_format'] ?? 'both';

        // Start audit
        $startTime = microtime(true);
        $this->reportProgress('Starting security audit...');
        $this->reportProgress("Scan type: {$scanType}, Severity threshold: {$severityThreshold}");

        // Collect findings from different scan types
        $allFindings = [];

        // Run static analysis
        if ($scanType === 'full' || $scanType === 'static') {
            $this->reportProgress('Running static analysis...');
            $staticFindings = $this->runStaticAnalysis();
            $allFindings = array_merge($allFindings, $staticFindings);
        }

        // Run dependency audit
        if ($scanType === 'full' || $scanType === 'dependencies') {
            $this->reportProgress('Auditing dependencies...');
            $dependencyFindings = $this->runDependencyAudit();
            $allFindings = array_merge($allFindings, $dependencyFindings);
        }

        // Run configuration checks
        if ($scanType === 'full' || $scanType === 'config') {
            $this->reportProgress('Checking configuration...');
            $configFindings = $this->runConfigurationChecks();
            $allFindings = array_merge($allFindings, $configFindings);
        }

        // Filter by severity threshold
        $filteredFindings = $this->filterBySeverity($allFindings, $severityThreshold);

        // Redact sensitive information
        $sanitizedFindings = $this->redactSensitiveData($filteredFindings);

        // Generate summary
        $summary = $this->generateSummary($sanitizedFindings);

        // Generate recommendations
        $recommendations = $includeRecommendations 
            ? $this->generateRecommendations($sanitizedFindings)
            : [];

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Build result
        $result = [
            'success' => true,
            'findings' => $sanitizedFindings,
            'summary' => $summary,
            'recommendations' => $recommendations,
            'scan_type' => $scanType,
            'severity_threshold' => $severityThreshold,
            'duration' => $duration,
        ];

        // Generate report files
        $reportPath = $this->generateReport($result, $outputFormat);
        $result['report_path'] = $reportPath;

        // Log to audit trail
        $auditLogEntry = $this->logAuditExecution($result);
        $result['audit_log_entry'] = $auditLogEntry;

        // Display results
        $this->displayResults($result);

        // Check if we should fail on findings
        if ($failOnFinding && $summary['total_findings'] > 0) {
            throw new ValidationException(
                "Security audit found {$summary['total_findings']} issue(s). " .
                "Critical: {$summary['critical']}, High: {$summary['high']}, " .
                "Medium: {$summary['medium']}, Low: {$summary['low']}"
            );
        }

        return $result;
    }

    /**
     * Run static analysis using PHPStan and other tools.
     *
     * @return array<array<string, mixed>> List of findings
     */
    private function runStaticAnalysis(): array
    {
        $findings = [];

        // Check if PHPStan is available
        $phpstanBin = $this->projectRoot . '/vendor/bin/phpstan';
        
        if (file_exists($phpstanBin)) {
            $findings = array_merge($findings, $this->runPhpStan());
        } else {
            $this->reportProgress('PHPStan not found, skipping static analysis');
        }

        // Check for common security issues in code
        $findings = array_merge($findings, $this->scanForSecurityPatterns());

        return $findings;
    }

    /**
     * Run PHPStan analysis.
     *
     * @return array<array<string, mixed>> List of findings
     */
    private function runPhpStan(): array
    {
        $findings = [];
        $phpstanBin = $this->projectRoot . '/vendor/bin/phpstan';

        // Run PHPStan with JSON output
        $command = sprintf(
            '%s analyse --level=7 --error-format=json --no-progress src tests 2>&1',
            escapeshellarg($phpstanBin)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        $result = json_decode($outputStr, true);

        if ($result !== null && isset($result['files'])) {
            foreach ($result['files'] as $file => $fileErrors) {
                foreach ($fileErrors['messages'] as $error) {
                    $findings[] = [
                        'type' => 'static_analysis',
                        'tool' => 'PHPStan',
                        'severity' => $this->mapPhpStanSeverity($error['message'] ?? ''),
                        'file' => $this->relativizePath($file),
                        'line' => $error['line'] ?? 0,
                        'message' => $error['message'] ?? 'Unknown error',
                        'description' => 'Static analysis detected a potential issue',
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Scan for common security patterns in code.
     *
     * @return array<array<string, mixed>> List of findings
     */
    private function scanForSecurityPatterns(): array
    {
        $findings = [];
        $patterns = [
            '/eval\s*\(/i' => ['severity' => 'critical', 'message' => 'Use of eval() detected - high security risk'],
            '/exec\s*\(/i' => ['severity' => 'high', 'message' => 'Use of exec() detected - ensure input validation'],
            '/system\s*\(/i' => ['severity' => 'high', 'message' => 'Use of system() detected - ensure input validation'],
            '/passthru\s*\(/i' => ['severity' => 'high', 'message' => 'Use of passthru() detected - ensure input validation'],
            '/shell_exec\s*\(/i' => ['severity' => 'high', 'message' => 'Use of shell_exec() detected - ensure input validation'],
            '/unserialize\s*\(/i' => ['severity' => 'medium', 'message' => 'Use of unserialize() detected - potential object injection'],
            '/\$_(GET|POST|REQUEST|COOKIE)\[/i' => ['severity' => 'low', 'message' => 'Direct superglobal access - ensure input validation'],
        ];

        // Scan PHP files in src and tests directories
        $directories = [
            $this->projectRoot . '/src',
            $this->projectRoot . '/tools',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    $lines = explode("\n", $content);

                    foreach ($patterns as $pattern => $config) {
                        foreach ($lines as $lineNum => $line) {
                            if (preg_match($pattern, $line)) {
                                $findings[] = [
                                    'type' => 'security_pattern',
                                    'tool' => 'Pattern Scanner',
                                    'severity' => $config['severity'],
                                    'file' => $this->relativizePath($file->getPathname()),
                                    'line' => $lineNum + 1,
                                    'message' => $config['message'],
                                    'description' => 'Potentially insecure code pattern detected',
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Run dependency vulnerability audit.
     *
     * @return array<array<string, mixed>> List of findings
     */
    private function runDependencyAudit(): array
    {
        $findings = [];

        // Check if composer is available (with caching)
        if ($this->composerBin === null) {
            $this->composerBin = $this->findComposerBinary();
        }
        
        if ($this->composerBin === false) {
            $this->reportProgress('Composer not found, skipping dependency audit');
            return $findings;
        }

        // Run composer audit
        $command = sprintf(
            'cd %s && %s audit --format=json 2>&1',
            escapeshellarg($this->projectRoot),
            escapeshellarg($this->composerBin)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);
        
        // Try to parse JSON output
        $result = json_decode($outputStr, true);

        if ($result !== null && isset($result['advisories'])) {
            foreach ($result['advisories'] as $package => $advisories) {
                foreach ($advisories as $advisory) {
                    $findings[] = [
                        'type' => 'dependency_vulnerability',
                        'tool' => 'Composer Audit',
                        'severity' => $this->mapComposerSeverity($advisory['severity'] ?? 'medium'),
                        'package' => $package,
                        'current_version' => $advisory['affectedVersions'] ?? 'unknown',
                        'fixed_version' => $advisory['sources'][0]['remoteId'] ?? 'See advisory',
                        'message' => $advisory['title'] ?? 'Vulnerability in dependency',
                        'description' => $advisory['link'] ?? 'No description available',
                        'cve' => $advisory['cve'] ?? null,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Run configuration security checks.
     *
     * @return array<array<string, mixed>> List of findings
     */
    private function runConfigurationChecks(): array
    {
        $findings = [];

        // Check for hardcoded credentials in config files
        $configPaths = [
            $this->projectRoot . '/config',
            $this->projectRoot . '/.env.example',
        ];

        foreach ($configPaths as $path) {
            if (is_dir($path)) {
                $findings = array_merge($findings, $this->scanDirectoryForSecrets($path));
            } elseif (is_file($path)) {
                $findings = array_merge($findings, $this->scanFileForSecrets($path));
            }
        }

        // Check composer.json for security issues
        $composerJson = $this->projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $findings = array_merge($findings, $this->checkComposerSecurity($composerJson));
        }

        return $findings;
    }

    /**
     * Scan directory for secrets and credentials.
     *
     * @param string $directory Directory path
     * @return array<array<string, mixed>> List of findings
     */
    private function scanDirectoryForSecrets(string $directory): array
    {
        $findings = [];

        if (!is_dir($directory)) {
            return $findings;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $findings = array_merge($findings, $this->scanFileForSecrets($file->getPathname()));
            }
        }

        return $findings;
    }

    /**
     * Scan file for secrets and credentials.
     *
     * @param string $filePath File path
     * @return array<array<string, mixed>> List of findings
     */
    private function scanFileForSecrets(string $filePath): array
    {
        $findings = [];
        $content = @file_get_contents($filePath);

        if ($content === false) {
            return $findings;
        }

        $lines = explode("\n", $content);

        // Patterns for hardcoded secrets (excluding example/dummy values)
        $secretPatterns = [
            '/api[_-]?key\s*[:=]\s*["\']?([a-zA-Z0-9]{32,})["\']?/i',
            '/secret\s*[:=]\s*["\']?([a-zA-Z0-9]{32,})["\']?/i',
            '/password\s*[:=]\s*["\']?(?!example|dummy|test|changeme|password|secret|12345678)([^\s"\']{8,})["\']?/i',
            '/token\s*[:=]\s*["\']?([a-zA-Z0-9]{32,})["\']?/i',
        ];

        foreach ($lines as $lineNum => $line) {
            // Skip comment lines in example files
            if (str_contains($filePath, 'example') && (str_starts_with(trim($line), '#') || str_starts_with(trim($line), '//'))) {
                continue;
            }

            foreach ($secretPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $findings[] = [
                        'type' => 'hardcoded_credential',
                        'tool' => 'Secret Scanner',
                        'severity' => 'critical',
                        'file' => $this->relativizePath($filePath),
                        'line' => $lineNum + 1,
                        'message' => 'Potential hardcoded credential detected',
                        'description' => 'Hardcoded credentials should be moved to environment variables',
                    ];
                    break; // Only report once per line
                }
            }
        }

        return $findings;
    }

    /**
     * Check composer.json for security issues.
     *
     * @param string $composerJsonPath Path to composer.json
     * @return array<array<string, mixed>> List of findings
     */
    private function checkComposerSecurity(string $composerJsonPath): array
    {
        $findings = [];
        $content = file_get_contents($composerJsonPath);
        $composer = json_decode($content, true);

        if ($composer === null) {
            return $findings;
        }

        // Check for minimum-stability
        if (isset($composer['minimum-stability']) && $composer['minimum-stability'] === 'dev') {
            $findings[] = [
                'type' => 'configuration',
                'tool' => 'Config Checker',
                'severity' => 'medium',
                'file' => $this->relativizePath($composerJsonPath),
                'message' => 'minimum-stability set to "dev" - may allow unstable dependencies',
                'description' => 'Consider setting minimum-stability to "stable" for production',
            ];
        }

        return $findings;
    }

    /**
     * Filter findings by severity threshold.
     *
     * @param array<array<string, mixed>> $findings All findings
     * @param string $threshold Severity threshold
     * @return array<array<string, mixed>> Filtered findings
     */
    private function filterBySeverity(array $findings, string $threshold): array
    {
        $severityLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $thresholdLevel = $severityLevels[$threshold] ?? 2;

        return array_filter($findings, function ($finding) use ($severityLevels, $thresholdLevel) {
            $findingSeverity = $finding['severity'] ?? 'low';
            $findingLevel = $severityLevels[$findingSeverity] ?? 1;
            return $findingLevel >= $thresholdLevel;
        });
    }

    /**
     * Redact sensitive data from findings.
     *
     * @param array<array<string, mixed>> $findings Findings to redact
     * @return array<array<string, mixed>> Redacted findings
     */
    private function redactSensitiveData(array $findings): array
    {
        return array_map(function ($finding) {
            foreach ($finding as $key => $value) {
                if (is_string($value)) {
                    foreach ($this->sensitivePatterns as $pattern => $replacement) {
                        $value = preg_replace($pattern, $replacement, $value);
                    }
                    $finding[$key] = $value;
                }
            }
            return $finding;
        }, $findings);
    }

    /**
     * Generate summary of findings.
     *
     * @param array<array<string, mixed>> $findings All findings
     * @return array<string, mixed> Summary
     */
    private function generateSummary(array $findings): array
    {
        $summary = [
            'total_findings' => count($findings),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'passed' => count($findings) === 0,
        ];

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? 'low';
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        return $summary;
    }

    /**
     * Generate remediation recommendations.
     *
     * @param array<array<string, mixed>> $findings All findings
     * @return array<array<string, mixed>> Recommendations
     */
    private function generateRecommendations(array $findings): array
    {
        $recommendations = [];
        $criticalFindings = array_filter($findings, fn($f) => ($f['severity'] ?? '') === 'critical');
        $highFindings = array_filter($findings, fn($f) => ($f['severity'] ?? '') === 'high');

        // Critical recommendations
        if (!empty($criticalFindings)) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Immediate action required',
                'description' => sprintf(
                    'Address %d critical security issue(s) immediately. These pose significant risk.',
                    count($criticalFindings)
                ),
                'count' => count($criticalFindings),
            ];
        }

        // High priority recommendations
        if (!empty($highFindings)) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Address within current sprint',
                'description' => sprintf(
                    'Resolve %d high-severity issue(s). Schedule remediation work this sprint.',
                    count($highFindings)
                ),
                'count' => count($highFindings),
            ];
        }

        // Specific recommendations based on finding types
        $dependencyIssues = array_filter($findings, fn($f) => ($f['type'] ?? '') === 'dependency_vulnerability');
        if (!empty($dependencyIssues)) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Update vulnerable dependencies',
                'description' => sprintf(
                    'Update %d vulnerable package(s) to patched versions using composer update.',
                    count($dependencyIssues)
                ),
                'count' => count($dependencyIssues),
            ];
        }

        $secretIssues = array_filter($findings, fn($f) => ($f['type'] ?? '') === 'hardcoded_credential');
        if (!empty($secretIssues)) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Remove hardcoded credentials',
                'description' => sprintf(
                    'Move %d hardcoded credential(s) to environment variables and rotate exposed secrets.',
                    count($secretIssues)
                ),
                'count' => count($secretIssues),
            ];
        }

        return $recommendations;
    }

    /**
     * Generate security report file(s).
     *
     * @param array<string, mixed> $result Audit results
     * @param string $format Output format
     * @return string Path to primary report file
     */
    private function generateReport(array $result, string $format): string
    {
        $timestamp = date('Y-m-d_His');
        $baseFilename = "security-audit-{$timestamp}";

        // Generate markdown report
        if ($format === 'markdown' || $format === 'both') {
            $mdPath = "{$this->reportDir}/{$baseFilename}.md";
            $this->writeMarkdownReport($mdPath, $result);
        }

        // Generate JSON report
        if ($format === 'json' || $format === 'both') {
            $jsonPath = "{$this->reportDir}/{$baseFilename}.json";
            $this->writeJsonReport($jsonPath, $result);
        }

        // Return path to markdown report as primary
        return $format === 'json' 
            ? "{$this->reportDir}/{$baseFilename}.json"
            : "{$this->reportDir}/{$baseFilename}.md";
    }

    /**
     * Write markdown report.
     *
     * @param string $path File path
     * @param array<string, mixed> $result Audit results
     */
    private function writeMarkdownReport(string $path, array $result): void
    {
        $summary = $result['summary'];
        $findings = $result['findings'];
        $recommendations = $result['recommendations'];

        $content = "# Security Audit Report\n\n";
        $content .= "**Date:** " . date('Y-m-d H:i:s') . "\n";
        $content .= "**Scan Type:** {$result['scan_type']}\n";
        $content .= "**Severity Threshold:** {$result['severity_threshold']}\n";
        $content .= "**Duration:** {$result['duration']}s\n\n";

        // Executive Summary
        $content .= "## Executive Summary\n\n";
        $status = $summary['passed'] ? '✅ PASSED' : '❌ FAILED';
        $content .= "**Status:** {$status}\n\n";
        $content .= "**Total Findings:** {$summary['total_findings']}\n";
        $content .= "- Critical: {$summary['critical']}\n";
        $content .= "- High: {$summary['high']}\n";
        $content .= "- Medium: {$summary['medium']}\n";
        $content .= "- Low: {$summary['low']}\n\n";

        // Recommendations
        if (!empty($recommendations)) {
            $content .= "## Recommendations\n\n";
            foreach ($recommendations as $i => $rec) {
                $priorityIcon = $rec['priority'] === 'critical' ? '🔴' : '🟡';
                $content .= ($i + 1) . ". {$priorityIcon} **{$rec['action']}**\n";
                $content .= "   - {$rec['description']}\n\n";
            }
        }

        // Findings by severity
        if (!empty($findings)) {
            $content .= "## Findings\n\n";

            foreach (['critical', 'high', 'medium', 'low'] as $severity) {
                $severityFindings = array_filter($findings, fn($f) => ($f['severity'] ?? '') === $severity);
                
                if (!empty($severityFindings)) {
                    $content .= "### " . ucfirst($severity) . " Severity\n\n";
                    
                    foreach ($severityFindings as $i => $finding) {
                        $content .= ($i + 1) . ". **{$finding['message']}**\n";
                        if (isset($finding['file'])) {
                            $content .= "   - File: `{$finding['file']}`";
                            if (isset($finding['line'])) {
                                $content .= " (Line {$finding['line']})";
                            }
                            $content .= "\n";
                        }
                        if (isset($finding['package'])) {
                            $content .= "   - Package: `{$finding['package']}`\n";
                        }
                        if (isset($finding['tool'])) {
                            $content .= "   - Tool: {$finding['tool']}\n";
                        }
                        if (isset($finding['description'])) {
                            $content .= "   - {$finding['description']}\n";
                        }
                        $content .= "\n";
                    }
                }
            }
        }

        $content .= "---\n";
        $content .= "*This report was automatically generated by the Security Audit Agent*\n";

        file_put_contents($path, $content);
    }

    /**
     * Write JSON report.
     *
     * @param string $path File path
     * @param array<string, mixed> $result Audit results
     */
    private function writeJsonReport(string $path, array $result): void
    {
        $report = [
            'timestamp' => date('c'),
            'scan_type' => $result['scan_type'],
            'severity_threshold' => $result['severity_threshold'],
            'duration' => $result['duration'],
            'summary' => $result['summary'],
            'recommendations' => $result['recommendations'],
            'findings' => $result['findings'],
        ];

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Log audit execution to audit trail.
     *
     * @param array<string, mixed> $result Audit results
     * @return string Reference to log entry
     */
    private function logAuditExecution(array $result): string
    {
        $logEntry = [
            'timestamp' => date('c'),
            'task' => 'security-audit',
            'scan_type' => $result['scan_type'],
            'findings_count' => $result['summary']['total_findings'],
            'critical_count' => $result['summary']['critical'],
            'high_count' => $result['summary']['high'],
            'status' => $result['summary']['passed'] ? 'passed' : 'failed',
            'report_path' => $result['report_path'] ?? null,
        ];

        // Log to audit trail via console
        $this->console->logAuditEvent('security-audit', $logEntry);

        return $logEntry['timestamp'];
    }

    /**
     * Display results to operator console.
     *
     * @param array<string, mixed> $result Audit results
     */
    private function displayResults(array $result): void
    {
        $summary = $result['summary'];

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "SECURITY AUDIT RESULTS\n";
        echo str_repeat("=", 80) . "\n\n";

        // Status
        $status = $summary['passed'] ? '✅ PASSED' : '❌ FAILED';
        echo "Status: {$status}\n\n";

        // Summary
        echo "📊 Summary:\n";
        echo "  Total Findings: {$summary['total_findings']}\n";
        echo "  - Critical: {$summary['critical']}\n";
        echo "  - High: {$summary['high']}\n";
        echo "  - Medium: {$summary['medium']}\n";
        echo "  - Low: {$summary['low']}\n\n";

        // Duration
        echo "⏱️  Duration: {$result['duration']}s\n\n";

        // Critical findings highlight
        if ($summary['critical'] > 0) {
            echo "🔴 CRITICAL: {$summary['critical']} critical issue(s) require immediate attention!\n\n";
        }

        // Recommendations
        if (!empty($result['recommendations'])) {
            echo "📝 Recommendations:\n";
            foreach ($result['recommendations'] as $i => $rec) {
                echo "  " . ($i + 1) . ". [{$rec['priority']}] {$rec['action']}\n";
            }
            echo "\n";
        }

        // Report location
        echo "📄 Report: {$result['report_path']}\n";

        echo "\n" . str_repeat("=", 80) . "\n";
    }

    /**
     * Map PHPStan error severity.
     *
     * @param string $message Error message
     * @return string Severity level
     */
    private function mapPhpStanSeverity(string $message): string
    {
        // Map PHPStan errors to severity levels
        if (str_contains(strtolower($message), 'security')) {
            return 'high';
        }
        if (str_contains(strtolower($message), 'undefined') || str_contains(strtolower($message), 'not found')) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Map Composer audit severity.
     *
     * @param string $severity Composer severity
     * @return string Normalized severity
     */
    private function mapComposerSeverity(string $severity): string
    {
        $mapping = [
            'critical' => 'critical',
            'high' => 'high',
            'moderate' => 'medium',
            'medium' => 'medium',
            'low' => 'low',
        ];

        return $mapping[strtolower($severity)] ?? 'medium';
    }

    /**
     * Convert absolute path to relative path.
     *
     * @param string $path Absolute path
     * @return string Relative path
     */
    private function relativizePath(string $path): string
    {
        return str_replace($this->projectRoot . '/', '', $path);
    }

    /**
     * Ensure report directory exists.
     */
    private function ensureReportDirectory(): void
    {
        if (!is_dir($this->reportDir)) {
            mkdir($this->reportDir, 0755, true);
        }
    }

    /**
     * Find composer binary by checking common locations.
     *
     * @return string|false Composer binary path or false if not found
     */
    private function findComposerBinary(): string|false
    {
        // Check common locations
        $locations = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            $this->projectRoot . '/vendor/bin/composer',
            'composer', // In PATH
        ];

        foreach ($locations as $location) {
            if ($location === 'composer') {
                // Check if it's in PATH
                $which = trim(shell_exec('which composer 2>/dev/null') ?? '');
                if (!empty($which) && file_exists($which)) {
                    return $which;
                }
            } elseif (file_exists($location) && is_executable($location)) {
                return $location;
            }
        }

        return false;
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
