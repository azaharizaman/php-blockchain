#!/usr/bin/env php
<?php
/**
 * Coverage Threshold Checker
 * 
 * This script checks if the code coverage meets minimum thresholds.
 * Run after generating coverage: composer run test:coverage-clover
 */

// Configuration
$minLineCoverage = 80.0;
$minMethodCoverage = 85.0;
$cloverFile = __DIR__ . '/../coverage/clover.xml';

// Check if coverage file exists
if (!file_exists($cloverFile)) {
    fwrite(STDERR, "Error: Coverage file not found at $cloverFile\n");
    fwrite(STDERR, "Run 'composer run test:coverage-clover' first\n");
    exit(1);
}

// Load and parse coverage data
$xml = simplexml_load_file($cloverFile);
if (!$xml) {
    fwrite(STDERR, "Error: Failed to parse coverage file\n");
    exit(1);
}

// Validate XML structure
if (!isset($xml->project->metrics)) {
    fwrite(STDERR, "Error: Invalid coverage file structure\n");
    exit(1);
}

// Extract metrics
$metrics = $xml->project->metrics;

// Validate required attributes exist
$requiredAttrs = ['statements', 'coveredstatements', 'methods', 'coveredmethods'];
foreach ($requiredAttrs as $attr) {
    if (!isset($metrics[$attr])) {
        fwrite(STDERR, "Error: Missing required metric '$attr' in coverage file\n");
        exit(1);
    }
}

$totalLines = (int)$metrics['statements'];
$coveredLines = (int)$metrics['coveredstatements'];
$totalMethods = (int)$metrics['methods'];
$coveredMethods = (int)$metrics['coveredmethods'];

// Calculate percentages
$lineCoverage = $totalLines > 0 ? ($coveredLines / $totalLines) * 100 : 0;
$methodCoverage = $totalMethods > 0 ? ($coveredMethods / $totalMethods) * 100 : 0;

// Display results
echo "\n";
echo "Coverage Report:\n";
echo "================\n";
echo sprintf("Line Coverage:   %.2f%% (%d/%d lines)\n", $lineCoverage, $coveredLines, $totalLines);
echo sprintf("Method Coverage: %.2f%% (%d/%d methods)\n", $methodCoverage, $coveredMethods, $totalMethods);
echo "\n";
echo "Thresholds:\n";
echo sprintf("Line Coverage:   %.1f%% required\n", $minLineCoverage);
echo sprintf("Method Coverage: %.1f%% required\n", $minMethodCoverage);
echo "\n";

// Check thresholds
$passed = true;
if ($lineCoverage < $minLineCoverage) {
    echo sprintf("✗ Line coverage %.2f%% is below threshold %.1f%%\n", $lineCoverage, $minLineCoverage);
    $passed = false;
}

if ($methodCoverage < $minMethodCoverage) {
    echo sprintf("✗ Method coverage %.2f%% is below threshold %.1f%%\n", $methodCoverage, $minMethodCoverage);
    $passed = false;
}

if ($passed) {
    echo "✓ All coverage thresholds met!\n";
    exit(0);
} else {
    echo "\n";
    echo "Coverage thresholds not met. Please add more tests.\n";
    exit(1);
}
