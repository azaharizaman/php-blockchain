<?php
/**
 * Manual verification script for logging and audit functionality
 * This script tests the core functionality without requiring PHPUnit
 */

declare(strict_types=1);

// Use composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Blockchain\Logging\RedactingLogger;
use Blockchain\Logging\NullLogger;
use Blockchain\Audit\FileAuditRecorder;
use Blockchain\Audit\NullAuditRecorder;

echo "=== PHP Blockchain Logging & Audit Verification ===\n\n";

// Test 1: NullLogger
echo "Test 1: NullLogger instantiation... ";
$testsPassed = 0;
$testsFailed = 0;
try {
    $nullLogger = new NullLogger();
    $nullLogger->info('Test message');
    echo "✓ PASS\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 2: RedactingLogger instantiation
echo "Test 2: RedactingLogger instantiation... ";
try {
    $logger = new RedactingLogger(new NullLogger());
    echo "✓ PASS\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 3: RedactingLogger field management
echo "Test 3: RedactingLogger field management... ";
try {
    $logger = new RedactingLogger(new NullLogger());
    $fields = $logger->getRedactedFields();
    
    if (in_array('private_key', $fields) && in_array('password', $fields)) {
        echo "✓ PASS (Default fields present)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Default fields missing\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 4: NullAuditRecorder
echo "Test 4: NullAuditRecorder... ";
try {
    $auditor = new NullAuditRecorder();
    $auditor->record('test.event', 'test-actor', ['key' => 'value']);
    
    $events = $auditor->getEvents(
        new DateTimeImmutable('-1 hour'),
        new DateTimeImmutable('+1 hour')
    );
    
    if (empty($events)) {
        echo "✓ PASS (Returns empty as expected)\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Should return empty array\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 5: FileAuditRecorder - Basic recording
echo "Test 5: FileAuditRecorder recording... ";
try {
    $tempFile = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
    $auditor = new FileAuditRecorder($tempFile);
    
    $auditor->record('key.created', 'user-123', [
        'key_id' => 'key-abc',
        'algorithm' => 'secp256k1',
    ]);
    
    if (file_exists($tempFile)) {
        $content = file_get_contents($tempFile);
        $event = json_decode(trim($content), true);
        
        if ($event['event_type'] === 'key.created' && $event['actor'] === 'user-123') {
            echo "✓ PASS\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: Event data mismatch\n";
            $testsFailed++;
        }
        
        unlink($tempFile);
        // Clean up directory only if it was created by FileAuditRecorder
        $dir = dirname($tempFile);
        if ($dir !== sys_get_temp_dir() && is_dir($dir) && count(scandir($dir)) == 2) {
            rmdir($dir);
        }
    } else {
        echo "✗ FAIL: File not created\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 6: FileAuditRecorder - Event retrieval
echo "Test 6: FileAuditRecorder event retrieval... ";
try {
    $tempFile = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
    $auditor = new FileAuditRecorder($tempFile);
    
    $auditor->record('event.1', 'actor-1', ['data' => 'test1']);
    $auditor->record('event.2', 'actor-2', ['data' => 'test2']);
    
    $events = $auditor->getEvents(
        new DateTimeImmutable('-1 hour'),
        new DateTimeImmutable('+1 hour')
    );
    
    if (count($events) === 2) {
        echo "✓ PASS\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Expected 2 events, got " . count($events) . "\n";
        $testsFailed++;
    }
    
    unlink($tempFile);
    // Clean up directory only if it was created by FileAuditRecorder
    $dir = dirname($tempFile);
    if ($dir !== sys_get_temp_dir() && is_dir($dir) && count(scandir($dir)) == 2) {
        rmdir($dir);
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 7: FileAuditRecorder - Event filtering by type
echo "Test 7: FileAuditRecorder event type filtering... ";
try {
    $tempFile = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
    $auditor = new FileAuditRecorder($tempFile);
    
    $auditor->record('key.created', 'actor-1', []);
    $auditor->record('key.rotated', 'actor-2', []);
    $auditor->record('key.created', 'actor-3', []);
    
    $events = $auditor->getEvents(
        new DateTimeImmutable('-1 hour'),
        new DateTimeImmutable('+1 hour'),
        'key.created'
    );
    
    if (count($events) === 2 && $events[0]['event_type'] === 'key.created') {
        echo "✓ PASS\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Filtering not working correctly\n";
        $testsFailed++;
    }
    
    unlink($tempFile);
    // Clean up directory only if it was created by FileAuditRecorder
    $dir = dirname($tempFile);
    if ($dir !== sys_get_temp_dir() && is_dir($dir) && count(scandir($dir)) == 2) {
        rmdir($dir);
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

// Test 8: File path verification
echo "Test 8: FileAuditRecorder file path getter... ";
try {
    $tempFile = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
    $auditor = new FileAuditRecorder($tempFile);
    
    if ($auditor->getFilePath() === $tempFile) {
        echo "✓ PASS\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: File path mismatch\n";
        $testsFailed++;
    }
    
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    // Clean up directory only if it was created by FileAuditRecorder
    $dir = dirname($tempFile);
    if ($dir !== sys_get_temp_dir() && is_dir($dir) && count(scandir($dir)) == 2) {
        rmdir($dir);
    }
} catch (Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n";
    $testsFailed++;
}

echo "\n=== Verification Complete ===\n";
if ($testsFailed === 0) {
    echo "All $testsPassed tests passed successfully!\n";
} else {
    echo "Results: $testsPassed passed, $testsFailed failed\n";
}
