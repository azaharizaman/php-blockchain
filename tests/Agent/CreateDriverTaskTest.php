<?php

declare(strict_types=1);

namespace Blockchain\Tests\Agent;

use Blockchain\Agent\Generator\DriverScaffolder;
use Blockchain\Agent\OperatorConsole;
use Blockchain\Agent\TaskRegistry;
use Blockchain\Agent\Tasks\CreateDriverTask;
use Blockchain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test suite for CreateDriverTask.
 *
 * @package Blockchain\Tests\Agent
 */
class CreateDriverTaskTest extends TestCase
{
    private CreateDriverTask $task;
    private DriverScaffolder $scaffolder;
    private TaskRegistry $registry;
    private OperatorConsole $console;
    private string $projectRoot;
    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test project root
        $this->projectRoot = dirname(__DIR__, 2);
        $this->fixturesPath = $this->projectRoot . '/tests/fixtures/agent';

        // Create mocked console that auto-approves
        $auditLogPath = sys_get_temp_dir() . '/test-audit-' . uniqid() . '.log';
        $this->console = new OperatorConsole($auditLogPath, false);
        $this->console->setSimulatedApproval(true);

        // Create real instances for integration testing
        $this->scaffolder = new DriverScaffolder();
        $this->registry = new TaskRegistry();

