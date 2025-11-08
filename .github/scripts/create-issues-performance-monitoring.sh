#!/bin/bash
set -euo pipefail

# Script to create GitHub issues for Performance Monitoring Implementation
# Usage: ./create-issues-performance-monitoring.sh [REPO]
# Example: ./create-issues-performance-monitoring.sh azaharizaman/php-blockchain

REPO="${1:-azaharizaman/php-blockchain}"
MILESTONE="PHP Blockchain SDK - Performance Monitoring"

echo "Creating GitHub issues for Performance Monitoring..."
echo "Repository: $REPO"
echo ""

# Create milestone if it doesn't exist
echo "Checking for milestone: $MILESTONE"
TEMP_FILE=$(mktemp)
trap 'rm -f "$TEMP_FILE"' EXIT

gh api repos/$REPO/milestones --jq ".[] | select(.title == \"$MILESTONE\") | .number" > "$TEMP_FILE"
if [ ! -s "$TEMP_FILE" ]; then
    echo "Creating milestone: $MILESTONE"
    MILESTONE_NUMBER=$(gh api repos/$REPO/milestones -f title="$MILESTONE" -f description="Implement performance monitoring, observability, metrics export, tracing hooks, and benchmarking harness for the SDK" --jq '.number')
else
    MILESTONE_NUMBER=$(cat "$TEMP_FILE")
fi
echo "✓ Using milestone: $MILESTONE (number: $MILESTONE_NUMBER)"
echo ""

# Ensure required labels exist
echo "Ensuring required labels exist..."
REQUIRED_LABELS=(
    "feature"
    "performance"
    "observability"
    "monitoring"
    "telemetry"
    "testing"
    "phase-1"
    "phase-2"
)

for LABEL in "${REQUIRED_LABELS[@]}"; do
    if ! gh label list --repo "$REPO" 2>/dev/null | grep -q "^$LABEL"; then
        echo "Creating label: $LABEL"
        gh label create "$LABEL" --repo "$REPO" 2>/dev/null || echo "  (label may already exist)"
    fi
done
echo "✓ All required labels ensured"
echo ""

print_issue_header() {
    echo "Creating Issue $1: $2..."
}

# Issue 1: Telemetry Interfaces
print_issue_header "1" "Telemetry Interfaces"
gh issue create \
    --repo "$REPO" \
    --title "TASK-001: Create telemetry exporter interface and noop implementation" \
    --milestone "$MILESTONE" \
    --label "feature,performance,telemetry,phase-1" \
    --body "## Overview
Introduce pluggable telemetry exporter interface and a default no-op implementation ensuring instrumentation is opt-in.

## Requirements
- **REQ-001**: Export metrics via ExporterInterface (request latencies, error rates, counts)
- **CON-001**: Telemetry must be opt-in and not enabled by default

## Implementation Checklist

