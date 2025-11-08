<?php

declare(strict_types=1);

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Blockchain\Operations\Idempotency;
use Blockchain\Operations\TransactionQueue;
use Blockchain\Operations\TransactionJob;
use Blockchain\Storage\InMemoryIdempotencyStore;
use Blockchain\Storage\IdempotencyStoreInterface;

/**
 * IdempotencyTest
 *
 * Comprehensive test suite for the idempotency token generation and storage
 * adapter system. Tests cover secure token generation, deterministic derivation,
 * in-memory store behavior, and queue duplicate prevention.
 *
 * Follows TDD principles and SEC-001 security requirements.
 */
class IdempotencyTest extends TestCase
{
    /**
     * Test random token generation produces unique tokens
     */
    public function testGenerateRandomTokensAreUnique(): void
    {
        $token1 = Idempotency::generate();
        $token2 = Idempotency::generate();

        $this->assertNotEquals($token1, $token2, 'Random tokens should be unique');
        $this->assertIsString($token1);
        $this->assertIsString($token2);
    }

    /**
     * Test random tokens have correct length
     */
    public function testRandomTokenLength(): void
    {
        $token = Idempotency::generate();

        // 32 bytes = 64 hex characters
        $this->assertEquals(64, strlen($token), 'Token should be 64 hex characters (32 bytes)');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token, 'Token should be valid hex');
    }

    /**
     * Test deterministic token generation with hint
     */
    public function testGenerateDeterministicToken(): void
    {
        $hint = 'wallet123|payload456';

        $token1 = Idempotency::generate($hint);
        $token2 = Idempotency::generate($hint);

        $this->assertEquals($token1, $token2, 'Same hint should produce same token');
        $this->assertEquals(64, strlen($token1), 'Deterministic token should be 64 characters');
    }

    /**
     * Test different hints produce different tokens
     */
    public function testDifferentHintsProduceDifferentTokens(): void
    {
        $token1 = Idempotency::generate('hint1');
        $token2 = Idempotency::generate('hint2');

        $this->assertNotEquals($token1, $token2, 'Different hints should produce different tokens');
    }

    /**
     * Test in-memory store record and has methods
     */
    public function testInMemoryStoreRecordAndHas(): void
    {
        $store = new InMemoryIdempotencyStore();

        $token = 'test-token-123';
        $context = ['jobId' => 'job-1', 'timestamp' => time()];

        // Initially token should not exist
        $this->assertFalse($store->has($token), 'Token should not exist initially');

        // Record the token
        $store->record($token, $context);

        // Now token should exist
        $this->assertTrue($store->has($token), 'Token should exist after recording');
    }

    /**
     * Test in-memory store can store multiple tokens
     */
    public function testInMemoryStoreMultipleTokens(): void
    {
        $store = new InMemoryIdempotencyStore();

        $store->record('token1', ['jobId' => 'job-1']);
        $store->record('token2', ['jobId' => 'job-2']);
        $store->record('token3', ['jobId' => 'job-3']);

        $this->assertTrue($store->has('token1'));
        $this->assertTrue($store->has('token2'));
        $this->assertTrue($store->has('token3'));
        $this->assertFalse($store->has('token4'));

        $this->assertEquals(3, $store->count());
    }

    /**
     * Test in-memory store getContext method
     */
    public function testInMemoryStoreGetContext(): void
    {
        $store = new InMemoryIdempotencyStore();

        $context = ['jobId' => 'job-1', 'attempts' => 1];
        $store->record('token1', $context);

        $retrievedContext = $store->getContext('token1');
        $this->assertEquals($context, $retrievedContext);

        $missingContext = $store->getContext('nonexistent');
        $this->assertNull($missingContext);
    }

    /**
     * Test in-memory store clear method
     */
    public function testInMemoryStoreClear(): void
    {
        $store = new InMemoryIdempotencyStore();

        $store->record('token1', ['jobId' => 'job-1']);
        $store->record('token2', ['jobId' => 'job-2']);

        $this->assertEquals(2, $store->count());

        $store->clear();

        $this->assertEquals(0, $store->count());
        $this->assertFalse($store->has('token1'));
        $this->assertFalse($store->has('token2'));
    }

    /**
     * Test queue prevents enqueueing duplicates with idempotency store
     */
    public function testQueuePreventsDuplicates(): void
    {
        $store = new InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            idempotencyStore: $store
        );

        $token = Idempotency::generate('unique-job-1');

        // Create two jobs with the same idempotency token
        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token // Same token
        );

        // Enqueue first job
        $queue->enqueue($job1);
        $this->assertEquals(1, $queue->size(), 'First job should be enqueued');

        // Attempt to enqueue duplicate
        $queue->enqueue($job2);
        $this->assertEquals(1, $queue->size(), 'Duplicate job should be skipped');
    }

    /**
     * Test queue allows jobs without idempotency tokens
     */
    public function testQueueAllowsJobsWithoutTokens(): void
    {
        $store = new InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            idempotencyStore: $store
        );

        // Jobs without idempotency tokens should be enqueued normally
        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456']
            // No idempotency token
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x789', 'amount' => 2.0],
            metadata: ['from' => '0xabc']
            // No idempotency token
        );

        $queue->enqueue($job1);
        $queue->enqueue($job2);

        $this->assertEquals(2, $queue->size(), 'Jobs without tokens should both be enqueued');
    }

    /**
     * Test queue works without idempotency store
     */
    public function testQueueWorksWithoutStore(): void
    {
        $queue = new TransactionQueue(); // No store configured

        $token = Idempotency::generate('job-1');

        // Create two jobs with the same token
        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        // Without store, both jobs should be enqueued (no duplicate detection)
        $queue->enqueue($job1);
        $queue->enqueue($job2);

        $this->assertEquals(2, $queue->size(), 'Without store, duplicates should be allowed');
    }

    /**
     * Test queue with different tokens allows multiple jobs
     */
    public function testQueueAllowsDifferentTokens(): void
    {
        $store = new InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            idempotencyStore: $store
        );

        $token1 = Idempotency::generate('job-1');
        $token2 = Idempotency::generate('job-2');

        $job1 = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token1
        );

        $job2 = new TransactionJob(
            id: 'job-2',
            payload: ['to' => '0x789', 'amount' => 2.0],
            metadata: ['from' => '0xabc'],
            idempotencyToken: $token2
        );

        $queue->enqueue($job1);
        $queue->enqueue($job2);

        $this->assertEquals(2, $queue->size(), 'Jobs with different tokens should both be enqueued');
    }

    /**
     * Test store records context on enqueue
     */
    public function testStoreRecordsContextOnEnqueue(): void
    {
        $store = new InMemoryIdempotencyStore();
        $queue = new TransactionQueue(
            idempotencyStore: $store
        );

        $token = Idempotency::generate('job-1');

        $job = new TransactionJob(
            id: 'job-1',
            payload: ['to' => '0x123', 'amount' => 1.0],
            metadata: ['from' => '0x456'],
            idempotencyToken: $token
        );

        $queue->enqueue($job);

        // Verify context was recorded
        $context = $store->getContext($token);
        $this->assertNotNull($context);
        $this->assertEquals('job-1', $context['jobId']);
        $this->assertArrayHasKey('enqueuedAt', $context);
        $this->assertArrayHasKey('attempts', $context);
    }

    /**
     * Test token generation is secure (entropy check)
     */
    public function testRandomTokensHaveSufficientEntropy(): void
    {
        $tokens = [];
        $numTokens = 100;

        for ($i = 0; $i < $numTokens; $i++) {
            $tokens[] = Idempotency::generate();
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount($numTokens, $uniqueTokens, 'All random tokens should be unique');

        // Tokens should have varied characters (not all same character)
        foreach ($tokens as $token) {
            $uniqueChars = count_chars($token, 3);
            $this->assertGreaterThan(5, strlen($uniqueChars), 'Token should have varied characters');
        }
    }
}
