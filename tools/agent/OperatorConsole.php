<?php

declare(strict_types=1);

namespace Blockchain\Agent;

use Blockchain\Exceptions\ValidationException;

/**
 * OperatorConsole manages operator approval workflow and audit logging.
 *
 * This class prompts operators for approval before executing privileged operations
 * and maintains a comprehensive audit trail of all decisions.
 */
class OperatorConsole
{
    /**
     * Path to the audit log file.
     */
    private string $auditLogPath;

    /**
     * Whether to require interactive approval (can be disabled for testing).
     */
    private bool $requireInteractiveApproval;

    /**
     * Simulated approval response for testing (null means use interactive mode).
     */
    private ?bool $simulatedApproval = null;

    /**
     * Create a new OperatorConsole instance.
     *
     * @param string $auditLogPath Path to audit log file
     * @param bool $requireInteractiveApproval Whether to prompt for approval interactively
     */
    public function __construct(
        string $auditLogPath,
        bool $requireInteractiveApproval = true
    ) {
        $this->auditLogPath = $auditLogPath;
        $this->requireInteractiveApproval = $requireInteractiveApproval;
        
        // Ensure audit log directory exists
        $this->ensureAuditLogDirectory();
    }

    /**
     * Ensure the audit log directory exists.
     */
    private function ensureAuditLogDirectory(): void
    {
        $directory = dirname($this->auditLogPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Request operator approval for a task operation.
     *
     * @param string $taskId Task identifier
     * @param array<string, mixed> $task Task definition
     * @param array<string, mixed> $inputs Task inputs
     * @param array<string> $affectedPaths Paths that will be modified
     * @return bool True if approved, false if denied
     */
    public function requestApproval(
        string $taskId,
        array $task,
        array $inputs,
        array $affectedPaths = []
    ): bool {
        // Check if approval is required
        $requiresApproval = $task['safety_flags']['requires_approval'] ?? true;
        
        if (!$requiresApproval) {
            $this->logOperation($taskId, 'auto-approved', true, $inputs, $affectedPaths);
            return true;
        }

        // Use simulated approval for testing
        if ($this->simulatedApproval !== null) {
            $approved = $this->simulatedApproval;
            $operator = 'test-operator';
            $this->logOperation($taskId, $operator, $approved, $inputs, $affectedPaths);
            return $approved;
        }

        // Interactive approval
        if ($this->requireInteractiveApproval) {
            $approved = $this->promptForApproval($taskId, $task, $inputs, $affectedPaths);
            $operator = $this->getCurrentOperator();
            $this->logOperation($taskId, $operator, $approved, $inputs, $affectedPaths);
            return $approved;
        }

        // Default: auto-deny if interactive not available
        $this->logOperation($taskId, 'system', false, $inputs, $affectedPaths, 'No operator available');
        return false;
    }

    /**
     * Prompt the operator for approval interactively.
     *
     * @param string $taskId Task identifier
     * @param array<string, mixed> $task Task definition
     * @param array<string, mixed> $inputs Task inputs
     * @param array<string> $affectedPaths Paths that will be modified
     * @return bool True if approved
     */
    private function promptForApproval(
        string $taskId,
        array $task,
        array $inputs,
        array $affectedPaths
    ): bool {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "OPERATOR APPROVAL REQUIRED\n";
        echo str_repeat("=", 70) . "\n\n";
        
        echo "Task ID: {$taskId}\n";
        echo "Task Name: " . ($task['name'] ?? 'Unknown') . "\n";
        echo "Description: " . ($task['description'] ?? 'No description') . "\n";
        echo "Category: " . ($task['category'] ?? 'unknown') . "\n\n";

        if (!empty($task['scopes'])) {
            echo "Required Scopes:\n";
            foreach ($task['scopes'] as $scope) {
                echo "  - {$scope}\n";
            }
            echo "\n";
        }

        if (!empty($inputs)) {
            echo "Task Inputs:\n";
            foreach ($inputs as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                if (strlen($displayValue) > 50) {
                    $displayValue = substr($displayValue, 0, 47) . '...';
                }
                echo "  {$key}: {$displayValue}\n";
            }
            echo "\n";
        }

        if (!empty($affectedPaths)) {
            echo "Affected Paths:\n";
            foreach ($affectedPaths as $path) {
                echo "  - {$path}\n";
            }
            echo "\n";
        }

        echo str_repeat("-", 70) . "\n";
        echo "Do you approve this operation? [y/N]: ";
        
        $handle = fopen("php://stdin", "r");
        if ($handle === false) {
            return false;
        }
        
        $line = fgets($handle);
        fclose($handle);
        
        $response = trim(strtolower($line ?: ''));
        return $response === 'y' || $response === 'yes';
    }

    /**
     * Get the current operator name.
     */
    private function getCurrentOperator(): string
    {
        // Try to get from environment
        $operator = getenv('USER') ?: getenv('USERNAME') ?: 'unknown';
        
        // Add hostname if available
        $hostname = gethostname();
        if ($hostname !== false) {
            $operator .= "@{$hostname}";
        }
        
        return $operator;
    }

    /**
     * Log an operation to the audit trail.
     *
     * @param string $taskId Task identifier
     * @param string $operator Operator who approved/denied
     * @param bool $approved Whether operation was approved
     * @param array<string, mixed> $inputs Task inputs
     * @param array<string> $affectedPaths Paths that were/would be modified
     * @param string|null $notes Additional notes or error messages
     */
    private function logOperation(
        string $taskId,
        string $operator,
        bool $approved,
        array $inputs,
        array $affectedPaths,
        ?string $notes = null
    ): void {
        $timestamp = date('Y-m-d H:i:s T');
        $outcome = $approved ? 'APPROVED' : 'DENIED';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'task_id' => $taskId,
            'operator' => $operator,
            'outcome' => $outcome,
            'inputs' => $inputs,
            'affected_paths' => $affectedPaths,
            'notes' => $notes,
        ];

        $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n";
        
        // Append to audit log
        file_put_contents($this->auditLogPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Validate file paths against task allowed paths.
     *
     * @param TaskRegistry $registry Task registry
     * @param string $taskId Task identifier
     * @param array<string> $filePaths Paths to validate
     * @throws ValidationException If any path is not allowed
     */
    public function validatePaths(
        TaskRegistry $registry,
        string $taskId,
        array $filePaths
    ): void {
        $deniedPaths = [];

        foreach ($filePaths as $path) {
            if (!$registry->isPathAllowed($taskId, $path)) {
                $deniedPaths[] = $path;
            }
        }

        if (!empty($deniedPaths)) {
            $exception = new ValidationException(
                "Task '{$taskId}' does not have permission to access the following paths"
            );
            $exception->setErrors(['denied_paths' => $deniedPaths]);
            throw $exception;
        }
    }

    /**
     * Read audit log entries.
     *
     * @param int|null $limit Maximum number of entries to return (most recent first)
     * @param string|null $taskId Filter by task ID
     * @param string|null $operator Filter by operator
     * @return array<int, array<string, mixed>> Audit log entries
     */
    public function readAuditLog(
        ?int $limit = null,
        ?string $taskId = null,
        ?string $operator = null
    ): array {
        if (!file_exists($this->auditLogPath)) {
            return [];
        }

        $lines = file($this->auditLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }

            // Apply filters
            if ($taskId !== null && ($entry['task_id'] ?? '') !== $taskId) {
                continue;
            }
            if ($operator !== null && ($entry['operator'] ?? '') !== $operator) {
                continue;
            }

            $entries[] = $entry;

            // Check limit
            if ($limit !== null && count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Get statistics from audit log.
     *
     * @return array<string, mixed> Audit statistics
     */
    public function getAuditStats(): array
    {
        $entries = $this->readAuditLog();
        
        $stats = [
            'total_operations' => count($entries),
            'approved' => 0,
            'denied' => 0,
            'by_task' => [],
            'by_operator' => [],
        ];

        foreach ($entries as $entry) {
            $outcome = $entry['outcome'] ?? 'UNKNOWN';
            if ($outcome === 'APPROVED') {
                $stats['approved']++;
            } elseif ($outcome === 'DENIED') {
                $stats['denied']++;
            }

            $taskId = $entry['task_id'] ?? 'unknown';
            $stats['by_task'][$taskId] = ($stats['by_task'][$taskId] ?? 0) + 1;

            $operator = $entry['operator'] ?? 'unknown';
            $stats['by_operator'][$operator] = ($stats['by_operator'][$operator] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Set simulated approval for testing.
     *
     * @param bool|null $approval True to approve, false to deny, null to use interactive mode
     */
    public function setSimulatedApproval(?bool $approval): void
    {
        $this->simulatedApproval = $approval;
    }

    /**
     * Clear the audit log (for testing purposes only).
     */
    public function clearAuditLog(): void
    {
        if (file_exists($this->auditLogPath)) {
            file_put_contents($this->auditLogPath, '');
        }
    }
}
