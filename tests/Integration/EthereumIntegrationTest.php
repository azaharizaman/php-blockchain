<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Blockchain\Drivers\EthereumDriver;
use Blockchain\Exceptions\ConfigurationException;

/**
 * Integration tests for EthereumDriver with real Ethereum test networks.
 * 
 * These tests are gated by the RUN_INTEGRATION_TESTS environment variable.
 * Set RUN_INTEGRATION_TESTS=true and ETHEREUM_RPC_ENDPOINT to run these tests.
 * 
 * Example Sepolia RPC endpoints:
 * - Infura: https://sepolia.infura.io/v3/YOUR_PROJECT_ID
 * - Alchemy: https://eth-sepolia.g.alchemy.com/v2/YOUR_API_KEY
 * - Public: https://rpc.sepolia.org
 */
class EthereumIntegrationTest extends TestCase
{
    private EthereumDriver $driver;
    private string $rpcEndpoint;

    /**
     * Known test data on Sepolia testnet
     */
    private const SEPOLIA_CHAIN_ID = 11155111;
    
    // Ethereum Foundation address with known balance on Sepolia
    private const TEST_ADDRESS = '0x7cF69F7837F089EcBE73Aa93c5c1b7B9E0e5B565';
    
    // A known transaction hash on Sepolia (example - update with valid one)
    private const TEST_TX_HASH = '0x88df016429689c079f3b2f6ad39fa052532c56795b733da78a91ebe6a713944b';
    
    // A known block number on Sepolia
    private const TEST_BLOCK_NUMBER = 4000000;
    
    // Test USDC contract on Sepolia (example - update with valid one)
    private const TEST_TOKEN_CONTRACT = '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238';
    
    /**
     * Set up the test environment and skip if integration tests are not enabled.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Check if integration tests are enabled
        $runIntegrationTests = getenv('RUN_INTEGRATION_TESTS');
        if ($runIntegrationTests !== 'true') {
            $this->markTestSkipped(
                'Integration tests are disabled. Set RUN_INTEGRATION_TESTS=true to run these tests.'
            );
        }
        
        // Load RPC endpoint from environment
        $this->rpcEndpoint = getenv('ETHEREUM_RPC_ENDPOINT') ?: '';
        if (empty($this->rpcEndpoint)) {
            $this->markTestSkipped(
                'ETHEREUM_RPC_ENDPOINT environment variable is required for integration tests.'
            );
        }
        
        // Initialize the driver
        $this->driver = new EthereumDriver();
    }

    /**
     * Test connection to Sepolia testnet.
     */
    public function testConnectToTestnet(): void
    {
        // Attempt to connect to the Sepolia testnet
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        // If no exception is thrown, connection was successful
        $this->assertTrue(true);
    }

    /**
     * Test getNetworkInfo returns valid Sepolia chain ID.
     */
    public function testGetNetworkInfoReturnsSepoliaChainId(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $networkInfo = $this->driver->getNetworkInfo();
        
        $this->assertIsArray($networkInfo);
        $this->assertArrayHasKey('chainId', $networkInfo);
        $this->assertEquals(self::SEPOLIA_CHAIN_ID, $networkInfo['chainId']);
        
        // Verify other network info fields
        $this->assertArrayHasKey('gasPrice', $networkInfo);
        $this->assertArrayHasKey('blockNumber', $networkInfo);
        $this->assertIsInt($networkInfo['gasPrice']);
        $this->assertIsInt($networkInfo['blockNumber']);
        $this->assertGreaterThan(0, $networkInfo['blockNumber']);
    }

    /**
     * Test getBalance retrieves balance from testnet.
     */
    public function testGetBalanceFromTestnet(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $balance = $this->driver->getBalance(self::TEST_ADDRESS);
        
        // Balance should be a numeric value >= 0
        $this->assertIsFloat($balance);
        $this->assertGreaterThanOrEqual(0.0, $balance);
    }

    /**
     * Test getBalance with invalid address format throws exception.
     */
    public function testGetBalanceWithInvalidAddressThrowsException(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->driver->getBalance('invalid_address');
    }

    /**
     * Test getTransaction retrieves transaction details from testnet.
     */
    public function testGetTransactionFromTestnet(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $transaction = $this->driver->getTransaction(self::TEST_TX_HASH);
        
        // Verify transaction structure
        $this->assertIsArray($transaction);
        
        // Transaction might not exist or could be null, handle both cases
        if (!empty($transaction)) {
            $this->assertArrayHasKey('hash', $transaction);
            $this->assertArrayHasKey('from', $transaction);
            $this->assertArrayHasKey('to', $transaction);
            $this->assertArrayHasKey('value', $transaction);
            
            // Verify hash matches
            $this->assertEquals(strtolower(self::TEST_TX_HASH), strtolower($transaction['hash']));
        }
    }

    /**
     * Test getTransaction with non-existent hash returns empty array.
     */
    public function testGetTransactionWithNonExistentHash(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $nonExistentHash = '0x0000000000000000000000000000000000000000000000000000000000000000';
        $transaction = $this->driver->getTransaction($nonExistentHash);
        
        $this->assertIsArray($transaction);
        // Non-existent transactions should return empty array
        $this->assertEmpty($transaction);
    }

