#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate documentation for blockchain drivers.
 *
 * This script scans src/Drivers/ for driver classes and generates
 * documentation stubs in docs/drivers/{driver}.md using the template
 * from docs/templates/driver-template.md.
 *
 * Usage: php scripts/generate-driver-docs.php
 */

$root = dirname(__DIR__);
$driversDir = $root . '/src/Drivers';
$docsDir = $root . '/docs/drivers';
$templatePath = $root . '/docs/templates/driver-template.md';

// Validate directories
if (!is_dir($driversDir)) {
    fwrite(STDERR, "Error: Drivers directory not found: $driversDir\n");
    exit(2);
}

if (!is_dir($docsDir)) {
    if (!mkdir($docsDir, 0755, true)) {
        fwrite(STDERR, "Error: Failed to create docs directory: $docsDir\n");
        exit(2);
    }
}

// Load template if it exists
$template = null;
if (file_exists($templatePath)) {
    $template = file_get_contents($templatePath);
    if ($template === false) {
        fwrite(STDERR, "Warning: Could not read template file: $templatePath\n");
        $template = null;
    }
}

// Extract interface methods for documentation
$interfacePath = $root . '/src/Contracts/BlockchainDriverInterface.php';
$interfaceMethods = [];
if (file_exists($interfacePath)) {
    $interfaceContent = file_get_contents($interfacePath);
    if ($interfaceContent !== false) {
        // Extract method signatures
        if (preg_match_all('/public function (\w+)\(([^;]*)\):\s*([^\s;]+);/s', $interfaceContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $interfaceMethods[$match[1]] = [
                    'name' => $match[1],
                    'params' => $match[2],
                    'return' => $match[3],
                ];
            }
        }
    }
}

// Find and process driver files
$files = glob($driversDir . '/*.php');
if ($files === false) {
    fwrite(STDERR, "Error: Failed to read drivers directory\n");
    exit(2);
}

$created = [];
$skipped = [];

foreach ($files as $file) {
    $base = basename($file, '.php');
    
    // Derive a human-friendly name
    $name = preg_replace('/Driver$/', '', $base);
    $slug = strtolower($name);
    $docPath = $docsDir . '/' . $slug . '.md';

    // Skip if documentation already exists
    if (file_exists($docPath)) {
        $skipped[] = $docPath;
        continue;
    }

    // Generate documentation content
    if ($template !== null) {
        // Use template with placeholders
        $content = generateFromTemplate($template, $name, $base, $slug, $interfaceMethods);
    } else {
        // Fallback to basic documentation
        $content = generateBasicDoc($name, $base, $slug, $interfaceMethods);
    }

    // Write documentation file
    if (file_put_contents($docPath, $content) === false) {
        fwrite(STDERR, "Error: Failed to write doc file: $docPath\n");
        continue;
    }

    $created[] = $docPath;
}

// Report results
if (count($created) > 0) {
    echo "✓ Created documentation:\n";
    foreach ($created as $p) {
        echo "  - " . basename($p) . "\n";
    }
}

if (count($skipped) > 0) {
    echo "\n✓ Skipped existing documentation:\n";
    foreach ($skipped as $p) {
        echo "  - " . basename($p) . "\n";
    }
}

if (count($created) === 0 && count($skipped) === 0) {
    echo "No driver files found in $driversDir\n";
}

echo "\nDocumentation generation complete.\n";
exit(0);

/**
 * Generate documentation from template.
 *
 * @param string $template Template content
 * @param string $name Driver name (e.g., "Ethereum")
 * @param string $base Class name (e.g., "EthereumDriver")
 * @param string $slug Lowercase name (e.g., "ethereum")
 * @param array<string,array<string,string>> $methods Interface methods
 *
 * @return string Generated documentation
 */
function generateFromTemplate(string $template, string $name, string $base, string $slug, array $methods): string
{
    $replacements = [
        '{{driver_name}}' => $name,
        '{{driver_class}}' => $base,
        '{{driver_lowercase}}' => $slug,
        '{{blockchain_name}}' => $name,
        '{{network_type}}' => 'TODO: Specify network type',
        '{{native_currency}}' => 'TODO: Specify native currency',
        '{{decimals}}' => 'TODO: Specify decimal places',
        '{{default_endpoint}}' => 'https://example-rpc.com',
        '{{official_docs_url}}' => 'TODO: Add official documentation URL',
        '{{rpc_docs_url}}' => 'TODO: Add RPC API documentation URL',
        '{{block_explorer_url}}' => 'TODO: Add block explorer URL',
    ];

    $content = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    return $content;
}

/**
 * Generate basic documentation without template.
 *
 * @param string $name Driver name
 * @param string $base Class name
 * @param string $slug Lowercase name
 * @param array<string,array<string,string>> $methods Interface methods
 *
 * @return string Generated documentation
 */
function generateBasicDoc(string $name, string $base, string $slug, array $methods): string
{
    $content = "# $name Driver\n\n";
    $content .= "This document provides a short overview and usage examples for the $name driver.\n\n";
    $content .= "## Overview\n\n";
    $content .= "- Class: `$base`\n";
    $content .= "- Location: `src/Drivers/$base.php`\n\n";
    $content .= "## Installation\n\n";
    $content .= "```bash\n";
    $content .= "composer require azaharizaman/php-blockchain\n";
    $content .= "```\n\n";
    $content .= "## Basic Usage\n\n";
    $content .= "```php\n";
    $content .= "use Blockchain\\BlockchainManager;\n\n";
    $content .= "\$blockchain = new BlockchainManager('$slug', [\n";
    $content .= "    'endpoint' => 'https://example-rpc.com',\n";
    $content .= "    'timeout' => 30\n";
    $content .= "]);\n\n";
    $content .= "\$balance = \$blockchain->getBalance('your_address');\n";
    $content .= "```\n\n";
    
    // Add available methods section
    if (count($methods) > 0) {
        $content .= "## Available Methods\n\n";
        $content .= "The $name driver implements the BlockchainDriverInterface:\n\n";
        foreach ($methods as $method) {
            $content .= "### `{$method['name']}()`\n\n";
            $content .= "```php\n";
            $content .= "public function {$method['name']}({$method['params']}): {$method['return']}\n";
            $content .= "```\n\n";
        }
    }
    
    $content .= "## Testing\n\n";
    $content .= "Run tests for the $name driver:\n\n";
    $content .= "```bash\n";
    $content .= "vendor/bin/phpunit tests/Drivers/{$base}Test.php\n";
    $content .= "```\n\n";
    $content .= "## Security\n\n";
    $content .= "- Do not commit private keys or `.env` files\n";
    $content .= "- Integration tests should run against testnets only\n";
    $content .= "- Use environment variables for sensitive configuration\n\n";
    $content .= "## Contributing\n\n";
    $content .= "See [CONTRIBUTING.md](../../CONTRIBUTING.md) for contribution guidelines.\n\n";
    $content .= "## License\n\n";
    $content .= "MIT License. See [LICENSE](../../LICENSE) for details.\n";

    return $content;
}
