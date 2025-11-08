<?php

declare(strict_types=1);

namespace Blockchain\Agent\Tasks;

use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Exceptions\ValidationException;

/**
 * UpdateReadmeTask orchestrates documentation synchronization.
 *
 * This task:
 * - Scans src/Drivers/ to discover available drivers
 * - Updates README.md supported blockchains table
 * - Generates/updates driver-specific documentation
 * - Provides diff preview before committing changes
 * - Optionally updates CHANGELOG.md
 *
 * @package Blockchain\Agent\Tasks
 */
class UpdateReadmeTask
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
     * Create a new UpdateReadmeTask instance.
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
        
        // Initialize console with audit log path from registry
        $auditLogPath = $this->registry->getAuditLogPath();
        $this->console = $console ?? new OperatorConsole($auditLogPath);
    }

    /**
     * Execute the update readme task.
     *
     * @param array<string, mixed> $inputs Task inputs
     * @return array<string, mixed> Task results
     * @throws ValidationException If validation fails
     */
    public function execute(array $inputs): array
    {
        // Get task definition
        $task = $this->registry->getTask('update-readme');

        // Validate inputs
        $this->registry->validateTaskInputs('update-readme', $inputs);

        // Extract inputs with defaults
        $updateType = $inputs['update_type'] ?? 'all';
        $driverNames = $inputs['driver_names'] ?? [];
        $templatePath = $inputs['template_path'] ?? null;
        $changelogEntry = $inputs['changelog_entry'] ?? null;
        $previewOnly = $inputs['preview_only'] ?? false;

        // Discover available drivers
        $this->reportProgress('Scanning for blockchain drivers...');
        $drivers = $this->discoverDrivers($driverNames);

        // Determine affected paths
        $affectedPaths = $this->calculateAffectedPaths($updateType, $drivers);

        // Validate paths
        $this->console->validatePaths($this->registry, 'update-readme', $affectedPaths);

        // Request operator approval
        $approved = $this->console->requestApproval(
            'update-readme',
            $task,
            $inputs,
            $affectedPaths
        );

        if (!$approved) {
            throw new ValidationException('Operator denied task execution');
        }

        // Generate changes and diffs
        $changes = [];
        $diffs = [];

        if ($updateType === 'drivers' || $updateType === 'all') {
            $this->reportProgress('Updating driver documentation...');
            $driverChanges = $this->updateDriverDocumentation($drivers, $templatePath);
            $changes = array_merge($changes, $driverChanges);
        }

        if ($updateType === 'api' || $updateType === 'all') {
            $this->reportProgress('Updating API documentation...');
            $apiChanges = $this->updateApiDocumentation($drivers);
            $changes = array_merge($changes, $apiChanges);
        }

        if ($updateType === 'changelog' || $updateType === 'all') {
            if ($changelogEntry) {
                $this->reportProgress('Updating changelog...');
                $changelogChanges = $this->updateChangelog($changelogEntry);
                $changes = array_merge($changes, $changelogChanges);
            }
        }

        // Update README with driver list
        $this->reportProgress('Updating README.md...');
        $readmeChanges = $this->updateReadmeDriverTable($drivers);
        $changes = array_merge($changes, $readmeChanges);

        // Generate diffs for all changes
        foreach ($changes as $change) {
            $diffs[] = $this->generateDiff($change);
        }

        // Display diff summary
        $diffSummary = $this->generateDiffSummary($diffs);
        echo "\n" . $diffSummary . "\n";

        // If preview only, return without writing
        if ($previewOnly) {
            return [
                'success' => true,
                'preview_only' => true,
                'drivers_found' => $this->formatDriverList($drivers),
                'changes_proposed' => count($changes),
                'preview_diff' => implode("\n\n", array_map(fn($d) => $d['unified_diff'], $diffs)),
                'diff_summary' => $diffSummary,
            ];
        }

        // Write changes to files
        $this->reportProgress('Writing changes to files...');
        $filesUpdated = $this->writeChanges($changes);

        // Build result
        $result = [
            'success' => true,
            'files_updated' => $filesUpdated,
            'diff_summary' => $diffSummary,
            'drivers_found' => $this->formatDriverList($drivers),
            'changes_made' => count($changes),
        ];

        // Display summary
        $this->displaySummary($result);

        return $result;
    }

    /**
     * Discover blockchain drivers in the project.
     *
     * @param array<string> $filterNames Optional driver names to filter
     * @return array<string, array<string, mixed>> Driver information keyed by driver name
     */
    private function discoverDrivers(array $filterNames = []): array
    {
        $driversPath = $this->projectRoot . '/src/Drivers';
        $drivers = [];

        if (!is_dir($driversPath)) {
            return $drivers;
        }

        $files = glob($driversPath . '/*Driver.php');
        
        foreach ($files as $file) {
            $driverInfo = $this->extractDriverInfo($file);
            
            if ($driverInfo !== null) {
                $driverName = $driverInfo['name'];
                
                // Filter by names if provided
                if (empty($filterNames) || in_array($driverName, $filterNames, true)) {
                    $drivers[$driverName] = $driverInfo;
                }
            }
        }

        return $drivers;
    }

    /**
     * Extract driver information from a driver file.
     *
     * @param string $filePath Path to driver file
     * @return array<string, mixed>|null Driver information or null if parsing fails
     */
    private function extractDriverInfo(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract class name
        if (!preg_match('/class\s+(\w+Driver)\s+implements/i', $content, $matches)) {
            return null;
        }
        $className = $matches[1];
        $driverName = str_replace('Driver', '', $className);

        // Extract network type
        $networkType = 'Unknown';
        if (preg_match('/\*\s*@network-type\s+(evm|non-evm)/i', $content, $matches)) {
            $networkType = ucfirst($matches[1]);
        } elseif (str_contains(strtolower($content), 'evm') && !str_contains(strtolower($content), 'non-evm')) {
            $networkType = 'EVM';
        } elseif (str_contains(strtolower($content), 'solana') || str_contains(strtolower($content), 'near')) {
            $networkType = 'Non-EVM';
        }

        // Extract native currency
        $nativeCurrency = 'N/A';
        if (preg_match('/private\s+const\s+CURRENCY\s*=\s*[\'"](\w+)[\'"]/', $content, $matches)) {
            $nativeCurrency = $matches[1];
        }

        // Determine status based on file completeness
        $status = $this->determineDriverStatus($filePath);

        // Check if documentation exists
        $docPath = $this->projectRoot . '/docs/drivers/' . strtolower($driverName) . '.md';
        $hasDocumentation = file_exists($docPath);

        return [
            'name' => $driverName,
            'class' => $className,
            'file' => basename($filePath),
            'network_type' => $networkType,
            'native_currency' => $nativeCurrency,
            'status' => $status,
            'has_documentation' => $hasDocumentation,
            'doc_path' => $docPath,
        ];
    }

    /**
     * Determine driver status based on implementation completeness.
     *
     * @param string $filePath Path to driver file
     * @return string Status emoji and text
     */
    private function determineDriverStatus(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '❓ Unknown';
        }

        // Check for TODO markers
        $todoCount = substr_count($content, 'TODO');
        
        // Check for basic method implementations
        $hasConnect = preg_match('/public\s+function\s+connect\([^)]*\):\s*void\s*\{[^}]+\}/s', $content);
        $hasGetBalance = preg_match('/public\s+function\s+getBalance\([^)]*\):\s*float\s*\{[^}]+\}/s', $content);
        
        if ($todoCount > 5) {
            return '🔄 Planned';
        } elseif ($todoCount > 0 || !$hasConnect || !$hasGetBalance) {
            return '⚠️  In Progress';
        } else {
            return '✅ Ready';
        }
    }

    /**
     * Calculate affected file paths for this task.
     *
     * @param string $updateType Type of update
     * @param array<string, array<string, mixed>> $drivers Discovered drivers
     * @return array<string> List of file paths
     */
    private function calculateAffectedPaths(string $updateType, array $drivers): array
    {
        $paths = ['README.md'];

        if ($updateType === 'drivers' || $updateType === 'all') {
            foreach ($drivers as $driver) {
                $paths[] = $driver['doc_path'];
            }
        }

        if ($updateType === 'changelog' || $updateType === 'all') {
            $changelogPath = $this->projectRoot . '/CHANGELOG.md';
            if (file_exists($changelogPath)) {
                $paths[] = 'CHANGELOG.md';
            }
        }

        return $paths;
    }

    /**
     * Update driver-specific documentation.
     *
     * @param array<string, array<string, mixed>> $drivers Driver information
     * @param string|null $templatePath Optional custom template path
     * @return array<array<string, mixed>> List of changes
     */
    private function updateDriverDocumentation(array $drivers, ?string $templatePath = null): array
    {
        $changes = [];

        foreach ($drivers as $driver) {
            $docPath = $driver['doc_path'];
            $oldContent = file_exists($docPath) ? file_get_contents($docPath) : '';
            
            // Generate new content (simplified for now - would use template rendering)
            $newContent = $this->generateDriverDocumentation($driver, $templatePath);
            
            if ($oldContent !== $newContent) {
                $changes[] = [
                    'file' => $docPath,
                    'old_content' => $oldContent,
                    'new_content' => $newContent,
                    'type' => 'driver_doc',
                ];
            }
        }

        return $changes;
    }

    /**
     * Generate driver documentation content.
     *
     * @param array<string, mixed> $driver Driver information
     * @param string|null $templatePath Optional template path
     * @return string Generated documentation
     */
    private function generateDriverDocumentation(array $driver, ?string $templatePath = null): string
    {
        // Simple template-based generation
        $template = "# {$driver['name']} Driver\n\n";
        $template .= "## Overview\n\n";
        $template .= "The `{$driver['class']}` provides integration with the {$driver['name']} blockchain network.\n\n";
        $template .= "**Network Type**: {$driver['network_type']}\n";
        $template .= "**Native Currency**: {$driver['native_currency']}\n";
        $template .= "**Status**: {$driver['status']}\n\n";
        $template .= "## Basic Usage\n\n";
        $template .= "```php\n";
        $template .= "use Blockchain\\BlockchainManager;\n\n";
        $template .= "\$blockchain = new BlockchainManager('" . strtolower($driver['name']) . "', [\n";
        $template .= "    'endpoint' => 'your-rpc-endpoint'\n";
        $template .= "]);\n\n";
        $template .= "\$balance = \$blockchain->getBalance('address');\n";
        $template .= "```\n\n";
        $template .= "## Methods\n\n";
        $template .= "See [BlockchainDriverInterface](../../src/Contracts/BlockchainDriverInterface.php) for available methods.\n";

        return $template;
    }

    /**
     * Update API documentation.
     *
     * @param array<string, array<string, mixed>> $drivers Driver information
     * @return array<array<string, mixed>> List of changes
     */
    private function updateApiDocumentation(array $drivers): array
    {
        // Placeholder for API documentation updates
        return [];
    }

    /**
     * Update CHANGELOG.md with new entry.
     *
     * @param string $entry Changelog entry
     * @return array<array<string, mixed>> List of changes
     */
    private function updateChangelog(string $entry): array
    {
        $changelogPath = $this->projectRoot . '/CHANGELOG.md';
        
        if (!file_exists($changelogPath)) {
            return [];
        }

        $oldContent = file_get_contents($changelogPath);
        $date = date('Y-m-d');
        $newEntry = "\n## [{$date}]\n\n- {$entry}\n";
        
        // Insert after the first heading
        $newContent = preg_replace(
            '/(# [^\n]+\n)/',
            "$1{$newEntry}",
            $oldContent,
            1
        );

        if ($oldContent !== $newContent) {
            return [[
                'file' => $changelogPath,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'type' => 'changelog',
            ]];
        }

        return [];
    }

    /**
     * Update README.md driver table.
     *
     * @param array<string, array<string, mixed>> $drivers Driver information
     * @return array<array<string, mixed>> List of changes
     */
    private function updateReadmeDriverTable(array $drivers): array
    {
        $readmePath = $this->projectRoot . '/README.md';
        $oldContent = file_get_contents($readmePath);

        // Generate new table
        $table = "| Blockchain | Status | Driver Class | Network Type | Documentation |\n";
        $table .= "|------------|--------|--------------|--------------|---------------|\n";
        
        foreach ($drivers as $driver) {
            $docLink = $driver['has_documentation'] 
                ? "[docs/drivers/" . strtolower($driver['name']) . ".md](docs/drivers/" . strtolower($driver['name']) . ".md)"
                : "-";
            
            $table .= "| {$driver['name']} | {$driver['status']} | `{$driver['class']}` | {$driver['network_type']} | {$docLink} |\n";
        }

        // Replace the table in README between markers
        $newContent = preg_replace(
            '/\| Blockchain \| Status \|.*?\n(?:\|[^\n]+\n)+/s',
            $table,
            $oldContent,
            1
        );

        if ($oldContent !== $newContent) {
            return [[
                'file' => $readmePath,
                'old_content' => $oldContent,
                'new_content' => $newContent,
                'type' => 'readme',
            ]];
        }

        return [];
    }

    /**
     * Generate diff for a change.
     *
     * @param array<string, mixed> $change Change information
     * @return array<string, mixed> Diff information
     */
    private function generateDiff(array $change): array
    {
        $oldLines = explode("\n", $change['old_content']);
        $newLines = explode("\n", $change['new_content']);

        // Simple diff generation (in production, use a proper diff library)
        $additions = 0;
        $deletions = 0;

        foreach ($newLines as $line) {
            if (!in_array($line, $oldLines, true)) {
                $additions++;
            }
        }

        foreach ($oldLines as $line) {
            if (!in_array($line, $newLines, true)) {
                $deletions++;
            }
        }

        return [
            'file' => $change['file'],
            'type' => $change['type'],
            'additions' => $additions,
            'deletions' => $deletions,
            'unified_diff' => $this->generateUnifiedDiff(
                $change['file'],
                $change['old_content'],
                $change['new_content']
            ),
        ];
    }

    /**
     * Generate unified diff format.
     *
     * @param string $file File path
     * @param string $oldContent Old content
     * @param string $newContent New content
     * @return string Unified diff
     */
    private function generateUnifiedDiff(string $file, string $oldContent, string $newContent): string
    {
        $relativePath = str_replace($this->projectRoot . '/', '', $file);
        $diff = "--- a/{$relativePath}\n";
        $diff .= "+++ b/{$relativePath}\n";
        
        // Simplified diff - in production use proper diff algorithm
        $oldLines = explode("\n", $oldContent);
        $newLines = explode("\n", $newContent);
        
        $maxLines = max(count($oldLines), count($newLines));
        $diffLines = [];
        
        for ($i = 0; $i < min(3, $maxLines); $i++) {
            $oldLine = $oldLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';
            
            if ($oldLine !== $newLine) {
                if ($oldLine !== '') {
                    $diffLines[] = "-{$oldLine}";
                }
                if ($newLine !== '') {
                    $diffLines[] = "+{$newLine}";
                }
            }
        }
        
        if ($maxLines > 3) {
            $diffLines[] = "... (" . ($maxLines - 3) . " more lines)";
        }
        
        $diff .= "@@ -1," . count($oldLines) . " +1," . count($newLines) . " @@\n";
        $diff .= implode("\n", $diffLines);
        
        return $diff;
    }

    /**
     * Generate diff summary.
     *
     * @param array<array<string, mixed>> $diffs List of diffs
     * @return string Summary text
     */
    private function generateDiffSummary(array $diffs): string
    {
        $summary = "Documentation Changes Summary\n";
        $summary .= str_repeat("=", 80) . "\n\n";

        foreach ($diffs as $diff) {
            $relativePath = str_replace($this->projectRoot . '/', '', $diff['file']);
            $summary .= "📝 {$relativePath}\n";
            $summary .= "   +{$diff['additions']} additions, -{$diff['deletions']} deletions\n\n";
        }

        $totalAdditions = array_sum(array_column($diffs, 'additions'));
        $totalDeletions = array_sum(array_column($diffs, 'deletions'));
        
        $summary .= "Total: {$totalAdditions} additions, {$totalDeletions} deletions across " . count($diffs) . " files\n";

        return $summary;
    }

    /**
     * Write changes to files.
     *
     * @param array<array<string, mixed>> $changes List of changes
     * @return array<string> List of updated file paths
     */
    private function writeChanges(array $changes): array
    {
        $filesUpdated = [];

        foreach ($changes as $change) {
            $filePath = $change['file'];
            
            // Ensure directory exists
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Write new content
            if (file_put_contents($filePath, $change['new_content']) !== false) {
                $filesUpdated[] = str_replace($this->projectRoot . '/', '', $filePath);
            }
        }

        return $filesUpdated;
    }

    /**
     * Format driver list for output.
     *
     * @param array<string, array<string, mixed>> $drivers Driver information
     * @return array<string> List of driver names
     */
    private function formatDriverList(array $drivers): array
    {
        return array_map(
            fn($driver) => $driver['name'] . ' (' . $driver['status'] . ')',
            array_values($drivers)
        );
    }

    /**
     * Display summary of results.
     *
     * @param array<string, mixed> $result Task results
     */
    private function displaySummary(array $result): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "DOCUMENTATION UPDATE SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        echo "✅ Drivers Found: " . count($result['drivers_found']) . "\n";
        foreach ($result['drivers_found'] as $driver) {
            echo "   - {$driver}\n";
        }
        echo "\n";

        echo "📝 Files Updated: " . count($result['files_updated']) . "\n";
        foreach ($result['files_updated'] as $file) {
            echo "   - {$file}\n";
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
