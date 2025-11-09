<?php

declare(strict_types=1);

namespace Blockchain\Tests\Logging;

use Blockchain\Logging\RedactingLogger;
use Blockchain\Logging\NullLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Test suite for RedactingLogger.
 *
 * Verifies that the RedactingLogger correctly:
 * - Implements PSR-3 LoggerInterface
 * - Redacts sensitive fields from context
 * - Redacts sensitive patterns from messages
 * - Supports custom redaction fields
 * - Handles nested arrays in context
 * - Does not leak secrets in logs
 */
class RedactingLoggerTest extends TestCase
{
    /**
     * Test that RedactingLogger can be instantiated with a logger.
     */
    public function testRedactingLoggerCanBeInstantiated(): void
    {
        $underlying = new NullLogger();
        $logger = new RedactingLogger($underlying);

        $this->assertInstanceOf(RedactingLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * Test that RedactingLogger redacts private_key from context.
     */
    public function testRedactingLoggerRedactsPrivateKeyFromContext(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->info('Transaction signed', [
            'transaction_id' => 'tx-123',
            'private_key' => '0x1234567890abcdef',
            'amount' => 1000,
        ]);

        $this->assertCount(1, $messages);
        $this->assertSame('Transaction signed', $messages[0]['message']);
        $this->assertSame('tx-123', $messages[0]['context']['transaction_id']);
        $this->assertSame('***REDACTED***', $messages[0]['context']['private_key']);
        $this->assertSame(1000, $messages[0]['context']['amount']);
    }

    /**
     * Test that RedactingLogger redacts various secret fields.
     */
    public function testRedactingLoggerRedactsMultipleSensitiveFields(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->error('Authentication failed', [
            'username' => 'john_doe',
            'password' => 'super_secret_password',
            'api_key' => 'ak_1234567890',
            'token' => 'tok_abcdefghij',
            'client_secret' => 'cs_secret123',
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('john_doe', $context['username']);
        $this->assertSame('***REDACTED***', $context['password']);
        $this->assertSame('***REDACTED***', $context['api_key']);
        $this->assertSame('***REDACTED***', $context['token']);
        $this->assertSame('***REDACTED***', $context['client_secret']);
    }

    /**
     * Test redaction of mnemonic and seed phrases.
     */
    public function testRedactingLoggerRedactsMnemonicAndSeedPhrase(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->warning('Wallet recovery attempted', [
            'wallet_id' => 'wallet-abc',
            'mnemonic' => 'abandon ability able about above absent absorb abstract absurd abuse access accident',
            'seed_phrase' => 'another seed phrase here',
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('wallet-abc', $context['wallet_id']);
        $this->assertSame('***REDACTED***', $context['mnemonic']);
        $this->assertSame('***REDACTED***', $context['seed_phrase']);
    }

    /**
     * Test that RedactingLogger handles nested arrays with deep redaction.
     */
    public function testRedactingLoggerRedactsNestedArrays(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->info('Nested config', [
            'user' => [
                'name' => 'Alice',
                'credentials' => [
                    'username' => 'alice',
                    'password' => 'alice_password',
                ],
            ],
            'api' => [
                'endpoint' => 'https://api.example.com',
                'api_key' => 'secret_api_key',
            ],
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('Alice', $context['user']['name']);
        $this->assertSame('alice', $context['user']['credentials']['username']);
        $this->assertSame('***REDACTED***', $context['user']['credentials']['password']);
        $this->assertSame('https://api.example.com', $context['api']['endpoint']);
        $this->assertSame('***REDACTED***', $context['api']['api_key']);
    }

    /**
     * Test that RedactingLogger supports custom redaction fields.
     */
    public function testRedactingLoggerSupportsCustomRedactionFields(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger, ['custom_secret', 'internal_token']);
        $logger->info('Custom fields', [
            'public_data' => 'visible',
            'custom_secret' => 'should_be_redacted',
            'internal_token' => 'also_redacted',
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('visible', $context['public_data']);
        $this->assertSame('***REDACTED***', $context['custom_secret']);
        $this->assertSame('***REDACTED***', $context['internal_token']);
    }

    /**
     * Test that RedactingLogger can use a custom redaction mask.
     */
    public function testRedactingLoggerSupportsCustomRedactionMask(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger, [], '[HIDDEN]');
        $logger->info('Custom mask', [
            'password' => 'secret123',
        ]);

        $this->assertCount(1, $messages);
        $this->assertSame('[HIDDEN]', $messages[0]['context']['password']);
    }

    /**
     * Test that RedactingLogger handles case-insensitive field matching.
     */
    public function testRedactingLoggerHandlesCaseInsensitiveFields(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->info('Case variations', [
            'Private_Key' => 'key1',
            'PRIVATE_KEY' => 'key2',
            'private_key' => 'key3',
            'privateKey' => 'key4',
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('***REDACTED***', $context['Private_Key']);
        $this->assertSame('***REDACTED***', $context['PRIVATE_KEY']);
        $this->assertSame('***REDACTED***', $context['private_key']);
        $this->assertSame('***REDACTED***', $context['privateKey']);
    }

    /**
     * Test that RedactingLogger redacts sensitive data in messages.
     */
    public function testRedactingLoggerRedactsDataInMessages(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->info('User login with password=secret123 and api_key=ak_12345');

        $this->assertCount(1, $messages);
        $message = $messages[0]['message'];

        // The message should have sensitive patterns redacted
        $this->assertStringNotContainsString('secret123', $message);
        $this->assertStringNotContainsString('ak_12345', $message);
        $this->assertStringContainsString('***REDACTED***', $message);
    }

    /**
     * Test that RedactingLogger can add additional redacted fields dynamically.
     */
    public function testRedactingLoggerCanAddRedactedFieldsDynamically(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->addRedactedFields(['custom_field', 'another_field']);

        $logger->info('Dynamic fields', [
            'normal_field' => 'visible',
            'custom_field' => 'should_be_redacted',
            'another_field' => 'also_redacted',
        ]);

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        $this->assertSame('visible', $context['normal_field']);
        $this->assertSame('***REDACTED***', $context['custom_field']);
        $this->assertSame('***REDACTED***', $context['another_field']);
    }

    /**
     * Test that RedactingLogger returns the list of redacted fields.
     */
    public function testRedactingLoggerReturnsRedactedFields(): void
    {
        $underlying = new NullLogger();
        $logger = new RedactingLogger($underlying, ['custom_field']);

        $fields = $logger->getRedactedFields();

        $this->assertIsArray($fields);
        $this->assertContains('private_key', $fields);
        $this->assertContains('password', $fields);
        $this->assertContains('custom_field', $fields);
    }

    /**
     * Test that RedactingLogger returns the underlying logger.
     */
    public function testRedactingLoggerReturnsUnderlyingLogger(): void
    {
        $underlying = new NullLogger();
        $logger = new RedactingLogger($underlying);

        $this->assertSame($underlying, $logger->getLogger());
    }

    /**
     * Test that RedactingLogger works with all PSR-3 log levels.
     */
    public function testRedactingLoggerWorksWithAllLogLevels(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);

        $logger->emergency('Emergency', ['secret' => 'val1']);
        $logger->alert('Alert', ['secret' => 'val2']);
        $logger->critical('Critical', ['secret' => 'val3']);
        $logger->error('Error', ['secret' => 'val4']);
        $logger->warning('Warning', ['secret' => 'val5']);
        $logger->notice('Notice', ['secret' => 'val6']);
        $logger->info('Info', ['secret' => 'val7']);
        $logger->debug('Debug', ['secret' => 'val8']);

        $this->assertCount(8, $messages);

        foreach ($messages as $message) {
            $this->assertSame('***REDACTED***', $message['context']['secret']);
        }
    }

    /**
     * Test that no secrets are exposed when logging exceptions with context.
     */
    public function testNoSecretsExposedInExceptionContext(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);

        try {
            // Simulate an operation that uses secrets
            $privateKey = '0x1234567890abcdef';
            $password = 'my_secret_password';

            // Log the error without exposing secrets
            $logger->error('Operation failed', [
                'error' => 'Connection timeout',
                'private_key' => $privateKey,
                'password' => $password,
            ]);
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: ' . $e->getMessage());
        }

        $this->assertCount(1, $messages);
        $context = $messages[0]['context'];

        // Verify secrets are redacted
        $this->assertSame('***REDACTED***', $context['private_key']);
        $this->assertSame('***REDACTED***', $context['password']);

        // Verify non-secret data is preserved
        $this->assertSame('Connection timeout', $context['error']);
    }

    /**
     * Test that RedactingLogger handles empty context.
     */
    public function testRedactingLoggerHandlesEmptyContext(): void
    {
        $messages = [];
        $mockLogger = $this->createMockLogger($messages);

        $logger = new RedactingLogger($mockLogger);
        $logger->info('Simple message');

        $this->assertCount(1, $messages);
        $this->assertSame('Simple message', $messages[0]['message']);
        $this->assertEmpty($messages[0]['context']);
    }

    /**
     * Create a mock logger that records log calls.
     *
     * @param array<int, array<string, mixed>> &$messages Reference to array to store messages
     *
     * @return LoggerInterface Mock logger instance
     */
    private function createMockLogger(array &$messages): LoggerInterface
    {
        return new class($messages) implements LoggerInterface {
            private array $messages;

            public function __construct(array &$messages)
            {
                $this->messages = &$messages;
            }

            public function emergency(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::EMERGENCY, $message, $context);
            }

            public function alert(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ALERT, $message, $context);
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::CRITICAL, $message, $context);
            }

            public function error(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ERROR, $message, $context);
            }

            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::WARNING, $message, $context);
            }

            public function notice(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::NOTICE, $message, $context);
            }

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::INFO, $message, $context);
            }

            public function debug(string|\Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::DEBUG, $message, $context);
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
