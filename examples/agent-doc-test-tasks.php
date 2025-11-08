<?php

/**
 * Example: Using UpdateReadmeTask and TestDriverTask
 * 
 * This script demonstrates how to use the documentation and testing automation tasks.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\Agent\Tasks\UpdateReadmeTask;
use Blockchain\Agent\Tasks\TestDriverTask;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\OperatorConsole;

echo "=== PHP Blockchain Agent Tasks Demo ===\n\n";

// Example 1: Preview documentation updates
echo "1. Previewing documentation updates...\n";
echo str_repeat("-", 80) . "\n";

$updateTask = new UpdateReadmeTask();

try {
    $result = $updateTask->execute([
        'update_type' => 'drivers',
        'preview_only' => true,
    ]);
    
    echo "\n✅ Preview generated successfully!\n";
    echo "Drivers found: " . count($result['drivers_found']) . "\n";
    echo "Changes proposed: " . ($result['changes_proposed'] ?? 0) . "\n\n";
    
    if (isset($result['drivers_found'])) {
        echo "Discovered drivers:\n";
        foreach ($result['drivers_found'] as $driver) {
            echo "  - {$driver}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Example 2: Update specific driver documentation
echo "2. Updating specific driver documentation...\n";
echo str_repeat("-", 80) . "\n";

try {
    $result = $updateTask->execute([
        'update_type' => 'drivers',
        'driver_names' => ['Solana'],
        'preview_only' => true,  // Set to false to actually write changes
    ]);
    
    echo "\n✅ Documentation update preview completed!\n";
    
    if (isset($result['preview_diff'])) {
        echo "\nPreview of changes:\n";
        echo substr($result['preview_diff'], 0, 500) . "...\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Example 3: Test a specific driver
echo "3. Testing blockchain driver...\n";
echo str_repeat("-", 80) . "\n";

$testTask = new TestDriverTask();

try {
    $result = $testTask->execute([
        'driver_name' => 'Solana',
        'test_type' => 'unit',
        'coverage' => false,
        'verbose' => false,
    ]);
    
    echo "\n✅ Test execution completed!\n";
    
    if (isset($result['test_results'])) {
        $testResults = $result['test_results'];
        echo "\nTest Results:\n";
        echo "  Status: " . ($testResults['passed'] ? '✅ PASSED' : '❌ FAILED') . "\n";
        echo "  Tests: {$testResults['tests_run']}\n";
        echo "  Assertions: {$testResults['assertions']}\n";
        echo "  Failures: {$testResults['failures']}\n";
        echo "  Errors: {$testResults['errors']}\n";
        echo "  Time: {$testResults['time']}s\n";
    }
    
    if (!empty($result['failure_details'])) {
        echo "\n❌ Failed tests:\n";
        foreach ($result['failure_details'] as $failure) {
            echo "  - {$failure['test']}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Note: PHPUnit may not be available or driver tests may not exist.\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Example 4: Test with coverage
echo "4. Testing with code coverage...\n";
echo str_repeat("-", 80) . "\n";

try {
    $result = $testTask->execute([
        'driver_name' => 'Solana',
        'test_type' => 'unit',
        'coverage' => true,
        'filter' => 'testConnect',  // Run only specific test
    ]);
    
    echo "\n✅ Test with coverage completed!\n";
    
    if (isset($result['coverage'])) {
        $coverage = $result['coverage'];
        echo "\nCoverage Report:\n";
        echo "  Lines: {$coverage['percentage']}%\n";
        echo "  Covered: {$coverage['lines_covered']}/{$coverage['lines_total']}\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Note: Coverage generation may require additional PHP extensions.\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// Example 5: Update documentation with changelog
echo "5. Updating documentation with changelog entry...\n";
echo str_repeat("-", 80) . "\n";

try {
    $result = $updateTask->execute([
        'update_type' => 'all',
        'changelog_entry' => 'Added automated documentation and testing tasks',
        'preview_only' => true,
    ]);
    
    echo "\n✅ Documentation update with changelog preview completed!\n";
    echo "Files that would be updated: " . count($result['files_updated'] ?? []) . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

echo "Demo completed! 🎉\n";
echo "\nFor actual use:\n";
echo "  - Remove 'preview_only' => true to write documentation changes\n";
echo "  - Ensure PHPUnit is installed via 'composer install'\n";
echo "  - Driver tests must exist in tests/Drivers/ directory\n";