        // Create task instance
        $this->task = new CreateDriverTask(
            $this->scaffolder,
            $this->registry,
            $this->console,
            $this->projectRoot
        );
    }

    protected function tearDown(): void
    {
        // Clean up generated test files
        $this->cleanupGeneratedFiles();
        
        Mockery::close();
        parent::tearDown();
    }

    public function testParseSpecificationFromFile(): void
    {
        $specPath = $this->fixturesPath . '/solana-rpc.json';
        
        $spec = $this->scaffolder->parseSpecification($specPath);

        $this->assertIsArray($spec);
        $this->assertEquals('openapi', $spec['type']);
        $this->assertArrayHasKey('methods', $spec);
        $this->assertNotEmpty($spec['methods']);
    }

    public function testParseSpecificationInvalidFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Specification file not found');

        $this->scaffolder->parseSpecification('/nonexistent/file.json');
    }

    public function testGenerateDriverClass(): void
    {
        $specPath = $this->fixturesPath . '/solana-rpc.json';
        $spec = $this->scaffolder->parseSpecification($specPath);

        $code = $this->scaffolder->generateDriverClass(
            'TestChain',
            'non-evm',
            $spec,
            [
                'native_currency' => 'TEST',
                'decimals' => 9,
                'default_endpoint' => 'https://test.example.com'
            ]
        );

        $this->assertStringContainsString('class TestChainDriver implements BlockchainDriverInterface', $code);
        $this->assertStringContainsString('namespace Blockchain\Drivers;', $code);
        $this->assertStringContainsString('public function connect(array $config): void', $code);
        $this->assertStringContainsString('public function getBalance(string $address): float', $code);
        $this->assertStringContainsString('public function sendTransaction(', $code);
        $this->assertStringContainsString('public function getTransaction(string $hash): array', $code);
        $this->assertStringContainsString('public function getBlock(', $code);
        $this->assertStringContainsString('public function estimateGas(', $code);
        $this->assertStringContainsString('public function getTokenBalance(', $code);
        $this->assertStringContainsString('public function getNetworkInfo(', $code);
    }

    public function testGenerateDriverClassEvm(): void
    {
        $spec = [
            'type' => 'openapi',
            'methods' => [
                'eth_getBalance' => ['name' => 'eth_getBalance'],
                'eth_getTransactionByHash' => ['name' => 'eth_getTransactionByHash'],
            ]
        ];

        $code = $this->scaffolder->generateDriverClass(
            'Polygon',
            'evm',
            $spec,
            [
                'native_currency' => 'MATIC',
                'decimals' => 18,
            ]
        );

        $this->assertStringContainsString('class PolygonDriver implements BlockchainDriverInterface', $code);
        $this->assertStringContainsString('private const DECIMALS = 18;', $code);
    }

    public function testGenerateTestClass(): void
    {
        $spec = [
            'type' => 'openapi',
            'methods' => []
        ];

        $code = $this->scaffolder->generateTestClass('TestChain', $spec);

        $this->assertStringContainsString('class TestChainDriverTest extends TestCase', $code);
        $this->assertStringContainsString('namespace Blockchain\Tests\Drivers;', $code);
        $this->assertStringContainsString('use Blockchain\Drivers\TestChainDriver;', $code);
        $this->assertStringContainsString('public function testConnectSuccess(): void', $code);
        $this->assertStringContainsString('public function testGetBalanceSuccess(): void', $code);
        $this->assertStringContainsString('public function testGetTransactionSuccess(): void', $code);
        $this->assertStringContainsString('public function testGetBlockSuccess(): void', $code);
    }

    public function testGenerateDocumentation(): void
    {
        $spec = [
            'type' => 'openapi',
            'methods' => []
        ];

        $doc = $this->scaffolder->generateDocumentation(
            'Bitcoin',
            $spec,
            [
                'native_currency' => 'BTC',
                'default_endpoint' => 'https://bitcoin.example.com'
            ]
        );

        $this->assertStringContainsString('# Bitcoin Driver', $doc);
        $this->assertStringContainsString('BitcoinDriver', $doc);
        $this->assertStringContainsString('BTC', $doc);
        $this->assertStringContainsString('https://bitcoin.example.com', $doc);
        $this->assertStringContainsString('## Basic Usage', $doc);
        $this->assertStringContainsString('### Get Balance', $doc);
    }

    public function testExecuteTaskCreatesFiles(): void
    {
        // Use a unique driver name to avoid conflicts
        $driverName = 'TestDriver' . time();
        
        $inputs = [
            'driver_name' => $driverName,
            'spec_source' => $this->fixturesPath . '/solana-rpc.json',
            'network_type' => 'non-evm',
            'native_currency' => 'TEST',
            'decimals' => 9,
            'default_endpoint' => 'https://test.example.com'
        ];

        $result = $this->task->execute($inputs);

        // Verify result structure
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('files_created', $result);
        $this->assertArrayHasKey('driver_class', $result);
        $this->assertArrayHasKey('test_class', $result);
        $this->assertArrayHasKey('validation_results', $result);
        $this->assertArrayHasKey('next_steps', $result);

        // Verify files were created
        $this->assertFileExists($result['files_created']['driver']);
        $this->assertFileExists($result['files_created']['test']);
        $this->assertFileExists($result['files_created']['documentation']);

        // Verify file contents
        $driverContent = file_get_contents($result['files_created']['driver']);
        $this->assertStringContainsString("class {$driverName}Driver", $driverContent);
        
        $testContent = file_get_contents($result['files_created']['test']);
        $this->assertStringContainsString("class {$driverName}DriverTest", $testContent);

        // Verify validation results
        $this->assertArrayHasKey('syntax_check', $result['validation_results']);
        $this->assertTrue($result['validation_results']['syntax_check']['passed']);

        // Verify next steps
        $this->assertNotEmpty($result['next_steps']);
        $this->assertGreaterThan(3, count($result['next_steps']));
    }

    public function testExecuteTaskDuplicateDriver(): void
    {
        // Try to create a driver that already exists
        $inputs = [
            'driver_name' => 'Solana',  // Already exists in the project
            'spec_source' => $this->fixturesPath . '/solana-rpc.json',
            'network_type' => 'non-evm',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already exists');

        $this->task->execute($inputs);
    }

    public function testExecuteTaskInvalidInputs(): void
    {
        $inputs = [
            'driver_name' => 'Test',
            // Missing required 'spec_source' and 'network_type'
        ];

        $this->expectException(ValidationException::class);

        $this->task->execute($inputs);
    }

    public function testExecuteTaskInvalidDriverName(): void
    {
        $inputs = [
            'driver_name' => 'invalid-name-with-dashes',  // Invalid format
            'spec_source' => $this->fixturesPath . '/solana-rpc.json',
            'network_type' => 'non-evm',
        ];

        $this->expectException(ValidationException::class);

        $this->task->execute($inputs);
    }

    public function testGeneratedCodeHasCorrectStructure(): void
    {
        $specPath = $this->fixturesPath . '/solana-rpc.json';
        $spec = $this->scaffolder->parseSpecification($specPath);

        $driverCode = $this->scaffolder->generateDriverClass(
            'MockChain',
            'non-evm',
            $spec,
            ['decimals' => 9]
        );

        // Check for proper PHP opening tag
        $this->assertStringStartsWith('<?php', $driverCode);

        // Check for strict types declaration
        $this->assertStringContainsString('declare(strict_types=1);', $driverCode);

        // Check for all required interface methods
        $requiredMethods = [
            'connect',
            'getBalance',
            'sendTransaction',
            'getTransaction',
            'getBlock',
            'estimateGas',
            'getTokenBalance',
            'getNetworkInfo'
        ];

        foreach ($requiredMethods as $method) {
            $this->assertStringContainsString("public function {$method}(", $driverCode);
        }

        // Check for private helper methods
        $this->assertStringContainsString('private function rpcCall(', $driverCode);
        $this->assertStringContainsString('private function ensureConnected(', $driverCode);
    }

    public function testGeneratedTestHasComprehensiveCoverage(): void
    {
        $spec = ['type' => 'openapi', 'methods' => []];
        $testCode = $this->scaffolder->generateTestClass('MockChain', $spec);

        // Check for test methods covering all interface methods
        $testMethods = [
            'testConnectSuccess',
            'testConnectMissingEndpoint',
            'testGetBalanceSuccess',
            'testGetBalanceNotConnected',
            'testGetTransactionSuccess',
            'testGetBlockSuccess',
            'testEstimateGas',
            'testGetTokenBalance',
            'testGetNetworkInfo',
        ];

        foreach ($testMethods as $method) {
            $this->assertStringContainsString("public function {$method}()", $testCode);
        }

        // Check for mock setup
        $this->assertStringContainsString('MockHandler', $testCode);
        $this->assertStringContainsString('setUp()', $testCode);
        $this->assertStringContainsString('tearDown()', $testCode);
    }

    /**
     * Clean up generated test files.
     */
    private function cleanupGeneratedFiles(): void
    {
        // Clean up any test drivers created during tests
        $patterns = [
            $this->projectRoot . '/src/Drivers/TestDriver*.php',
            $this->projectRoot . '/src/Drivers/TestChain*.php',
            $this->projectRoot . '/tests/Drivers/TestDriver*Test.php',
            $this->projectRoot . '/tests/Drivers/TestChain*Test.php',
            $this->projectRoot . '/docs/drivers/testdriver*.md',
            $this->projectRoot . '/docs/drivers/testchain*.md',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        // Clean up audit logs
        $auditLogs = glob(sys_get_temp_dir() . '/test-audit-*.log');
        foreach ($auditLogs as $log) {
            @unlink($log);
        }
    }
}

