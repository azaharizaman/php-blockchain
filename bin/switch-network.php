#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Network Profile Switcher CLI Tool
 *
 * This CLI utility helps developers quickly switch between network profiles
 * and output configuration for local development.
 *
 * Usage:
 *   php bin/switch-network.php <profile-name> [options]
 *
 * Options:
 *   --output=<path>    Write configuration to the specified file
 *   --dry-run          Print configuration to stdout only (default: true)
 *   --format=<format>  Output format: json|php|env (default: json)
 *   --force            Overwrite existing file without confirmation
 *   --help             Show this help message
 *
 * Examples:
 *   # Display Solana mainnet configuration in JSON format
 *   php bin/switch-network.php solana.mainnet
 *
 *   # Display Ethereum mainnet configuration in PHP array format
 *   php bin/switch-network.php ethereum.mainnet --format=php
 *
 *   # Display configuration in ENV format
 *   php bin/switch-network.php ethereum.localhost --format=env
 *
 *   # Write configuration to file
 *   php bin/switch-network.php solana.devnet --output=config/active.json
 *
 *   # Write with force (no confirmation)
 *   php bin/switch-network.php ethereum.sepolia --output=config/network.php --format=php --force
 *
 * Exit Codes:
 *   0 - Success
 *   1 - Error (invalid profile, write failure, etc.)
 */

// Load Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaderFound = false;
foreach ($autoloadPaths as $file) {
    if (file_exists($file)) {
        require $file;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Error: Composer autoloader not found. Run 'composer install' first.\n");
    exit(1);
}

use Blockchain\Config\NetworkProfiles;

/**
 * Display usage information
 */
function showUsage(): void
{
    global $argv;
    $scriptName = basename($argv[0] ?? 'switch-network.php');
    
    echo "Usage: {$scriptName} <profile-name> [options]\n\n";
    echo "Options:\n";
    echo "  --output=<path>    Write configuration to the specified file\n";
    echo "  --dry-run          Print configuration to stdout only (default: true)\n";
    echo "  --format=<format>  Output format: json|php|env (default: json)\n";
    echo "  --force            Overwrite existing file without confirmation\n";
    echo "  --help             Show this help message\n\n";
    echo "Available Profiles:\n";
    foreach (NetworkProfiles::all() as $profile) {
        echo "  - {$profile}\n";
    }
    echo "\nExamples:\n";
    echo "  {$scriptName} solana.mainnet\n";
    echo "  {$scriptName} ethereum.mainnet --format=php\n";
    echo "  {$scriptName} solana.devnet --output=config/active.json\n";
}

/**
 * Parse command-line arguments
 *
 * @param array<int, string> $argv Command-line arguments
 * @return array<string, mixed> Parsed arguments
 */
function parseArguments(array $argv): array
{
    $options = [
        'profile' => null,
        'output' => null,
        'dry_run' => true,
        'format' => 'json',
        'force' => false,
        'help' => false,
    ];

    // Remove script name
    array_shift($argv);

    foreach ($argv as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        } elseif (strpos($arg, '--output=') === 0) {
            $options['output'] = substr($arg, 9);
            $options['dry_run'] = false;
        } elseif (strpos($arg, '--format=') === 0) {
            $options['format'] = strtolower(substr($arg, 9));
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif (!str_starts_with($arg, '--') && $options['profile'] === null) {
            $options['profile'] = $arg;
        }
    }

    return $options;
}

/**
 * Format configuration as JSON
 *
 * @param array<string, mixed> $config Configuration array
 * @return string Formatted JSON string
 */
function formatAsJson(array $config): string
{
    return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

/**
 * Format configuration as PHP array
 *
 * @param array<string, mixed> $config Configuration array
 * @return string Formatted PHP array string
 */
function formatAsPhp(array $config): string
{
    $output = "<?php\n\nreturn ";
    $output .= var_export($config, true);
    $output .= ";\n";
    return $output;
}

/**
 * Format configuration as ENV key=value pairs
 *
 * @param array<string, mixed> $config Configuration array
 * @return string Formatted ENV string
 */
function formatAsEnv(array $config): string
{
    $lines = [];
    foreach ($config as $key => $value) {
        $envKey = strtoupper($key);
        if (is_array($value)) {
            $envValue = json_encode($value);
        } elseif (is_bool($value)) {
            $envValue = $value ? 'true' : 'false';
        } else {
            $envValue = (string) $value;
        }
        $lines[] = "{$envKey}={$envValue}";
    }
    return implode("\n", $lines) . "\n";
}

/**
 * Format configuration in the specified format
 *
 * @param array<string, mixed> $config Configuration array
 * @param string $format Format type (json|php|env)
 * @return string Formatted configuration
 * @throws InvalidArgumentException If format is not supported
 */
function formatConfig(array $config, string $format): string
{
    return match ($format) {
        'json' => formatAsJson($config),
        'php' => formatAsPhp($config),
        'env' => formatAsEnv($config),
        default => throw new \InvalidArgumentException(
            "Unsupported format '{$format}'. Supported formats: json, php, env"
        ),
    };
}

/**
 * Write configuration to file
 *
 * @param string $path File path
 * @param string $content Content to write
 * @param bool $force Skip confirmation if file exists
 * @return bool True on success, false on failure
 */
function writeToFile(string $path, string $content, bool $force): bool
{
    // Check if file exists and prompt for confirmation
    if (file_exists($path) && !$force) {
        echo "File '{$path}' already exists. Overwrite? (y/N): ";
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            fwrite(STDERR, "Error: Cannot read from stdin\n");
            return false;
        }
        $line = fgets($handle);
        fclose($handle);
        
        if ($line === false || strtolower(trim($line)) !== 'y') {
            fwrite(STDERR, "Operation cancelled.\n");
            return false;
        }
    }

    // Create directory if it doesn't exist
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            fwrite(STDERR, "Error: Cannot create directory '{$dir}'\n");
            return false;
        }
    }

    // Write to file
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "Error: Cannot write to file '{$path}'\n");
        return false;
    }

    // Set appropriate file permissions (0600 for sensitive configs)
    if (!chmod($path, 0600)) {
        fwrite(STDERR, "Warning: Cannot set file permissions for '{$path}'\n");
    }

    return true;
}

// Main execution
try {
    $options = parseArguments($argv);

    // Show help if requested or no profile specified
    if ($options['help'] || $options['profile'] === null) {
        showUsage();
        exit($options['help'] ? 0 : 1);
    }

    // Validate format
    if (!in_array($options['format'], ['json', 'php', 'env'])) {
        fwrite(STDERR, "Error: Invalid format '{$options['format']}'. Supported formats: json, php, env\n");
        exit(1);
    }

    // Get profile configuration
    $profile = NetworkProfiles::get($options['profile']);

    // Format configuration
    $output = formatConfig($profile, $options['format']);

    // Output or write to file
    if ($options['dry_run'] || $options['output'] === null) {
        // Dry-run mode: print to stdout
        echo $output;
        exit(0);
    } else {
        // Write to file
        if (writeToFile($options['output'], $output, $options['force'])) {
            echo "Configuration written to: {$options['output']}\n";
            exit(0);
        } else {
            exit(1);
        }
    }
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n\n");
    fwrite(STDERR, "Available profiles:\n");
    foreach (NetworkProfiles::all() as $profile) {
        fwrite(STDERR, "  - {$profile}\n");
    }
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
