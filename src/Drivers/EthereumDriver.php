<?php

declare(strict_types=1);

namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Transport\GuzzleAdapter;
use Blockchain\Utils\CachePool;

/**
 * EthereumDriver provides blockchain interaction for Ethereum and EVM-compatible chains.
 *
 * This driver implements the BlockchainDriverInterface for Ethereum networks,
 * supporting JSON-RPC communication with Ethereum nodes (Infura, Alchemy, or self-hosted).
 *
 * @package Blockchain\Drivers
 */
class EthereumDriver implements BlockchainDriverInterface
{
    /**
     * HTTP client adapter for making JSON-RPC requests.
     */
    private ?GuzzleAdapter $httpClient = null;

    /**
     * Cache pool for caching blockchain data.
     */
    private CachePool $cache;

    /**
     * RPC endpoint URL.
     */
    private string $endpoint = '';

    /**
     * Chain ID of the connected network.
     */
    private ?string $chainId = null;

    /**
     * Constructor to inject optional dependencies.
     *
     * @param GuzzleAdapter|null $httpClient Optional HTTP client adapter for making requests
     * @param CachePool|null $cache Optional cache pool for caching responses
     */
    public function __construct(?GuzzleAdapter $httpClient = null, ?CachePool $cache = null)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache ?? new CachePool();
    }

    /**
     * Connect to the Ethereum network with the given configuration.
     *
     * @param array<string,mixed> $config Configuration array containing 'endpoint' key
     * @throws ConfigurationException If endpoint is missing from configuration
     * @return void
     */
    public function connect(array $config): void
    {
        if (!isset($config['endpoint'])) {
            throw new ConfigurationException('Ethereum endpoint is required in configuration.');
        }

        $this->endpoint = $config['endpoint'];

        // Create GuzzleAdapter if not provided via constructor
        if ($this->httpClient === null) {
            $clientConfig = [
                'base_uri' => $config['endpoint'],
                'timeout' => $config['timeout'] ?? 30,
            ];

            $this->httpClient = new GuzzleAdapter(null, $clientConfig);
        }

        // Optionally validate connection by fetching chain ID
        try {
            $this->chainId = $this->rpcCall('eth_chainId', []);
        } catch (\Exception $e) {
            // Continue even if chain ID fetch fails (node might be syncing)
        }
    }

    /**
     * Get the balance of an Ethereum address.
     *
     * @param string $address The Ethereum address to query (0x prefixed)
     * @throws ConfigurationException If the driver is not connected
     * @throws \Exception If the balance query fails
     * @return float The balance in ETH
     */
    public function getBalance(string $address): float
    {
        $this->ensureConnected();

        // Generate cache key
        $cacheKey = CachePool::generateKey('getBalance', ['address' => $address]);

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Call eth_getBalance with "latest" block parameter
        $balanceHex = $this->rpcCall('eth_getBalance', [$address, 'latest']);

        // Convert hex wei to float ETH
        $balance = $this->weiToEth($balanceHex);

        // Store in cache with default TTL
        $this->cache->set($cacheKey, $balance);

        return $balance;
    }

    /**
     * Send a transaction (placeholder implementation).
     *
     * @param string $from The sender's blockchain address
     * @param string $to The recipient's blockchain address
     * @param float $amount The amount to transfer in ETH
     * @param array<string,mixed> $options Additional transaction options
     * @throws ConfigurationException If the driver is not connected
     * @throws TransactionException Always throws as not yet implemented
     * @return string The transaction hash (not reached)
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        $this->ensureConnected();

        // TODO: Phase 2 - Implement raw transaction signing and broadcasting
        // This will require:
        // 1. Building the transaction object
        // 2. Signing with private key
        // 3. Broadcasting via eth_sendRawTransaction
        throw new TransactionException('Raw transaction signing not yet implemented for Ethereum driver.');
    }

    /**
     * Get transaction details by hash.
     *
     * @param string $hash The transaction hash (0x prefixed)
     * @throws ConfigurationException If the driver is not connected
     * @throws \Exception If the transaction query fails
     * @return array<string,mixed> Transaction details
     */
    public function getTransaction(string $hash): array
    {
        $this->ensureConnected();

        // Generate cache key
        $cacheKey = CachePool::generateKey('getTransaction', ['hash' => $hash]);

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Call eth_getTransactionByHash
        $transaction = $this->rpcCall('eth_getTransactionByHash', [$hash]);

        if ($transaction === null) {
            return [];
        }

        // Store in cache with longer TTL for immutable transaction data
        $this->cache->set($cacheKey, $transaction, 3600); // 1 hour

        return $transaction;
    }

    /**
     * Get block information by number or hash.
     *
     * @param int|string $blockIdentifier The block number (int) or block hash (string)
     * @throws ConfigurationException If the driver is not connected
     * @throws \Exception If the block query fails
     * @return array<string,mixed> Block information
     */
    public function getBlock(int|string $blockIdentifier): array
    {
        $this->ensureConnected();

        // Generate cache key
        $cacheKey = CachePool::generateKey('getBlock', ['block' => $blockIdentifier]);

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Determine if identifier is block number or hash
        if (is_int($blockIdentifier)) {
            // Convert to hex for block number
            $blockParam = '0x' . dechex($blockIdentifier);
            $method = 'eth_getBlockByNumber';
        } else {
            // Assume it's a hash
            $blockParam = $blockIdentifier;
            $method = 'eth_getBlockByHash';
        }

        // Call appropriate RPC method with full transaction details (true parameter)
        $block = $this->rpcCall($method, [$blockParam, true]);

        if ($block === null) {
            return [];
        }

        // Store in cache with longer TTL for immutable block data
        $this->cache->set($cacheKey, $block, 3600); // 1 hour

        return $block;
    }

    /**
     * Estimate gas for a transaction (stub).
     *
     * @param string $from The sender's blockchain address
     * @param string $to The recipient's blockchain address
     * @param float $amount The amount to transfer in ETH
     * @param array<string,mixed> $options Additional transaction options
     * @throws ConfigurationException If the driver is not connected
     * @return int|null Estimated gas units, or null (not yet implemented)
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        // TODO: TASK-004 - Implement gas estimation
        // This will use eth_estimateGas RPC method
        return null;
    }

    /**
     * Get token balance for a specific ERC-20 token (stub).
     *
     * @param string $address The wallet address to query
     * @param string $tokenAddress The ERC-20 token contract address
     * @throws ConfigurationException If the driver is not connected
     * @return float|null The token balance, or null (not yet implemented)
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        // TODO: TASK-005 - Implement ERC-20 token balance queries
        // This will require calling the balanceOf method on the token contract
        return null;
    }

    /**
     * Get Ethereum network information.
     *
     * @throws ConfigurationException If the driver is not connected
     * @throws \Exception If network info query fails
     * @return array<string,mixed>|null Network information including chainId, gasPrice, and blockNumber
     */
    public function getNetworkInfo(): ?array
    {
        $this->ensureConnected();

        try {
            // Fetch multiple network parameters
            $chainId = $this->rpcCall('eth_chainId', []);
            $gasPrice = $this->rpcCall('eth_gasPrice', []);
            $blockNumber = $this->rpcCall('eth_blockNumber', []);

            return [
                'chainId' => $this->hexToInt($chainId),
                'gasPrice' => $this->hexToInt($gasPrice),
                'blockNumber' => $this->hexToInt($blockNumber),
            ];
        } catch (\Exception $e) {
            // Return null if network info cannot be retrieved
            return null;
        }
    }

    /**
     * Make a JSON-RPC call to the Ethereum node.
     *
     * @param string $method The RPC method name (e.g., 'eth_getBalance')
     * @param array<int,mixed> $params The method parameters
     * @throws ConfigurationException If the driver is not connected
     * @throws \Exception If the RPC call fails or returns an error
     * @return mixed The result field from the RPC response
     */
    private function rpcCall(string $method, array $params = []): mixed
    {
        $this->ensureConnected();

        // Build JSON-RPC payload
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];

        // Make HTTP POST request
        $response = $this->httpClient->post('', $payload);

        // Check for RPC error
        if (isset($response['error'])) {
            $errorMessage = $response['error']['message'] ?? 'Unknown RPC error';
            throw new \Exception("Ethereum RPC Error: {$errorMessage}");
        }

        // Return result field
        return $response['result'] ?? null;
    }

    /**
     * Convert hex wei value to float ETH.
     *
     * @param string $wei Hex-encoded wei value (e.g., '0x1234')
     * @return float The value in ETH
     */
    private function weiToEth(string $wei): float
    {
        // Convert hex to decimal string
        $weiDecimal = $this->hexToInt($wei);

        // Convert wei to ETH (1 ETH = 10^18 wei)
        return $weiDecimal / 1e18;
    }

    /**
     * Convert hex string to integer.
     *
     * @param string $hex Hex-encoded value (with or without 0x prefix)
     * @return int The decimal integer value
     */
    private function hexToInt(string $hex): int
    {
        // Remove 0x prefix if present
        $hex = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;

        // Convert hex to integer
        return (int) hexdec($hex);
    }

    /**
     * Validate Ethereum address format.
     *
     * @param string $address The address to validate
     * @return bool True if the address is valid, false otherwise
     */
    private function validateAddress(string $address): bool
    {
        // Check if address starts with 0x and has 40 hex characters
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }

        return true;
    }

    /**
     * Ensure the driver is connected before performing operations.
     *
     * @throws ConfigurationException If not connected
     * @return void
     */
    private function ensureConnected(): void
    {
        if ($this->httpClient === null || empty($this->endpoint)) {
            throw new ConfigurationException('Ethereum driver is not connected. Please call connect() first.');
        }
    }
}