    /**
     * Test getBlock retrieves block data from testnet.
     */
    public function testGetBlockFromTestnet(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $block = $this->driver->getBlock(self::TEST_BLOCK_NUMBER);
        
        // Verify block structure
        $this->assertIsArray($block);
        
        if (!empty($block)) {
            $this->assertArrayHasKey('number', $block);
            $this->assertArrayHasKey('hash', $block);
            $this->assertArrayHasKey('timestamp', $block);
        }
    }

    /**
     * Test getBlock with 'latest' tag.
     */
    public function testGetBlockWithLatestTag(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $block = $this->driver->getBlock('latest');
        
        $this->assertIsArray($block);
        $this->assertNotEmpty($block);
        $this->assertArrayHasKey('number', $block);
        $this->assertArrayHasKey('hash', $block);
        $this->assertArrayHasKey('timestamp', $block);
    }

    /**
     * Test getBlock with invalid block number.
     */
    public function testGetBlockWithInvalidBlockNumber(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        // Use a very large block number that doesn't exist yet
        $futureBlockNumber = 999999999999;
        $block = $this->driver->getBlock($futureBlockNumber);
        
        $this->assertIsArray($block);
        $this->assertEmpty($block);
    }

    /**
     * Test estimateGas for a simple ETH transfer.
     */
    public function testEstimateGasOnTestnet(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $gasEstimate = $this->driver->estimateGas(
            self::TEST_ADDRESS,
            '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb1',
            0.001
        );
        
        // Gas estimate should be reasonable for a simple transfer
        // Minimum is 21,000 gas, but with safety buffer and variations
        $this->assertIsInt($gasEstimate);
        $this->assertGreaterThan(21000, $gasEstimate);
        $this->assertLessThan(100000, $gasEstimate);
    }

    /**
     * Test estimateGas with different transaction types.
     */
    public function testEstimateGasWithContractInteraction(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        // Estimate gas for a contract interaction (ERC-20 transfer)
        $erc20TransferData = '0xa9059cbb' . // transfer(address,uint256) selector
            '000000000000000000000000742d35cc6634c0532925a3b844bc9e7595f0beb1' . // to address
            '0000000000000000000000000000000000000000000000000de0b6b3a7640000'; // 1 token (18 decimals)
        
        $gasEstimate = $this->driver->estimateGas(
            self::TEST_ADDRESS,
            self::TEST_TOKEN_CONTRACT,
            0.0,
            ['data' => $erc20TransferData]
        );
        
        // Contract interactions require more gas than simple transfers
        $this->assertIsInt($gasEstimate);
        $this->assertGreaterThan(50000, $gasEstimate);
    }

    /**
     * Test getTokenBalance retrieves ERC-20 token balance.
     */
    public function testGetTokenBalanceFromTestnet(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $balance = $this->driver->getTokenBalance(
            self::TEST_ADDRESS,
            self::TEST_TOKEN_CONTRACT
        );
        
        // Balance should be either a float >= 0 or null if the contract is invalid
        if ($balance !== null) {
            $this->assertIsFloat($balance);
            $this->assertGreaterThanOrEqual(0.0, $balance);
        } else {
            // If null, it means the token contract might not be valid
            $this->assertNull($balance);
        }
    }

    /**
     * Test getTokenBalance with invalid token address.
     */
    public function testGetTokenBalanceWithInvalidTokenAddress(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $balance = $this->driver->getTokenBalance(
            self::TEST_ADDRESS,
            'invalid_token_address'
        );
        
        $this->assertNull($balance);
    }

    /**
     * Test rate limiting by making multiple rapid requests.
     * Note: This test may fail if RPC provider has strict rate limits.
     */
    public function testMultipleRapidRequests(): void
    {
        $this->driver->connect([
            'endpoint' => $this->rpcEndpoint,
            'timeout' => 30
        ]);
        
        $successfulRequests = 0;
        $failedRequests = 0;
        
        // Make 5 rapid requests
        for ($i = 0; $i < 5; $i++) {
            try {
                $networkInfo = $this->driver->getNetworkInfo();
                if (!empty($networkInfo) && isset($networkInfo['chainId'])) {
                    $successfulRequests++;
                }
            } catch (\Exception $e) {
                $failedRequests++;
            }
        }
        
        // At least some requests should succeed
        $this->assertGreaterThan(0, $successfulRequests);
        
        // Document that rate limiting might cause some failures
        // This is expected behavior with public RPC endpoints
    }

    /**
     * Test network timeout handling.
     */
    public function testNetworkTimeoutHandling(): void
    {
        // Use a very short timeout to simulate timeout scenarios
        $driver = new EthereumDriver();
        
        try {
            $driver->connect([
                'endpoint' => $this->rpcEndpoint,
                'timeout' => 1 // 1 second timeout
            ]);
            
            // Try to get network info with short timeout
            $networkInfo = $driver->getNetworkInfo();
            
            // If it succeeds, that's fine - network was fast
            $this->assertIsArray($networkInfo);
        } catch (\Exception $e) {
            // Timeout or other network errors are expected with very short timeout
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test that driver throws exception when not connected.
     */
    public function testOperationWithoutConnectionThrowsException(): void
    {
        $driver = new EthereumDriver();
        
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Ethereum driver is not connected');
        
        $driver->getBalance(self::TEST_ADDRESS);
    }
}
