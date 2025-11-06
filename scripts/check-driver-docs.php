#!/usr/bin/env php
<?php
// scripts/check-driver-docs.php
// Verify each driver in src/Drivers has a corresponding docs/drivers/{driver}.md file

$root = dirname(__DIR__);
$driversDir = $root . '/src/Drivers';
$docsDir = $root . '/docs/drivers';

if (!is_dir($driversDir)) {
    fwrite(STDERR, "Drivers directory not found: $driversDir\n");
    exit(2);
}

$files = glob($driversDir . '/*.php');
$missing = [];
foreach ($files as $file) {
    $base = basename($file, '.php');
    $name = preg_replace('/Driver$/', '', $base);
    $slug = strtolower($name);
    $docPath = $docsDir . '/' . $slug . '.md';

    if (!file_exists($docPath)) {
        $missing[] = [
            'driver' => $base,
            'expected_doc' => $docPath
        ];
    }
}

if (count($missing) > 0) {
    fwrite(STDERR, "Missing driver docs detected:\n");
    foreach ($missing as $m) {
        fwrite(STDERR, sprintf(" - %s -> %s\n", $m['driver'], $m['expected_doc']));
    }
    fwrite(STDERR, "\nPlease add docs under docs/drivers for the above drivers.\n");
    exit(1);
}

echo "All drivers have docs.\n";
exit(0);
