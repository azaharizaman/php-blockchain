<?php

declare(strict_types=1);

namespace Blockchain\Agent;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\ValidationException;

/**
 * TaskRegistry loads and manages agent task definitions from YAML configuration.
 *
 * This class provides centralized access to task metadata, safety flags, and
 * operation specifications for the agent automation system.
 */
class TaskRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $tasks = [];

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * Path to the registry YAML file.
     */
    private string $registryPath;

    /**
     * Create a new TaskRegistry instance.
     *
     * @param string|null $registryPath Path to registry.yaml file. If null, uses default path.
     * @throws ConfigurationException If registry file cannot be loaded
     */
    public function __construct(?string $registryPath = null)
    {
        $this->registryPath = $registryPath ?? $this->getDefaultRegistryPath();
        $this->loadRegistry();
    }

    /**
     * Get default registry path relative to project root.
     *
     * @return string
     */
    private function getDefaultRegistryPath(): string
    {
        // Assuming this is called from tools/agent/, go up to project root
        $projectRoot = dirname(__DIR__, 2);
        return $projectRoot . '/.copilot/tasks/registry.yaml';
    }

    /**
     * Load task definitions from the registry YAML file.
     *
     * @return void
     * @throws ConfigurationException If file not found or invalid YAML
     */
    private function loadRegistry(): void
    {
        if (!file_exists($this->registryPath)) {
            throw new ConfigurationException(
                "Task registry file not found: {$this->registryPath}"
            );
        }

        $content = file_get_contents($this->registryPath);
        if ($content === false) {
            throw new ConfigurationException(
                "Failed to read task registry file: {$this->registryPath}"
            );
        }

        $data = yaml_parse($content);
        if ($data === false) {
            throw new ConfigurationException(
                "Failed to parse task registry YAML: {$this->registryPath}"
            );
        }

        if (!is_array($data)) {
            throw new ConfigurationException(
                "Task registry must contain a valid YAML structure"
            );
        }

        if (!isset($data['tasks']) || !is_array($data['tasks'])) {
            throw new ConfigurationException(
                "Task registry must contain a 'tasks' section"
            );
        }

        $this->tasks = $data['tasks'];
        $this->metadata = $data['metadata'] ?? [];

        $this->validateTasks();
    }

    /**
     * Validate loaded task definitions for integrity.
     *
     * @return void
     * @throws ValidationException If task definitions are invalid
     */
    private function validateTasks(): void
    {
        $errors = [];

        foreach ($this->tasks as $taskId => $task) {
            if (!isset($task['id'])) {
                $errors[$taskId][] = "Missing required field: 'id'";
            }

            if (!isset($task['name'])) {
                $errors[$taskId][] = "Missing required field: 'name'";
            }

            if (!isset($task['description'])) {
                $errors[$taskId][] = "Missing required field: 'description'";
            }

            if (!isset($task['category'])) {
                $errors[$taskId][] = "Missing required field: 'category'";
            }

            if (!isset($task['scopes']) || !is_array($task['scopes'])) {
                $errors[$taskId][] = "Missing or invalid 'scopes' field";
            }

            if (isset($task['safety_flags']) && !is_array($task['safety_flags'])) {
                $errors[$taskId][] = "Field 'safety_flags' must be an array";
            }

            // Validate task ID matches key
            if (isset($task['id']) && $task['id'] !== $taskId) {
                $errors[$taskId][] = "Task ID '{$task['id']}' does not match key '{$taskId}'";
            }
        }

        if (!empty($errors)) {
            $exception = new ValidationException(
                "Task registry validation failed"
            );
            $exception->setErrors($errors);
            throw $exception;
        }
    }

    /**
     * Get a task definition by ID.
     *
     * @param string $taskId Task identifier
     * @return array<string, mixed> Task definition
     * @throws ValidationException If task not found
     */
    public function getTask(string $taskId): array
    {
        if (!$this->hasTask($taskId)) {
            throw new ValidationException(
                "Task '{$taskId}' not found in registry"
            );
        }

        return $this->tasks[$taskId];
    }

    /**
     * Check if a task exists in the registry.
     *
     * @param string $taskId Task identifier
     * @return bool
     */
    public function hasTask(string $taskId): bool
    {
        return isset($this->tasks[$taskId]);
    }

    /**
     * Get all registered tasks.
     *
     * @return array<string, array<string, mixed>> All task definitions
     */
    public function getAllTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get task IDs only.
     *
     * @return array<int, string> List of task IDs
     */
    public function getTaskIds(): array
    {
        return array_keys($this->tasks);
    }

    /**
     * Get tasks by category.
     *
     * @param string $category Category name (e.g., 'generation', 'testing')
     * @return array<string, array<string, mixed>> Tasks in the specified category
     */
    public function getTasksByCategory(string $category): array
    {
        return array_filter(
            $this->tasks,
            fn(array $task): bool => ($task['category'] ?? '') === $category
        );
    }

    /**
     * Get tasks that require operator approval.
     *
     * @return array<string, array<string, mixed>> Tasks requiring approval
     */
    public function getTasksRequiringApproval(): array
    {
        return array_filter(
            $this->tasks,
            fn(array $task): bool =>
                ($task['safety_flags']['requires_approval'] ?? false) === true
        );
    }

    /**
     * Get tasks by scope.
     *
     * @param string $scope Scope identifier (e.g., 'filesystem:write')
     * @return array<string, array<string, mixed>> Tasks with the specified scope
     */
    public function getTasksByScope(string $scope): array
    {
        return array_filter(
            $this->tasks,
            fn(array $task): bool =>
                in_array($scope, $task['scopes'] ?? [], true)
        );
    }

    /**
     * Get registry metadata.
     *
     * @return array<string, mixed> Metadata information
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the path to the audit log file.
     *
     * @return string
     */
    public function getAuditLogPath(): string
    {
        if (isset($this->metadata['audit_log_path'])) {
            $path = $this->metadata['audit_log_path'];
            // Make path absolute if relative
            if (!str_starts_with($path, '/')) {
                $projectRoot = dirname(__DIR__, 2);
                return $projectRoot . '/' . $path;
            }
            return $path;
        }

        // Default fallback
        $projectRoot = dirname(__DIR__, 2);
        return $projectRoot . '/storage/agent-audit.log';
    }

    /**
     * Get default safety flags from metadata.
     *
     * @return array<string, mixed> Default safety configuration
     */
    public function getDefaultSafetyFlags(): array
    {
        return $this->metadata['default_safety_flags'] ?? [];
    }

    /**
     * Validate task input parameters against task definition.
     *
     * @param string $taskId Task identifier
     * @param array<string, mixed> $inputs Input parameters to validate
     * @return bool True if valid
     * @throws ValidationException If validation fails
     */
    public function validateTaskInputs(string $taskId, array $inputs): bool
    {
        $task = $this->getTask($taskId);
        $errors = [];

        if (!isset($task['inputs']) || !is_array($task['inputs'])) {
            return true; // No input validation required
        }

        foreach ($task['inputs'] as $inputDef) {
            $name = $inputDef['name'] ?? '';
            $required = $inputDef['required'] ?? false;
            $type = $inputDef['type'] ?? 'string';

            if ($required && !isset($inputs[$name])) {
                $errors[$name] = "Required input '{$name}' is missing";
                continue;
            }

            if (isset($inputs[$name])) {
                $value = $inputs[$name];
                
                // Type validation
                $valid = match ($type) {
                    'string' => is_string($value),
                    'integer' => is_int($value),
                    'boolean' => is_bool($value),
                    'array' => is_array($value),
                    'object' => is_array($value) || is_object($value),
                    default => true,
                };

                if (!$valid) {
                    $errors[$name] = "Input '{$name}' must be of type {$type}";
                }

                // Regex validation if specified
                if (
                    isset($inputDef['validation']) &&
                    is_string($value)
                ) {
                    // Use # as delimiter to avoid conflicts with /
                    $pattern = '#' . str_replace('#', '\\#', $inputDef['validation']) . '#';
                    $matchResult = @preg_match($pattern, $value);
                    if ($matchResult === false) {
                        $errors[$name] = "Input '{$name}' has an invalid validation pattern";
                    } elseif ($matchResult === 0) {
                        $errors[$name] = "Input '{$name}' does not match validation pattern";
                    }
                }
            }
        }

        if (!empty($errors)) {
            $exception = new ValidationException(
                "Task input validation failed for '{$taskId}'"
            );
            $exception->setErrors($errors);
            throw $exception;
        }

        return true;
    }

    /**
     * Check if a file path is allowed for a task.
     *
     * @param string $taskId Task identifier
     * @param string $filePath Path to check
     * @return bool True if allowed
     */
    public function isPathAllowed(string $taskId, string $filePath): bool
    {
        $task = $this->getTask($taskId);
        $safetyFlags = $task['safety_flags'] ?? [];
        
        // Normalize the file path to prevent traversal attacks
        // Remove any . or .. components and resolve to canonical path
        $normalizedPath = $this->normalizePath($filePath);
        
        // Check deny patterns first
        $denyPatterns = array_merge(
            $safetyFlags['deny_patterns'] ?? [],
            $this->getDefaultSafetyFlags()['deny_patterns'] ?? []
        );

        foreach ($denyPatterns as $pattern) {
            // Convert glob pattern to regex
            $regex = $this->globToRegex($pattern);
            if (preg_match($regex, $normalizedPath)) {
                return false;
            }
        }

        // Check allowed paths
        $allowedPaths = $safetyFlags['allowed_paths'] ?? [];
        if (empty($allowedPaths)) {
            return false; // No paths allowed if not specified
        }

        foreach ($allowedPaths as $allowedPath) {
            // Normalize allowed path as well
            $normalizedAllowed = $this->normalizePath($allowedPath);
            if (str_starts_with($normalizedPath, $normalizedAllowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a file path to prevent traversal attacks.
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizePath(string $path): string
    {
        // Split path into components
        $parts = explode('/', $path);
        $normalized = [];
        
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue; // Skip empty and current directory
            }
            if ($part === '..') {
                // Go up one directory if possible
                if (!empty($normalized)) {
                    array_pop($normalized);
                }
            } else {
                $normalized[] = $part;
            }
        }
        
        return implode('/', $normalized);
    }

    /**
     * Convert glob pattern to regex.
     *
     * @param string $pattern Glob pattern
     * @return string Regex pattern
     */
    private function globToRegex(string $pattern): string
    {
        // Replace glob wildcards with placeholders first
        $pattern = str_replace(['*', '?'], ['__GLOB_STAR__', '__GLOB_QMARK__'], $pattern);
        // Escape regex special characters
        $pattern = preg_quote($pattern, '/');
        // Replace placeholders with regex equivalents
        $pattern = str_replace(['__GLOB_STAR__', '__GLOB_QMARK__'], ['.*', '.'], $pattern);
        return '/^' . $pattern . '$/';
    }
}
