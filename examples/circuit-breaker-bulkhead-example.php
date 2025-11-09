<?php

/**
 * Example: Using Circuit Breaker and Bulkhead Patterns
 *
 * This example demonstrates how to use the CircuitBreaker and Bulkhead
 * components to build resilient blockchain integrations with protection
 * against cascading failures and resource exhaustion.
 */

declare(strict_types=1);

// Manual requires for when autoloader is not available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../src/Reliability/CircuitBreaker.php';
    require_once __DIR__ . '/../src/Reliability/Bulkhead.php';
}

use Blockchain\Reliability\CircuitBreaker;
use Blockchain\Reliability\CircuitBreakerOpenException;
use Blockchain\Reliability\Bulkhead;
use Blockchain\Reliability\BulkheadFullException;

echo "=== Circuit Breaker and Bulkhead Examples ===\n\n";

// Example 1: Basic Circuit Breaker
echo "1. Basic Circuit Breaker\n";
echo "   Creating a circuit breaker with 3 failure threshold...\n";

$breaker = new CircuitBreaker(
    failureThreshold: 3,
    windowSizeSeconds: 60,
    cooldownSeconds: 30,
    successThreshold: 2
);

echo "   - State: {$breaker->getState()}\n";
echo "   - Failure threshold: {$breaker->getFailureThreshold()}\n";
echo "   - Window size: {$breaker->getWindowSizeSeconds()}s\n";
echo "   - Cooldown: {$breaker->getCooldownSeconds()}s\n\n";

// Example 2: Circuit Breaker in Action
echo "2. Circuit Breaker in Action\n";
echo "   Simulating service that fails 3 times then succeeds...\n";

$attemptCount = 0;
$testBreaker = new CircuitBreaker(failureThreshold: 3);

for ($i = 1; $i <= 5; $i++) {
    try {
        $result = $testBreaker->call(function () use ($i) {
            if ($i <= 3) {
                throw new RuntimeException("Service unavailable");
            }
            return "Success on attempt $i";
        });
        echo "   - Attempt $i: $result ✅\n";
    } catch (RuntimeException $e) {
        echo "   - Attempt $i: Failed (service error) ❌\n";
    } catch (CircuitBreakerOpenException $e) {
        echo "   - Attempt $i: Circuit open, fail fast ⚡\n";
    }
}

echo "   Final state: {$testBreaker->getState()}\n\n";

// Example 3: Force Open for Maintenance
echo "3. Force Open for Maintenance\n";
echo "   Manually opening circuit for maintenance...\n";

$maintenanceBreaker = new CircuitBreaker();
$maintenanceBreaker->forceOpen();

echo "   - State: {$maintenanceBreaker->getState()}\n";
echo "   - Forced open: " . ($maintenanceBreaker->isForcedOpen() ? 'Yes' : 'No') . "\n";

try {
    $maintenanceBreaker->call(fn() => 'should not execute');
} catch (CircuitBreakerOpenException $e) {
    echo "   - Request blocked: Circuit is forced open ⛔\n";
}

$maintenanceBreaker->close();
echo "   - After close: {$maintenanceBreaker->getState()}\n\n";

// Example 4: Basic Bulkhead
echo "4. Basic Bulkhead\n";
echo "   Creating a bulkhead with 5 concurrent operation limit...\n";

$bulkhead = new Bulkhead(
    maxConcurrent: 5,
    maxQueueSize: 10,
    queueTimeoutSeconds: 30
);

echo "   - Max concurrent: {$bulkhead->getMaxConcurrent()}\n";
echo "   - Max queue size: {$bulkhead->getMaxQueueSize()}\n";
echo "   - Active operations: {$bulkhead->getActiveCount()}\n\n";

// Example 5: Bulkhead in Action
echo "5. Bulkhead in Action\n";
echo "   Simulating concurrent operations...\n";

$testBulkhead = new Bulkhead(maxConcurrent: 3);

// Simulate filling the bulkhead
for ($i = 1; $i <= 5; $i++) {
    try {
        // In a real scenario, this would be async, but for demo we use tryAcquire
        if ($i <= 3) {
            $testBulkhead->tryAcquire();
            echo "   - Operation $i: Started (active: {$testBulkhead->getActiveCount()}) ✅\n";
        } else {
            $testBulkhead->execute(fn() => "task $i");
            echo "   - Operation $i: Executed ✅\n";
        }
    } catch (BulkheadFullException $e) {
        echo "   - Operation $i: Rejected (bulkhead full) ⛔\n";
    }
}

// Show statistics
$stats = $testBulkhead->getStats();
echo "\n   Statistics:\n";
echo "   - Active: {$stats['active']}/{$stats['maxConcurrent']}\n";
echo "   - Available: {$stats['available']}\n";
echo "   - Utilization: {$stats['utilizationPercent']}%\n\n";

// Release all slots
$testBulkhead->reset();
echo "   After reset: {$testBulkhead->getActiveCount()} active\n\n";

// Example 6: Combined Usage
echo "6. Combined Usage - Circuit Breaker + Bulkhead\n";
echo "   Protecting a service with both patterns...\n";

