<?php

declare(strict_types=1);

/**
 * Example: Using the Agent Task Registry and Operator Console
 * 
 * This script demonstrates how to use the agent task registry system
 * to load task definitions and request operator approval.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\OperatorConsole;

// 1. Load the task registry
echo "=== Agent Task Registry Example ===\n\n";

try {
    $registry = new TaskRegistry();
    echo "✓ Task registry loaded successfully\n\n";
    
    // 2. List all available tasks
    echo "Available Tasks:\n";
    foreach ($registry->getAllTasks() as $taskId => $task) {
        echo sprintf(
            "  - %s: %s (%s)\n",
            $taskId,
            $task['name'],
            $task['category']
        );
    }
    echo "\n";
    
    // 3. Get tasks by category
    echo "Generation Tasks:\n";
    foreach ($registry->getTasksByCategory('generation') as $taskId => $task) {
        echo "  - {$taskId}: {$task['name']}\n";
    }
    echo "\n";
    
    // 4. Show task details
    $task = $registry->getTask('create-driver');
    echo "Task Details for 'create-driver':\n";
    echo "  Name: {$task['name']}\n";
    echo "  Description: {$task['description']}\n";
    echo "  Category: {$task['category']}\n";
    echo "  Scopes: " . implode(', ', $task['scopes']) . "\n";
    echo "  Requires Approval: " . 
         ($task['safety_flags']['requires_approval'] ? 'Yes' : 'No') . "\n";
    echo "\n";
    
    // 5. Initialize operator console
    $auditLogPath = $registry->getAuditLogPath();
    $console = new OperatorConsole($auditLogPath, false);
    
    // For this example, we'll simulate approval
    $console->setSimulatedApproval(true);
    echo "✓ Operator console initialized\n\n";
    
    // 6. Request approval for a task
    echo "=== Requesting Approval ===\n\n";
    
    $inputs = [
        'driver_name' => 'ethereum',
        'rpc_url' => 'https://mainnet.infura.io',
    ];
    
    $affectedPaths = [
        'src/Drivers/EthereumDriver.php',
        'tests/Drivers/EthereumDriverTest.php',
    ];
    
    // Validate inputs first
    $registry->validateTaskInputs('create-driver', $inputs);
    echo "✓ Task inputs validated\n";
    
    // Validate paths
    $console->validatePaths($registry, 'create-driver', $affectedPaths);
    echo "✓ Affected paths validated\n";
    
    // Request approval
    $approved = $console->requestApproval(
        'create-driver',
        $task,
        $inputs,
        $affectedPaths
    );
    
    if ($approved) {
        echo "✓ Operation APPROVED\n";
        echo "  Task would now execute with:\n";
        echo "    Driver: {$inputs['driver_name']}\n";
        echo "    RPC: {$inputs['rpc_url']}\n";
    } else {
        echo "✗ Operation DENIED\n";
    }
    echo "\n";
    
    // 7. Read audit log
    echo "=== Audit Log ===\n\n";
    $entries = $console->readAuditLog(5);
    echo "Recent audit entries (last 5):\n";
    foreach ($entries as $entry) {
        echo sprintf(
            "  [%s] %s - %s by %s\n",
            $entry['timestamp'],
            $entry['task_id'],
            $entry['outcome'],
            $entry['operator']
        );
    }
    echo "\n";
    
    // 8. Show audit statistics
    $stats = $console->getAuditStats();
    echo "Audit Statistics:\n";
    echo "  Total Operations: {$stats['total_operations']}\n";
    echo "  Approved: {$stats['approved']}\n";
    echo "  Denied: {$stats['denied']}\n";
    echo "\n";
    
    echo "=== Example Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    if ($e instanceof \Blockchain\Exceptions\ValidationException) {
        $errors = $e->getErrors();
        if (!empty($errors)) {
            echo "Validation Errors:\n";
            foreach ($errors as $field => $error) {
                if (is_array($error)) {
                    echo "  {$field}:\n";
                    foreach ($error as $err) {
                        echo "    - {$err}\n";
                    }
                } else {
                    echo "  {$field}: {$error}\n";
                }
            }
        }
    }
    exit(1);
}
