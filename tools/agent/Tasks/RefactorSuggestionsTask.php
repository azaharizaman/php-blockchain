<?php

declare(strict_types=1);

namespace Blockchain\Agent\Tasks;

use Blockchain\Agent\Analysis\ComplexityScanner;
use Blockchain\Agent\Analysis\RefactoringSuggestion;
use Blockchain\Agent\Analysis\UnusedCodeDetector;
use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;

/**
 * RefactorSuggestionsTask analyzes code and generates refactoring suggestions.
 *
 * This task:
 * - Analyzes cyclomatic complexity
 * - Detects unused/dead code
 * - Generates git patch files for suggested changes
 * - Provides risk assessment for each suggestion
 * - Enables operator review workflow
 * - Logs activities to audit trail
 *
 * @package Blockchain\Agent\Tasks
 */
class RefactorSuggestionsTask
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
     * Create a new RefactorSuggestionsTask instance.
     *
     * @param TaskRegistry|null $registry Optional registry instance
     * @param OperatorConsole|null $console Optional console instance
     * @param string|null $projectRoot Optional project root path
     */
    public function __construct(
        TaskRegistry $registry = null,
        OperatorConsole $console = null,
        string $projectRoot = null
    ) {
        $this->registry = $registry ?? new TaskRegistry();
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->reportDir = $this->projectRoot . '/storage/agent-reports/refactor';
        
        // Initialize console with audit log path from registry
        $auditLogPath = $this->registry->getAuditLogPath();
        $this->console = $console ?? new OperatorConsole($auditLogPath);
        
        // Ensure report directory exists
        $this->ensureReportDirectory();
    }

    /**
     * Execute the refactor suggestions task.
     *
     * @param array<string, mixed> $inputs Task inputs
     * @return array<string, mixed> Task results
     * @throws ValidationException If validation fails
     */
    public function execute(array $inputs): array
    {
        // Get task definition
        $task = $this->registry->getTask('refactor-suggestions');

        // Validate inputs
        $this->registry->validateTaskInputs('refactor-suggestions', $inputs);

        // Extract inputs with defaults
        $analysisType = $inputs['analysis_type'] ?? 'full';
        $complexityThreshold = $inputs['complexity_threshold'] ?? 10;
        $riskThreshold = $inputs['risk_threshold'] ?? 'low';
        $scanPaths = $inputs['scan_paths'] ?? ['src/', 'tools/'];
        $generatePatches = $inputs['generate_patches'] ?? true;
        $outputFormat = $inputs['output_format'] ?? 'both';

        // Start analysis
        $startTime = microtime(true);
        $this->reportProgress('Starting refactoring analysis...');
        $this->reportProgress("Analysis type: {$analysisType}, Complexity threshold: {$complexityThreshold}");

        // Collect suggestions from different analyzers
        $allSuggestions = [];

        // Run complexity analysis
        if ($analysisType === 'full' || $analysisType === 'complexity') {
            $this->reportProgress('Analyzing code complexity...');
            $complexitySuggestions = $this->analyzeComplexity($complexityThreshold, $scanPaths);
            $allSuggestions = array_merge($allSuggestions, $complexitySuggestions);
        }

        // Run unused code detection
        if ($analysisType === 'full' || $analysisType === 'unused') {
            $this->reportProgress('Detecting unused code...');
            $unusedCodeSuggestions = $this->detectUnusedCode($scanPaths);
            $allSuggestions = array_merge($allSuggestions, $unusedCodeSuggestions);
        }

        // Filter by risk threshold
        $filteredSuggestions = $this->filterByRisk($allSuggestions, $riskThreshold);

        // Generate patches if requested
        if ($generatePatches) {
            $this->reportProgress('Generating patches...');
            $this->generatePatches($filteredSuggestions);
        }

        // Generate summary
        $summary = $this->generateSummary($filteredSuggestions);

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Build result
        $result = [
            'success' => true,
            'suggestions' => array_map(fn($s) => $s->toArray(), $filteredSuggestions),
            'summary' => $summary,
            'analysis_type' => $analysisType,
            'complexity_threshold' => $complexityThreshold,
            'risk_threshold' => $riskThreshold,
            'duration' => $duration,
        ];

        // Generate report files
        $reportPath = $this->generateReport($result, $filteredSuggestions, $outputFormat);
        $result['report_path'] = $reportPath;

        // Log to audit trail
        $auditLogEntry = $this->logAuditExecution($result);
        $result['audit_log_entry'] = $auditLogEntry;

        // Display results
        $this->displayResults($result);

        return $result;
    }

    /**
     * Analyze code complexity.
     *
     * @param int $threshold Complexity threshold
     * @param array<string> $scanPaths Paths to scan
     * @return array<RefactoringSuggestion>
     */
    private function analyzeComplexity(int $threshold, array $scanPaths): array
    {
        $scanner = new ComplexityScanner($this->projectRoot, $threshold, $scanPaths);
        return $scanner->scan();
    }

    /**
     * Detect unused code.
     *
     * @param array<string> $scanPaths Paths to scan
     * @return array<RefactoringSuggestion>
     */
    private function detectUnusedCode(array $scanPaths): array
    {
        $detector = new UnusedCodeDetector($this->projectRoot, $scanPaths);
        return $detector->scan();
    }

    /**
     * Filter suggestions by risk threshold.
     *
     * @param array<RefactoringSuggestion> $suggestions All suggestions
     * @param string $threshold Risk threshold
     * @return array<RefactoringSuggestion>
     */
    private function filterByRisk(array $suggestions, string $threshold): array
    {
        $riskLevels = ['low' => 1, 'medium' => 2, 'high' => 3];
        $thresholdLevel = $riskLevels[$threshold] ?? 1;

        return array_filter($suggestions, function ($suggestion) use ($riskLevels, $thresholdLevel) {
            $suggestionRisk = $suggestion->getRisk();
            $suggestionLevel = $riskLevels[$suggestionRisk] ?? 1;
            return $suggestionLevel >= $thresholdLevel;
        });
    }

    /**
     * Generate git patches for suggestions.
     *
     * @param array<RefactoringSuggestion> $suggestions Suggestions to generate patches for
     * @return void
     */
    private function generatePatches(array $suggestions): void
    {
        foreach ($suggestions as $suggestion) {
            // For now, we generate a placeholder patch structure
            // In a full implementation, this would generate actual code changes
            $patch = $this->createPatchPlaceholder($suggestion);
            $suggestion->setPatch($patch);
        }
    }

    /**
     * Create a placeholder patch for a suggestion.
     *
     * @param RefactoringSuggestion $suggestion The suggestion
     * @return string Patch content
     */
    private function createPatchPlaceholder(RefactoringSuggestion $suggestion): string
    {
        $timestamp = date('Y-m-d H:i:s');
        
        $patch = "# Refactoring Patch: {$suggestion->getTitle()}\n";
        $patch .= "# Generated: {$timestamp}\n";
        $patch .= "# Type: {$suggestion->getType()}\n";
        $patch .= "# Risk: {$suggestion->getRisk()}\n";
        $patch .= "# File: {$suggestion->getFilePath()}\n";
        
        if ($suggestion->getStartLine() !== null) {
            $patch .= "# Lines: {$suggestion->getStartLine()}";
            if ($suggestion->getEndLine() !== null) {
                $patch .= "-{$suggestion->getEndLine()}";
            }
            $patch .= "\n";
        }
        
        $patch .= "\n# Description:\n";
        $patch .= "# {$suggestion->getDescription()}\n";
        $patch .= "\n# Note: This is a placeholder patch.\n";
        $patch .= "# Manual refactoring is required based on the above guidance.\n";
        $patch .= "# Review the file and apply changes according to the description.\n";

        return $patch;
    }

    /**
     * Generate summary of suggestions.
     *
     * @param array<RefactoringSuggestion> $suggestions All suggestions
     * @return array<string, mixed>
     */
    private function generateSummary(array $suggestions): array
    {
        $summary = [
            'total_suggestions' => count($suggestions),
            'by_type' => [
                'complexity' => 0,
                'unused_code' => 0,
            ],
            'by_risk' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
            ],
        ];

        foreach ($suggestions as $suggestion) {
            $type = $suggestion->getType();
            if (isset($summary['by_type'][$type])) {
                $summary['by_type'][$type]++;
            }

            $risk = $suggestion->getRisk();
            if (isset($summary['by_risk'][$risk])) {
                $summary['by_risk'][$risk]++;
            }
        }

        return $summary;
    }

    /**
     * Generate report file(s).
     *
     * @param array<string, mixed> $result Task results
     * @param array<RefactoringSuggestion> $suggestions Suggestions
     * @param string $format Output format
     * @return string Path to primary report file
     */
    private function generateReport(array $result, array $suggestions, string $format): string
    {
        $timestamp = date('Y-m-d_His');
        $baseFilename = "refactor-suggestions-{$timestamp}";

        // Generate markdown report
        if ($format === 'markdown' || $format === 'both') {
            $mdPath = "{$this->reportDir}/{$baseFilename}.md";
            $this->writeMarkdownReport($mdPath, $result, $suggestions);
        }

        // Generate JSON report
        if ($format === 'json' || $format === 'both') {
            $jsonPath = "{$this->reportDir}/{$baseFilename}.json";
            $this->writeJsonReport($jsonPath, $result);
        }

        // Save individual patch files
        $this->savePatchFiles($suggestions, $timestamp);

        // Return path to markdown report as primary
        return $format === 'json' 
            ? "{$this->reportDir}/{$baseFilename}.json"
            : "{$this->reportDir}/{$baseFilename}.md";
    }

    /**
     * Write markdown report.
     *
     * @param string $path File path
     * @param array<string, mixed> $result Task results
     * @param array<RefactoringSuggestion> $suggestions Suggestions
     * @throws \RuntimeException If file write fails
     */
    private function writeMarkdownReport(string $path, array $result, array $suggestions): void
    {
        $summary = $result['summary'];

        $content = "# Refactoring Suggestions Report\n\n";
        $content .= "**Date:** " . date('Y-m-d H:i:s') . "\n";
        $content .= "**Analysis Type:** {$result['analysis_type']}\n";
        $content .= "**Complexity Threshold:** {$result['complexity_threshold']}\n";
        $content .= "**Risk Threshold:** {$result['risk_threshold']}\n";
        $content .= "**Duration:** {$result['duration']}s\n\n";

        // Executive Summary
        $content .= "## Executive Summary\n\n";
        $content .= "**Total Suggestions:** {$summary['total_suggestions']}\n\n";
        
        $content .= "**By Type:**\n";
        foreach ($summary['by_type'] as $type => $count) {
            $content .= "- " . ucfirst(str_replace('_', ' ', $type)) . ": {$count}\n";
        }
        $content .= "\n";

        $content .= "**By Risk:**\n";
        foreach ($summary['by_risk'] as $risk => $count) {
            $icon = $risk === 'high' ? '🔴' : ($risk === 'medium' ? '🟡' : '🟢');
            $content .= "- {$icon} " . ucfirst($risk) . ": {$count}\n";
        }
        $content .= "\n";

        // Suggestions grouped by risk
        if (!empty($suggestions)) {
            $content .= "## Suggestions\n\n";

            foreach (['high', 'medium', 'low'] as $risk) {
                $riskSuggestions = array_filter(
                    $suggestions,
                    fn($s) => $s->getRisk() === $risk
                );
                
                if (!empty($riskSuggestions)) {
                    $icon = $risk === 'high' ? '🔴' : ($risk === 'medium' ? '🟡' : '🟢');
                    $content .= "### {$icon} " . ucfirst($risk) . " Risk\n\n";
                    
                    foreach ($riskSuggestions as $i => $suggestion) {
                        $content .= ($i + 1) . ". **{$suggestion->getTitle()}**\n";
                        $content .= "   - **File:** `{$suggestion->getFilePath()}`\n";
                        
                        if ($suggestion->getStartLine() !== null) {
                            $content .= "   - **Lines:** {$suggestion->getStartLine()}";
                            if ($suggestion->getEndLine() !== null) {
                                $content .= "-{$suggestion->getEndLine()}";
                            }
                            $content .= "\n";
                        }
                        
                        $content .= "   - **Type:** " . ucfirst(str_replace('_', ' ', $suggestion->getType())) . "\n";
                        
                        if ($suggestion->getCurrentMetric() !== null) {
                            $content .= "   - **Current Metric:** {$suggestion->getCurrentMetric()}\n";
                            if ($suggestion->getExpectedMetric() !== null) {
                                $content .= "   - **Expected Metric:** {$suggestion->getExpectedMetric()}\n";
                            }
                        }
                        
                        $content .= "   - **Description:** {$suggestion->getDescription()}\n";
                        
                        if ($suggestion->getPatch() !== null) {
                            $patchFile = "patch_{$suggestion->getId()}.diff";
                            $content .= "   - **Patch File:** `{$patchFile}`\n";
                        }
                        
                        $content .= "\n";
                    }
                }
            }
        }

        $content .= "---\n\n";
        $content .= "## How to Apply Suggestions\n\n";
        $content .= "1. Review each suggestion carefully\n";
        $content .= "2. For suggestions with patches, review the patch file in `storage/agent-reports/refactor/patches/`\n";
        $content .= "3. Apply patches manually or using the CLI command (if available)\n";
        $content .= "4. Test thoroughly after applying changes\n";
        $content .= "5. Commit changes with appropriate messages\n\n";

        $content .= "---\n";
        $content .= "*This report was automatically generated by the Refactoring Suggestions Agent*\n";

        $bytesWritten = file_put_contents($path, $content);
        if ($bytesWritten === false) {
            throw new \RuntimeException("Failed to write markdown report to: {$path}");
        }
    }

    /**
     * Write JSON report.
     *
     * @param string $path File path
     * @param array<string, mixed> $result Task results
     */
    private function writeJsonReport(string $path, array $result): void
    {
        $report = [
            'timestamp' => date('c'),
            'analysis_type' => $result['analysis_type'],
            'complexity_threshold' => $result['complexity_threshold'],
            'risk_threshold' => $result['risk_threshold'],
            'duration' => $result['duration'],
            'summary' => $result['summary'],
            'suggestions' => $result['suggestions'],
        ];

        $json = json_encode($report, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode report as JSON: " . json_last_error_msg());
        }
        file_put_contents($path, $json);
    }

    /**
     * Save individual patch files.
     *
     * @param array<RefactoringSuggestion> $suggestions Suggestions with patches
     * @param string $timestamp Timestamp for directory name
     */
    private function savePatchFiles(array $suggestions, string $timestamp): void
    {
        $patchDir = "{$this->reportDir}/patches/{$timestamp}";
        
        if (!is_dir($patchDir)) {
            mkdir($patchDir, 0755, true);
        }

        foreach ($suggestions as $suggestion) {
            if ($suggestion->getPatch() !== null) {
                $patchFile = "{$patchDir}/patch_{$suggestion->getId()}.diff";
                $bytesWritten = file_put_contents($patchFile, $suggestion->getPatch());
                if ($bytesWritten === false) {
                    throw new \RuntimeException("Failed to write patch file: {$patchFile}");
                }
            }
        }
    }

    /**
     * Log audit execution to audit trail.
     *
     * @param array<string, mixed> $result Task results
     * @return string Reference to log entry
     */
    private function logAuditExecution(array $result): string
    {
        $logEntry = [
            'timestamp' => date('c'),
            'task' => 'refactor-suggestions',
            'analysis_type' => $result['analysis_type'],
            'suggestions_count' => $result['summary']['total_suggestions'],
            'high_risk_count' => $result['summary']['by_risk']['high'],
            'medium_risk_count' => $result['summary']['by_risk']['medium'],
            'low_risk_count' => $result['summary']['by_risk']['low'],
            'report_path' => $result['report_path'] ?? null,
        ];

        // Log to audit trail via console
        $this->console->logAuditEvent('refactor-suggestions', $logEntry);

        return $logEntry['timestamp'];
    }

    /**
     * Display results to operator console.
     *
     * @param array<string, mixed> $result Task results
     */
    private function displayResults(array $result): void
    {
        $summary = $result['summary'];

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "REFACTORING SUGGESTIONS RESULTS\n";
        echo str_repeat("=", 80) . "\n\n";

        // Summary
        echo "📊 Summary:\n";
        echo "  Total Suggestions: {$summary['total_suggestions']}\n\n";

        echo "  By Type:\n";
        foreach ($summary['by_type'] as $type => $count) {
            echo "    - " . ucfirst(str_replace('_', ' ', $type)) . ": {$count}\n";
        }
        echo "\n";

        echo "  By Risk:\n";
        echo "    🔴 High: {$summary['by_risk']['high']}\n";
        echo "    🟡 Medium: {$summary['by_risk']['medium']}\n";
        echo "    🟢 Low: {$summary['by_risk']['low']}\n\n";

        // Duration
        echo "⏱️  Duration: {$result['duration']}s\n\n";

        // High risk highlight
        if ($summary['by_risk']['high'] > 0) {
            echo "🔴 {$summary['by_risk']['high']} high-risk suggestion(s) require careful review!\n\n";
        }

        // Report location
        echo "📄 Report: {$result['report_path']}\n";

        echo "\n" . str_repeat("=", 80) . "\n";
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
     * Report progress to console.
     *
     * @param string $message Progress message
     */
    private function reportProgress(string $message): void
    {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
}
