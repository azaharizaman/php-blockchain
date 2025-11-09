# Blockchain Driver Benchmarking Harness

This directory contains benchmarking tools for measuring blockchain driver RPC throughput and latency using controlled test scenarios.

## Overview

The benchmarking harness provides performance testing capabilities for blockchain drivers with support for:

- **Multiple Workload Scenarios**: Test different RPC operations (getBalance, getTransaction, getBlock)
- **Mock Mode**: Test without network calls using Guzzle MockHandler
- **Live Mode**: Test against real or local blockchain nodes
- **Detailed Metrics**: Latency percentiles (p50, p90, p95, p99), throughput, error rates
- **Multiple Output Formats**: CSV and JSON for easy integration with analysis tools

## Usage

### Basic Usage

```bash
php bench/driver-bench.php --driver=ethereum --endpoint=http://localhost:8545 --iterations=100
```

### Mock Mode (No Network Calls)

```bash
php bench/driver-bench.php --driver=ethereum --mock --iterations=1000
```

### Specific Workload

```bash
# Test only getBalance operations
php bench/driver-bench.php --driver=solana --workload=getBalance --iterations=500

# Test only getTransaction operations
php bench/driver-bench.php --driver=ethereum --workload=getTransaction --iterations=300
```

### Output Formats

```bash
# JSON output (default)
php bench/driver-bench.php --driver=ethereum --mock --output-format=json

# CSV output
php bench/driver-bench.php --driver=ethereum --mock --output-format=csv

# Save to file
php bench/driver-bench.php --driver=ethereum --mock --output-file=results.json
```

## Command-Line Options

| Option | Description | Required | Default |
|--------|-------------|----------|---------|
| `--driver` | Driver name (ethereum, solana) | Yes | - |
| `--endpoint` | RPC endpoint URL | No* | - |
| `--iterations` | Number of operations to perform | No | 100 |
| `--mock` | Use MockHandler (no network calls) | No | false |
| `--workload` | Workload type (getBalance, getTransaction, getBlock, all) | No | all |
| `--output-format` | Output format (json, csv) | No | json |
| `--output-file` | Path to output file (stdout if not specified) | No | - |

\* Required unless `--mock` is specified

## Workload Scenarios

### getBalance Workload

Measures performance of repeated balance queries:
- Uses random valid addresses
- Tests read-only operations
- Good baseline for RPC throughput

### getTransaction Workload

Measures performance of transaction fetches:
- Uses transaction hashes
- Tests indexed data retrieval
- Common operation in explorers and wallets

### getBlock Workload

Measures performance of block queries:
- Uses block numbers or hashes
- Tests large data retrieval
- Important for sync operations

### All Workloads (Default)

Runs all three workload types sequentially and provides aggregate metrics.

## Output Format

### JSON Output

```json
{
  "driver": "ethereum",
  "workload": "getBalance",
  "iterations": 1000,
  "mode": "mock",
  "metrics": {
    "duration_seconds": 2.456,
    "throughput_ops_per_sec": 407.2,
    "latency_ms": {
      "min": 1.23,
      "max": 45.67,
      "p50": 2.34,
      "p90": 3.45,
      "p95": 4.56,
      "p99": 8.90,
      "avg": 2.45
    },
    "errors": {
      "count": 0,
      "rate_percent": 0.0
    }
  }
}
```

### CSV Output

```csv
metric,value,unit
driver,ethereum,
workload,getBalance,
iterations,1000,
mode,mock,
duration,2.456,seconds
throughput,407.2,ops/sec
latency_min,1.23,ms
latency_max,45.67,ms
latency_p50,2.34,ms
latency_p90,3.45,ms
latency_p95,4.56,ms
latency_p99,8.90,ms
latency_avg,2.45,ms
error_count,0,
error_rate,0.0,percent
```

## Interpreting Results

### Latency Percentiles

- **p50 (Median)**: 50% of operations completed in this time or less
- **p90**: 90% of operations completed in this time or less
- **p95**: 95% of operations completed in this time or less  
- **p99**: 99% of operations completed in this time or less

Higher percentiles (p95, p99) are important for understanding worst-case performance and detecting outliers.

### Throughput

Operations per second indicates the maximum rate at which the driver can process requests. Compare this with your application's expected load.

### Error Rate

Percentage of operations that failed. In mock mode, this should be 0% unless testing error scenarios.

## Examples

### Compare Drivers

```bash
# Benchmark Ethereum driver
php bench/driver-bench.php --driver=ethereum --mock --iterations=1000 > eth-results.json

# Benchmark Solana driver
php bench/driver-bench.php --driver=solana --mock --iterations=1000 > sol-results.json

# Compare results
diff eth-results.json sol-results.json
```

### Test Against Local Node

```bash
# Start local Ethereum node on port 8545
# Then benchmark against it
php bench/driver-bench.php \
  --driver=ethereum \
  --endpoint=http://localhost:8545 \
  --iterations=500 \
  --output-format=csv \
  --output-file=local-node-perf.csv
```

### CI/CD Integration

```bash
# Run as smoke test in CI pipeline
php bench/driver-bench.php --driver=ethereum --mock --iterations=100

# Check exit code
if [ $? -eq 0 ]; then
  echo "Benchmark passed"
else
  echo "Benchmark failed"
  exit 1
fi
```

## Performance Tips

1. **Use Mock Mode for Reproducibility**: Network latency varies; mock mode provides consistent results
2. **Sufficient Iterations**: Use at least 100 iterations for meaningful percentile calculations
3. **Warm-up**: Consider adding a warm-up phase to exclude cold-start effects
4. **Environment Consistency**: Run benchmarks on the same hardware/environment for comparisons

## Troubleshooting

### High p99 Latency

Could indicate:
- Network congestion (in live mode)
- Garbage collection pauses
- Resource contention

### Low Throughput

Could indicate:
- Slow network/RPC endpoint
- Inefficient driver implementation
- Bottlenecks in HTTP client

### Errors in Mock Mode

Indicates:
- Bug in driver implementation
- Incorrect mock responses
- Missing error handling

## Contributing

When adding new workload scenarios:

1. Create a new workload function in `driver-bench.php`
2. Add CLI option parsing for the new workload
3. Update this README with documentation
4. Add tests to `tests/Bench/DriverBenchTest.php`

## See Also

- [MetricCollector Documentation](../src/Telemetry/MetricCollector.php)
- [Driver Interface](../src/Contracts/BlockchainDriverInterface.php)
- [Testing Guide](../TESTING.md)
