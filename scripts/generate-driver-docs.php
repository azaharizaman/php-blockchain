#!/usr/bin/env php
<?php
// scripts/generate-driver-docs.php
// Generate docs/drivers/{driver}.md stubs for each driver in src/Drivers

$root = dirname(__DIR__);
$driversDir = $root . '/src/Drivers';
$docsDir = $root . '/docs/drivers';

if (!is_dir($driversDir)) {
    fwrite(STDERR, "Drivers directory not found: $driversDir\n");
    exit(2);
}

if (!is_dir($docsDir)) {
    if (!mkdir($docsDir, 0755, true)) {
        fwrite(STDERR, "Failed to create docs directory: $docsDir\n");
        exit(2);
    }
}

$files = glob($driversDir . '/*.php');
$created = [];
foreach ($files as $file) {
    $base = basename($file, '.php');
    // Derive a human-friendly name
    $name = preg_replace('/Driver$/', '', $base);
    $slug = strtolower($name);
    $docPath = $docsDir . '/' . $slug . '.md';

    if (file_exists($docPath)) {
        continue;
    }

    $content = "# $name Driver\n\n";
    $content .= "This document provides a short overview and usage examples for the $name driver.\n\n";
    $content .= "## Overview\n\n";
    $content .= "- Class: `$base`\n";
    $content .= "- Location: `src/Drivers/$base.php`\n\n";
    $content .= "## Usage example\n\n";
    $content .= "```php\n";
    $content .= "// Example usage\n";
    $content .= "use Blockchain\\BlockchainManager;\n\n";
    $content .= "\$manager = new BlockchainManager('$slug', ['endpoint' => 'https://example-rpc']);\n";
    $content .= "\$balance = \$manager->getBalance('address_here');\n";
    $content .= "```\n\n";
    $content .= "## Testing\n\nAdd PHPUnit tests under `tests/` using Guzzle MockHandler.\n\n";
    $content .= "## Caveats\n\n- Do not commit private keys or `.env` files.\n- Integration tests should run against testnets only and be gated by operator controls.\n";

    if (false === file_put_contents($docPath, $content)) {
        fwrite(STDERR, "Failed to write doc file: $docPath\n");
        continue;
    }

    $created[] = $docPath;
}

if (count($created) > 0) {
    echo "Created docs:\n";
    foreach ($created as $p) {
        echo " - $p\n";
    }
} else {
    echo "No new driver docs were created.\n";
}

exit(0);
