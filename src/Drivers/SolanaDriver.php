<?php

declare(strict_types=1);

namespace Blockchain\Drivers;

use Blockchain\Contracts\BlockchainDriverInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Utils\CachePool;

class SolanaDriver implements BlockchainDriverInterface
{
    private const LAMPORTS_PER_SOL = 1000000000;

    protected ?Client $client = null;
    protected array $config = [];
    protected CachePool $cache;

    /**
     * Constructor to inject optional cache dependency.
     *
     * @param CachePool|null $cache Optional cache pool for caching responses
     */
    public function __construct(?CachePool $cache = null)
    {
        $this->cache = $cache ?? new CachePool();
    }

    /**
     * Connect to the Solana network with the given configuration.
     */
    public function connect(array $config): void
    {
        if (!isset($config['endpoint'])) {
            throw new ConfigurationException('Solana endpoint is required in configuration.');
        }

        $this->config = $config;
        $this->client = new Client([
            'base_uri' => $config['endpoint'],
            'timeout' => $config['timeout'] ?? 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Get the balance of a Solana address.
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

        try {
            $response = $this->client->post('', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getBalance',
                    'params' => [$address]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \Exception('Solana RPC Error: ' . $data['error']['message']);
            }

            // Convert lamports to SOL (1 SOL = 1,000,000,000 lamports)
            $balance = ($data['result']['value'] ?? 0) / self::LAMPORTS_PER_SOL;

            // Store in cache with default TTL
            $this->cache->set($cacheKey, $balance);

            return $balance;
        } catch (RequestException $e) {
            throw new \Exception('Failed to get balance: ' . $e->getMessage());
        }
    }

    /**
     * Send a transaction (placeholder implementation).
     */
    public function sendTransaction(string $from, string $to, float $amount, array $options = []): string
    {
        $this->ensureConnected();

        // This is a placeholder implementation
        // In a real implementation, you would:
        // 1. Create a transaction
        // 2. Sign it with the private key
        // 3. Send it to the network

        throw new \Exception('Transaction sending not yet implemented for Solana driver.');
    }

    /**
     * Get transaction details by signature.
     */
    public function getTransaction(string $txHash): array
    {
        $this->ensureConnected();

        // Generate cache key
        $cacheKey = CachePool::generateKey('getTransaction', ['txHash' => $txHash]);

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->client->post('', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getTransaction',
                    'params' => [
                        $txHash,
                        ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \Exception('Solana RPC Error: ' . $data['error']['message']);
            }

            $transaction = $data['result'] ?? [];

            // Store in cache with longer TTL for immutable transaction data
            $this->cache->set($cacheKey, $transaction, 3600); // 1 hour

            return $transaction;
        } catch (RequestException $e) {
            throw new \Exception('Failed to get transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get block information by slot number.
     */
    public function getBlock(int|string $blockNumber): array
    {
        $this->ensureConnected();

        // Generate cache key
        $cacheKey = CachePool::generateKey('getBlock', ['blockNumber' => (int)$blockNumber]);

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->client->post('', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getBlock',
                    'params' => [
                        (int)$blockNumber,
                        ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \Exception('Solana RPC Error: ' . $data['error']['message']);
            }

            $block = $data['result'] ?? [];

            // Store in cache with longer TTL for immutable block data
            $this->cache->set($cacheKey, $block, 3600); // 1 hour

            return $block;
        } catch (RequestException $e) {
            throw new \Exception('Failed to get block: ' . $e->getMessage());
        }
    }

    /**
     * Estimate gas for a transaction (not applicable to Solana).
     */
    public function estimateGas(string $from, string $to, float $amount, array $options = []): ?int
    {
        // Solana doesn't use gas in the traditional sense
        // It uses compute units and transaction fees
        return null;
    }

    /**
     * Get SPL token balance for an address.
     */
    public function getTokenBalance(string $address, string $tokenAddress): ?float
    {
        $this->ensureConnected();

        try {
            $response = $this->client->post('', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getTokenAccountsByOwner',
                    'params' => [
                        $address,
                        ['mint' => $tokenAddress],
                        ['encoding' => 'jsonParsed']
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \Exception('Solana RPC Error: ' . $data['error']['message']);
            }

            $accounts = $data['result']['value'] ?? [];

            if (empty($accounts)) {
                return 0.0;
            }

            // Return balance from the first token account
            $tokenInfo = $accounts[0]['account']['data']['parsed']['info'] ?? [];
            $balance = $tokenInfo['tokenAmount']['uiAmount'] ?? 0;

            return (float)$balance;
        } catch (RequestException $e) {
            throw new \Exception('Failed to get token balance: ' . $e->getMessage());
        }
    }

    /**
     * Get Solana network information.
     */
    public function getNetworkInfo(): ?array
    {
        $this->ensureConnected();

        try {
            $response = $this->client->post('', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getEpochInfo'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \Exception('Solana RPC Error: ' . $data['error']['message']);
            }

            return $data['result'] ?? [];
        } catch (RequestException $e) {
            throw new \Exception('Failed to get network info: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the driver is connected before performing operations.
     */
    private function ensureConnected(): void
    {
        if ($this->client === null) {
            throw new ConfigurationException('Solana driver is not connected. Please call connect() first.');
        }
    }
}