### 1. Create ExporterInterface
- [ ] Create file \`src/Telemetry/ExporterInterface.php\`
- [ ] Add namespace: \`namespace Blockchain\\Telemetry;\`
- [ ] Add \`declare(strict_types=1);\`
- [ ] Define method \`public function export(array \$metrics): void;\`
- [ ] Add phpdoc: \`@param array<string,mixed> \$metrics\`

### 2. Create NoopExporter
- [ ] Create file \`src/Telemetry/NoopExporter.php\`
- [ ] Implement ExporterInterface with empty export method
- [ ] Add comprehensive docblock explaining noop behavior
- [ ] Make this the default exporter in SDK configuration

### 3. Documentation
- [ ] Add usage guide in \`docs/telemetry.md\` explaining how to enable telemetry
- [ ] Document expected metrics structure and format
- [ ] Explain how to implement custom exporters

### 4. Tests
- [ ] Create \`tests/Telemetry/NoopExporterTest.php\`
- [ ] Verify noop exporter can be instantiated and called without errors
- [ ] Run \`composer run phpstan\`

## Acceptance Criteria
- [x] ExporterInterface and NoopExporter exist
- [x] Default configuration uses NoopExporter
- [x] Tests pass and static analysis reports no errors

## Files Created
- \`src/Telemetry/ExporterInterface.php\`
- \`src/Telemetry/NoopExporter.php\`
- \`docs/telemetry.md\`
- \`tests/Telemetry/NoopExporterTest.php\`
" || echo "⚠ Issue 1 may already exist"

# Issue 2: MetricCollector
print_issue_header "2" "MetricCollector"
gh issue create \
    --repo "$REPO" \
    --title "TASK-002: Implement MetricCollector helper with timer and context support" \
    --milestone "$MILESTONE" \
    --label "feature,performance,telemetry,testing,phase-1" \
    --body "## Overview
Create MetricCollector helper that drivers can use to record timing, counters, and context for telemetry export.

## Requirements
- Support timer/context helpers used by drivers
- Produce aggregates when using in-memory exporter
- Integrate with ExporterInterface

## Implementation Checklist

### 1. Create MetricCollector class
- [ ] Create file \`src/Telemetry/MetricCollector.php\`
- [ ] Add namespace and strict types
- [ ] Inject ExporterInterface via constructor

### 2. Timer methods
- [ ] Implement \`public function startTimer(string \$name): string\` returning timer ID
- [ ] Implement \`public function stopTimer(string \$timerId): float\` returning elapsed milliseconds
- [ ] Store timing data in internal buffer

### 3. Counter and gauge methods
- [ ] Implement \`public function increment(string \$metric, int \$value = 1): void\`
- [ ] Implement \`public function gauge(string \$metric, float \$value): void\`
- [ ] Implement \`public function histogram(string \$metric, float \$value): void\`

### 4. Context and metadata
- [ ] Implement \`public function withContext(array \$context): self\` for immutable context attachment
- [ ] Ensure context is included in exported metrics
- [ ] Add phpdoc: \`@param array<string,mixed> \$context\`

### 5. Export and flush
- [ ] Implement \`public function flush(): void\` to export buffered metrics
- [ ] Implement \`public function reset(): void\` to clear buffer
- [ ] Auto-flush on destruct if buffer not empty

### 6. Tests
- [ ] Create \`tests/Telemetry/MetricCollectorTest.php\`
- [ ] Create \`tests/Telemetry/InMemoryExporter.php\` for testing
- [ ] Test timer accuracy using controlled delays
- [ ] Test counter increments and gauge updates
- [ ] Verify context attachment in exported metrics
- [ ] Verify aggregates are calculated correctly

### 7. Validation
- [ ] Run \`vendor/bin/phpunit tests/Telemetry/MetricCollectorTest.php\`
- [ ] Run \`composer run phpstan\`
- [ ] Ensure all public methods have proper docblocks

## Acceptance Criteria
- [x] MetricCollector records timing and produces expected aggregates
- [x] Unit tests with in-memory exporter pass
- [x] Context metadata flows through to export

## Files Created
- \`src/Telemetry/MetricCollector.php\`
- \`tests/Telemetry/InMemoryExporter.php\`
- \`tests/Telemetry/MetricCollectorTest.php\`
" || echo "⚠ Issue 2 may already exist"

# Issue 3: OpenTelemetry Exporter
print_issue_header "3" "OpenTelemetry Exporter"
gh issue create \
    --repo "$REPO" \
    --title "TASK-003: Implement OpenTelemetry exporter adapter" \
    --milestone "$MILESTONE" \
    --label "feature,performance,telemetry,phase-2" \
    --body "## Overview
Provide OpenTelemetry-compatible exporter that converts SDK metrics to OT format for integration with observability platforms.

## Requirements
- Implement ExporterInterface with OT format conversion
- Document required configuration
- Support dry-run mode without external network calls

## Implementation Checklist

### 1. Create OpenTelemetryExporter
- [ ] Create file \`src/Telemetry/OpenTelemetryExporter.php\`
- [ ] Add namespace and strict types
- [ ] Implement ExporterInterface
- [ ] Accept configuration array with endpoint, headers, auth

### 2. Metric conversion
- [ ] Map SDK timer metrics to OT histogram/summary
- [ ] Map SDK counters to OT counter metrics
- [ ] Map SDK gauges to OT gauge metrics
- [ ] Preserve context/attributes as OT resource attributes

### 3. Export implementation
- [ ] Implement HTTP POST to configured OT collector endpoint
- [ ] Use Guzzle client for network calls
- [ ] Support batch export with configurable limits
- [ ] Handle export failures gracefully with logging

### 4. Configuration
- [ ] Document required config keys in class docblock
- [ ] Support environment variable overrides
- [ ] Validate configuration on instantiation
- [ ] Throw ConfigurationException if endpoint missing

### 5. Tests
- [ ] Create \`tests/Telemetry/OpenTelemetryExporterTest.php\`
- [ ] Use MockHandler to simulate OT collector responses
- [ ] Verify metric format conversion matches OT spec
- [ ] Test error handling for network failures
- [ ] Test batch size limits and flushing

### 6. Documentation
- [ ] Add OT setup guide to \`docs/telemetry.md\`
- [ ] Provide example configuration snippets
- [ ] Document supported OT versions and endpoints

### 7. Validation
- [ ] Run integration tests in dry-run mode
- [ ] Run \`composer run phpstan\`
- [ ] Verify no external network calls without explicit config

## Acceptance Criteria
- [x] OpenTelemetry exporter converts metrics to OT format
- [x] Integration tests validate format without network calls
- [x] Documentation explains configuration requirements

## Files Created
- \`src/Telemetry/OpenTelemetryExporter.php\`
- \`tests/Telemetry/OpenTelemetryExporterTest.php\`
- Updated \`docs/telemetry.md\`
" || echo "⚠ Issue 3 may already exist"

# Issue 4: Benchmarking Harness
print_issue_header "4" "Benchmarking Harness"
gh issue create \
    --repo "$REPO" \
    --title "TASK-004: Create benchmarking harness for driver performance testing" \
    --milestone "$MILESTONE" \
    --label "feature,performance,testing,phase-2" \
    --body "## Overview
Implement benchmarking harness with workload scripts to measure driver RPC throughput and latency using controlled test scenarios.

## Requirements
- Measure RPC throughput and latency
- Support MockHandler or local node testing
- Produce CSV/JSON output with latency percentiles

## Implementation Checklist

### 1. Create bench directory structure
- [ ] Create \`bench/\` directory
- [ ] Add \`bench/README.md\` with usage instructions
- [ ] Create \`bench/.gitignore\` for output files

### 2. Implement driver benchmark script
- [ ] Create \`bench/driver-bench.php\`
- [ ] Accept CLI arguments: driver name, endpoint, iterations
- [ ] Support mock mode and live mode flags
- [ ] Load driver and configuration

### 3. Workload scenarios
- [ ] Implement \`getBalance\` workload (repeated balance queries)
- [ ] Implement \`getTransaction\` workload (transaction fetches)
- [ ] Implement \`getBlock\` workload (block queries)
- [ ] Allow custom workload scripts via --workload parameter

### 4. Metrics collection
- [ ] Use MetricCollector to track operation latencies
- [ ] Calculate p50, p90, p95, p99 percentiles
- [ ] Calculate throughput (ops/second)
- [ ] Calculate error rate percentage

### 5. Output formatting
- [ ] Implement CSV output with columns: metric, value, unit
- [ ] Implement JSON output with structured results
- [ ] Add --output-format flag (csv|json)
- [ ] Write results to stdout or --output-file path

### 6. Tests
- [ ] Create \`tests/Bench/DriverBenchTest.php\`
- [ ] Verify benchmark harness runs with mock driver
- [ ] Verify percentile calculations are accurate
- [ ] Verify output format parsing

### 7. CI integration
- [ ] Add optional bench job to \`.github/workflows/agent-tasks.yml\`
- [ ] Run bench in mock mode as smoke test
- [ ] Store bench results as artifacts

### 8. Documentation
- [ ] Document bench usage in \`bench/README.md\`
- [ ] Provide example commands for common scenarios
- [ ] Explain how to interpret results

## Acceptance Criteria
- [x] Bench scripts produce latency percentiles in JSON/CSV
- [x] Harness works with MockHandler without network calls
- [x] Documentation explains usage and output format

## Files Created
- \`bench/driver-bench.php\`
- \`bench/README.md\`
- \`tests/Bench/DriverBenchTest.php\`
" || echo "⚠ Issue 4 may already exist"

print_issue_header "✓" "All Performance Monitoring issues attempted"
echo "Done. Review output above for any warnings about existing issues."