$combinedBreaker = new CircuitBreaker(failureThreshold: 2);
$combinedBulkhead = new Bulkhead(maxConcurrent: 2);

echo "   Configuration:\n";
echo "   - Circuit breaker: {$combinedBreaker->getFailureThreshold()} failure threshold\n";
echo "   - Bulkhead: {$combinedBulkhead->getMaxConcurrent()} concurrent limit\n\n";

// Simulate protected service calls
for ($i = 1; $i <= 4; $i++) {
    try {
        $result = $combinedBreaker->call(function () use ($combinedBulkhead, $i) {
            return $combinedBulkhead->execute(function () use ($i) {
                if ($i <= 2) {
                    throw new RuntimeException("Service error");
                }
                return "Request $i processed";
            });
        });
        echo "   - Request $i: $result ✅\n";
    } catch (RuntimeException $e) {
        echo "   - Request $i: Service error ❌\n";
    } catch (CircuitBreakerOpenException $e) {
        echo "   - Request $i: Circuit open ⚡\n";
    } catch (BulkheadFullException $e) {
        echo "   - Request $i: Bulkhead full ⛔\n";
    }
}

echo "\n   Final circuit state: {$combinedBreaker->getState()}\n\n";

// Example 7: Real-World Scenario
echo "7. Real-World Scenario - RPC Node Protection\n";
echo "   Simulating blockchain RPC calls with protection...\n\n";

$rpcBreaker = new CircuitBreaker(
    failureThreshold: 5,
    windowSizeSeconds: 60,
    cooldownSeconds: 30
);

$rpcBulkhead = new Bulkhead(
    maxConcurrent: 10,
    maxQueueSize: 20
);

// Simulate RPC call
function simulateRpcCall($blockNumber, $breaker, $bulkhead) {
    try {
        return $breaker->call(function () use ($bulkhead, $blockNumber) {
            return $bulkhead->execute(function () use ($blockNumber) {
                // Simulate RPC call
                return ['blockNumber' => $blockNumber, 'hash' => '0x' . bin2hex(random_bytes(32))];
            });
        });
    } catch (CircuitBreakerOpenException $e) {
        return ['error' => 'Circuit open - using cached data'];
    } catch (BulkheadFullException $e) {
        return ['error' => 'Too many concurrent requests'];
    }
}

$result = simulateRpcCall(12345, $rpcBreaker, $rpcBulkhead);
if (isset($result['error'])) {
    echo "   Error: {$result['error']}\n";
} else {
    echo "   ✅ Block {$result['blockNumber']} retrieved\n";
    echo "   Hash: " . substr($result['hash'], 0, 20) . "...\n";
}

echo "\n   Protection Status:\n";
echo "   - Circuit state: {$rpcBreaker->getState()}\n";
echo "   - Active requests: {$rpcBulkhead->getActiveCount()}/{$rpcBulkhead->getMaxConcurrent()}\n";
echo "   - Available capacity: {$rpcBulkhead->getAvailableSlots()}\n\n";

// Example 8: Best Practices
echo "8. Best Practices\n\n";

echo "   ✅ DO:\n";
echo "   - Use circuit breakers for external service calls\n";
echo "   - Set failure thresholds based on service SLA\n";
echo "   - Use bulkheads to isolate different resource pools\n";
echo "   - Monitor circuit breaker metrics and adjust\n";
echo "   - Combine with retry policies for resilience\n";
echo "   - Test failure scenarios in staging\n\n";

echo "   ❌ DON'T:\n";
echo "   - Set failure threshold too low (false positives)\n";
echo "   - Keep circuits forced open indefinitely\n";
echo "   - Use same bulkhead for critical and non-critical ops\n";
echo "   - Ignore circuit breaker state changes\n";
echo "   - Set bulkhead limits too low (artificial throttling)\n\n";

// Example 9: Monitoring and Observability
echo "9. Monitoring and Observability\n";
echo "   Key metrics to track...\n\n";

echo "   Circuit Breaker Metrics:\n";
echo "   - State: {$breaker->getState()}\n";
echo "   - Failure count: {$breaker->getFailureCount()}\n";
echo "   - Is open: " . ($breaker->isOpen() ? 'Yes' : 'No') . "\n";
echo "   - Is forced: " . ($breaker->isForcedOpen() ? 'Yes' : 'No') . "\n\n";

echo "   Bulkhead Metrics:\n";
$bulkheadStats = $bulkhead->getStats();
echo "   - Active: {$bulkheadStats['active']}\n";
echo "   - Available: {$bulkheadStats['available']}\n";
echo "   - Utilization: {$bulkheadStats['utilizationPercent']}%\n";
echo "   - Queue size: {$bulkheadStats['queueSize']}\n\n";

echo "=== Examples Complete ===\n";
echo "\nFor more information, see:\n";
echo "- src/Reliability/CircuitBreaker.php\n";
echo "- src/Reliability/Bulkhead.php\n";
echo "- tests/Reliability/CircuitBreakerTest.php\n";
echo "- tests/Reliability/BulkheadTest.php\n";
