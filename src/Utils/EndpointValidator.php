<?php

declare(strict_types=1);

namespace Blockchain\Utils;

use Blockchain\Transport\GuzzleAdapter;
use GuzzleHttp\Client;

/**
 * EndpointValidator provides utilities to validate custom RPC endpoint reachability.
 *
 * This class supports both dry-run validation (URL format only) and live validation
 * (actual network requests) to ensure endpoints are accessible before use in production.
 *
 * @package Blockchain\Utils
 */
class EndpointValidator
{
    /**
     * The HTTP adapter for making network requests.
     */
    private GuzzleAdapter $adapter;

    /**
     * Create a new EndpointValidator instance.
     *
     * @param GuzzleAdapter|null $adapter Optional GuzzleAdapter for dependency injection (useful for testing)
     *
     * @example
     * ```php
     * // Default usage
     * $validator = new EndpointValidator();
     *
     * // For testing with mock adapter
     * $mockAdapter = new GuzzleAdapter($mockClient);
     * $validator = new EndpointValidator($mockAdapter);
     * ```
     */
    public function __construct(?GuzzleAdapter $adapter = null)
    {
        if ($adapter === null) {
            // Create default adapter if not provided
            $this->adapter = new GuzzleAdapter(
                new Client([
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ])
            );
        } else {
            $this->adapter = $adapter;
        }
    }

    /**
     * Validate endpoint in dry-run mode (no network calls).
     *
     * Validates the URL format only:
     * - Checks if the URL can be parsed
     * - Verifies scheme is http/https/wss
     * - Ensures host is present
     *
     * @param string $endpoint The endpoint URL to validate
     * @return ValidationResult The validation result
     *
     * @example
     * ```php
     * $validator = new EndpointValidator();
     * $result = $validator->validateDryRun('https://api.mainnet-beta.solana.com');
     * if ($result->isValid()) {
     *     echo "URL format is valid\n";
     * }
     * ```
     */
    public function validateDryRun(string $endpoint): ValidationResult
    {
        // Validate URL format
        $parsedUrl = parse_url($endpoint);

        if ($parsedUrl === false) {
            return new ValidationResult(
                false,
                null,
                'Invalid URL format'
            );
        }

        // Check if scheme exists and is valid
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https', 'wss'])) {
            return new ValidationResult(
                false,
                null,
                'URL scheme must be http, https, or wss'
            );
        }

        // Check if host exists
        if (!isset($parsedUrl['host']) || empty($parsedUrl['host'])) {
            return new ValidationResult(
                false,
                null,
                'URL must contain a valid host'
            );
        }

        return new ValidationResult(true, null, null);
    }

    /**
     * Validate endpoint with live network request.
     *
     * Performs actual network validation:
     * - Sends HTTP GET request to the endpoint
     * - Measures latency
     * - Checks for 2xx response code
     * - Optionally sends RPC ping request
     *
     * @param string $endpoint The endpoint URL to validate
     * @param array<string,mixed> $options Validation options:
     *   - 'rpc-ping' (bool): Send RPC ping request (default: false)
     *   - 'blockchain' (string): Blockchain type for RPC ping ('ethereum' or 'solana')
     * @return ValidationResult The validation result
     *
     * @example
     * ```php
     * $validator = new EndpointValidator();
     *
     * // Basic HTTP validation
     * $result = $validator->validate('https://api.mainnet-beta.solana.com');
     *
     * // With RPC ping
     * $result = $validator->validate('https://api.mainnet-beta.solana.com', [
     *     'rpc-ping' => true,
     *     'blockchain' => 'solana'
     * ]);
     *
     * if ($result->isValid()) {
     *     echo sprintf("Endpoint is valid (latency: %.3fs)\n", $result->getLatency());
     * } else {
     *     echo "Error: " . $result->getError() . "\n";
     * }
     * ```
     */
    public function validate(string $endpoint, array $options = []): ValidationResult
    {
        // First validate URL format
        $dryRunResult = $this->validateDryRun($endpoint);
        if (!$dryRunResult->isValid()) {
            return $dryRunResult;
        }

        // Check if RPC ping is requested
        $rpcPing = $options['rpc-ping'] ?? false;
        $blockchain = $options['blockchain'] ?? null;

        try {
            if ($rpcPing && $blockchain !== null) {
                return $this->validateWithRpcPing($endpoint, $blockchain);
            } else {
                return $this->validateHttpReachability($endpoint);
            }
        } catch (\Throwable $e) {
            return new ValidationResult(
                false,
                null,
                'Network error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate endpoint HTTP reachability with GET request.
     *
     * @param string $endpoint The endpoint URL
     * @return ValidationResult The validation result
     */
    private function validateHttpReachability(string $endpoint): ValidationResult
    {
        $startTime = microtime(true);

        try {
            $response = $this->adapter->get($endpoint);
            $latency = microtime(true) - $startTime;

            return new ValidationResult(true, $latency, null);
        } catch (\Throwable $e) {
            $latency = microtime(true) - $startTime;
            return new ValidationResult(
                false,
                $latency,
                $e->getMessage()
            );
        }
    }

    /**
     * Validate endpoint with blockchain-specific RPC ping.
     *
     * @param string $endpoint The endpoint URL
     * @param string $blockchain The blockchain type ('ethereum' or 'solana')
     * @return ValidationResult The validation result
     */
    private function validateWithRpcPing(string $endpoint, string $blockchain): ValidationResult
    {
        $startTime = microtime(true);

        try {
            // Prepare RPC request based on blockchain type
            $rpcRequest = $this->getRpcPingRequest($blockchain);

            if ($rpcRequest === null) {
                return new ValidationResult(
                    false,
                    null,
                    'Unsupported blockchain type: ' . $blockchain
                );
            }

            $response = $this->adapter->post($endpoint, $rpcRequest);
            $latency = microtime(true) - $startTime;

            // Validate JSON-RPC response structure
            // Must have 'jsonrpc' field and either 'result' or 'error' field
            if (!isset($response['jsonrpc']) || (!isset($response['result']) && !isset($response['error']))) {
                return new ValidationResult(
                    false,
                    $latency,
                    'Invalid RPC response structure'
                );
            }

            // Check for error in response
            if (isset($response['error'])) {
                $errorMsg = is_array($response['error'])
                    ? ($response['error']['message'] ?? json_encode($response['error']))
                    : (string) $response['error'];

                return new ValidationResult(
                    false,
                    $latency,
                    'RPC error: ' . $errorMsg
                );
            }

            return new ValidationResult(true, $latency, null);
        } catch (\Throwable $e) {
            $latency = microtime(true) - $startTime;
            return new ValidationResult(
                false,
                $latency,
                'RPC ping failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get RPC ping request payload for the specified blockchain.
     *
     * @param string $blockchain The blockchain type
     * @return array<string,mixed>|null The RPC request payload, or null if unsupported
     */
    private function getRpcPingRequest(string $blockchain): ?array
    {
        return match (strtolower($blockchain)) {
            'ethereum' => [
                'jsonrpc' => '2.0',
                'method' => 'eth_chainId',
                'params' => [],
                'id' => 1,
            ],
            'solana' => [
                'jsonrpc' => '2.0',
                'method' => 'getHealth',
                'params' => [],
                'id' => 1,
            ],
            default => null,
        };
    }
}
