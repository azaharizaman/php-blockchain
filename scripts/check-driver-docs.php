#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Check that all blockchain drivers have documentation.
 *
 * This script verifies that:
 * 1. Each driver in src/Drivers/ has a corresponding docs/drivers/{driver}.md file
 * 2. Documentation files contain required sections
 * 3. Documentation follows the expected structure
 *
 * Usage: php scripts/check-driver-docs.php
 */

$root = dirname(__DIR__);
$driversDir = $root . '/src/Drivers';
$docsDir = $root . '/docs/drivers';

// Required sections that should be present in documentation
$requiredSections = [
    'Overview',
    'Installation',
];

// Validate directories
if (!is_dir($driversDir)) {
    fwrite(STDERR, "Error: Drivers directory not found: $driversDir\n");
    exit(2);
}

if (!is_dir($docsDir)) {
    fwrite(STDERR, "Error: Docs directory not found: $docsDir\n");
    fwrite(STDERR, "Run 'composer run generate-docs' to create documentation.\n");
    exit(2);
}

// Find all driver files
$files = glob($driversDir . '/*.php');
if ($files === false) {
    fwrite(STDERR, "Error: Failed to read drivers directory\n");
    exit(2);
}

$missing = [];
$incomplete = [];
$valid = [];

foreach ($files as $file) {
    $base = basename($file, '.php');
    $name = preg_replace('/Driver$/', '', $base);
    $slug = strtolower($name);
    $docPath = $docsDir . '/' . $slug . '.md';

    // Check if documentation exists
    if (!file_exists($docPath)) {
        $missing[] = [
            'driver' => $base,
            'expected_doc' => $docPath,
            'name' => $name,
        ];
        continue;
    }

    // Read and validate documentation content
    $content = file_get_contents($docPath);
    if ($content === false) {
        fwrite(STDERR, "Warning: Could not read doc file: $docPath\n");
        continue;
    }

    // Check for required sections
    $missingSections = [];
    foreach ($requiredSections as $section) {
        // Look for markdown headers with the section name
        if (!preg_match('/^##?\s+' . preg_quote($section, '/') . '/mi', $content)) {
            $missingSections[] = $section;
        }
    }

    if (count($missingSections) > 0) {
        $incomplete[] = [
            'driver' => $base,
            'doc_path' => $docPath,
            'missing_sections' => $missingSections,
        ];
    } else {
        $valid[] = $base;
    }
}

// Report results
$hasErrors = false;

if (count($missing) > 0) {
    $hasErrors = true;
    fwrite(STDERR, "✗ Missing driver documentation:\n");
    foreach ($missing as $m) {
        fwrite(STDERR, sprintf("  - %s (expected: %s)\n", $m['driver'], basename($m['expected_doc'])));
    }
    fwrite(STDERR, "\n  Run 'composer run generate-docs' to create missing documentation.\n\n");
}

if (count($incomplete) > 0) {
    $hasErrors = true;
    fwrite(STDERR, "✗ Incomplete driver documentation:\n");
    foreach ($incomplete as $inc) {
        fwrite(STDERR, sprintf("  - %s (missing sections: %s)\n", 
            $inc['driver'], 
            implode(', ', $inc['missing_sections'])
        ));
    }
    fwrite(STDERR, "\n  Please add the required sections to the above documentation files.\n\n");
}

if (count($valid) > 0) {
    echo "✓ Valid driver documentation:\n";
    foreach ($valid as $driver) {
        echo "  - $driver\n";
    }
    echo "\n";
}

// Exit with appropriate code
if ($hasErrors) {
    fwrite(STDERR, "Documentation check failed. Please fix the issues above.\n");
    exit(1);
}

echo "✓ All drivers have complete documentation.\n";
exit(0);
