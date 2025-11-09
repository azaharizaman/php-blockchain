#!/usr/bin/env php
<?php

/**
 * Test script to verify RefactorSuggestionsTask execution.
 */

declare(strict_types=1);

// Check if autoload exists, otherwise skip
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "✗ Composer autoload not found. Run 'composer install' first.\n";
    echo "Skipping test execution.\n";
    exit(0);
}

require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\Agent\Tasks\RefactorSuggestionsTask;

echo "Testing RefactorSuggestionsTask...\n\n";

try {
    $task = new RefactorSuggestionsTask();
    
    echo "✓ Task instantiated successfully\n";
    
    // Test with minimal inputs
    $inputs = [
        'analysis_type' => 'complexity',
        'complexity_threshold' => 15,
        'scan_paths' => ['src/Drivers/'],  // Scan just drivers directory
        'risk_threshold' => 'medium',
        'generate_patches' => true,
        'output_format' => 'both',
    ];
    
    echo "Running analysis with inputs:\n";
    echo "  - Analysis type: {$inputs['analysis_type']}\n";
    echo "  - Complexity threshold: {$inputs['complexity_threshold']}\n";
    echo "  - Risk threshold: {$inputs['risk_threshold']}\n";
    echo "  - Scan paths: " . implode(', ', $inputs['scan_paths']) . "\n\n";
    
    $result = $task->execute($inputs);
    
    echo "\n✓ Task executed successfully\n\n";
    
    echo "Results:\n";
    echo "  - Total suggestions: {$result['summary']['total_suggestions']}\n";
    echo "  - By type:\n";
    foreach ($result['summary']['by_type'] as $type => $count) {
        echo "    • " . ucfirst(str_replace('_', ' ', $type)) . ": {$count}\n";
    }
    echo "  - By risk:\n";
    echo "    • High: {$result['summary']['by_risk']['high']}\n";
    echo "    • Medium: {$result['summary']['by_risk']['medium']}\n";
    echo "    • Low: {$result['summary']['by_risk']['low']}\n";
    echo "  - Report: {$result['report_path']}\n";
    echo "  - Duration: {$result['duration']}s\n\n";
    
    if ($result['summary']['total_suggestions'] > 0) {
        echo "Sample suggestion:\n";
        $sample = $result['suggestions'][0];
        echo "  - Title: {$sample['title']}\n";
        echo "  - File: {$sample['file_path']}\n";
        echo "  - Type: {$sample['type']}\n";
        echo "  - Risk: {$sample['risk']}\n\n";
    }
    
    echo "✓ All checks passed!\n";
    
    exit(0);
    
} catch (\Blockchain\Exceptions\ValidationException $e) {
    echo "✗ Validation Error: {$e->getMessage()}\n";
    echo "  Please check your input parameters.\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "✗ Runtime Error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Unexpected Error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
