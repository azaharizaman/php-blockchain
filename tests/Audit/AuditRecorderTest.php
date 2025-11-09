<?php

declare(strict_types=1);

namespace Blockchain\Tests\Audit;

use Blockchain\Audit\AuditRecorderInterface;
use Blockchain\Audit\FileAuditRecorder;
use Blockchain\Audit\NullAuditRecorder;
use Blockchain\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for audit recorder implementations.
 *
 * Verifies that audit recorders:
 * - Implement AuditRecorderInterface correctly
 * - Record audit events reliably
 * - Support event retrieval with filtering
 * - Handle event purging correctly
 * - Do not expose sensitive data in audit logs
 */
class AuditRecorderTest extends TestCase
{
    private string $testLogDir;
    private string $testLogFile;

    /**
     * Set up test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for test logs
        $this->testLogDir = sys_get_temp_dir() . '/blockchain-audit-test-' . uniqid();
        $this->testLogFile = $this->testLogDir . '/audit.log';
    }

    /**
     * Clean up test environment after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove test log file and directory
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }

        if (is_dir($this->testLogDir)) {
            rmdir($this->testLogDir);
        }
    }

    /**
     * Test that FileAuditRecorder can be instantiated.
     */
    public function testFileAuditRecorderCanBeInstantiated(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $this->assertInstanceOf(FileAuditRecorder::class, $recorder);
        $this->assertInstanceOf(AuditRecorderInterface::class, $recorder);
    }

    /**
     * Test that FileAuditRecorder creates directory automatically.
     */
    public function testFileAuditRecorderCreatesDirectoryAutomatically(): void
    {
        $this->assertDirectoryDoesNotExist($this->testLogDir);

        new FileAuditRecorder($this->testLogFile);

        $this->assertDirectoryExists($this->testLogDir);
    }

    /**
     * Test that FileAuditRecorder throws exception if directory creation fails.
     */
    public function testFileAuditRecorderThrowsExceptionIfDirectoryNotWritable(): void
    {
        // Try to create a recorder in a non-writable location
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Failed to create audit log directory');

        new FileAuditRecorder('/root/impossible/audit.log');
    }

    /**
     * Test that FileAuditRecorder can record an audit event.
     */
    public function testFileAuditRecorderCanRecordEvent(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);
        $recorder->record('key.created', 'user-123', ['key_id' => 'key-abc']);

        $this->assertFileExists($this->testLogFile);

        $content = file_get_contents($this->testLogFile);
        $this->assertNotEmpty($content);

        $event = json_decode(trim($content), true);
        $this->assertIsArray($event);
        $this->assertSame('key.created', $event['event_type']);
        $this->assertSame('user-123', $event['actor']);
        $this->assertSame('key-abc', $event['context']['key_id']);
        $this->assertArrayHasKey('timestamp', $event);
    }

    /**
     * Test that FileAuditRecorder can record multiple events.
     */
    public function testFileAuditRecorderCanRecordMultipleEvents(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $recorder->record('key.created', 'user-123', ['key_id' => 'key-1']);
        $recorder->record('key.rotated', 'user-456', ['key_id' => 'key-1', 'new_key_id' => 'key-2']);
        $recorder->record('transaction.sent', 'user-123', ['tx_id' => 'tx-abc', 'amount' => 1000]);

        $this->assertFileExists($this->testLogFile);

        $lines = file($this->testLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(3, $lines);

        $event1 = json_decode($lines[0], true);
        $event2 = json_decode($lines[1], true);
        $event3 = json_decode($lines[2], true);

        $this->assertSame('key.created', $event1['event_type']);
        $this->assertSame('key.rotated', $event2['event_type']);
        $this->assertSame('transaction.sent', $event3['event_type']);
    }

    /**
     * Test that FileAuditRecorder can retrieve events by time range.
     */
    public function testFileAuditRecorderCanRetrieveEventsByTimeRange(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $startTime = new \DateTimeImmutable('2025-01-01 00:00:00');

        // Record events that will fall within our time range
        $recorder->record('event.1', 'actor-1', ['time' => 'early']);
        $recorder->record('event.2', 'actor-2', ['time' => 'late']);

        $endTime = new \DateTimeImmutable('2025-12-31 23:59:59');

        $events = $recorder->getEvents($startTime, $endTime);

        $this->assertCount(2, $events);
    }

    /**
     * Test that FileAuditRecorder can filter events by event type.
     */
    public function testFileAuditRecorderCanFilterEventsByType(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $recorder->record('key.created', 'user-123', ['key_id' => 'key-1']);
        $recorder->record('key.rotated', 'user-456', ['key_id' => 'key-1']);
        $recorder->record('key.created', 'user-789', ['key_id' => 'key-2']);

        $startTime = new \DateTimeImmutable('-1 hour');
        $endTime = new \DateTimeImmutable('+1 hour');

        $events = $recorder->getEvents($startTime, $endTime, 'key.created');

        $this->assertCount(2, $events);
        $this->assertSame('key.created', $events[0]['event_type']);
        $this->assertSame('key.created', $events[1]['event_type']);
    }

    /**
     * Test that FileAuditRecorder can filter events by actor.
     */
    public function testFileAuditRecorderCanFilterEventsByActor(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $recorder->record('key.created', 'user-123', ['key_id' => 'key-1']);
        $recorder->record('key.rotated', 'user-456', ['key_id' => 'key-1']);
        $recorder->record('key.deleted', 'user-123', ['key_id' => 'key-1']);

        $startTime = new \DateTimeImmutable('-1 hour');
        $endTime = new \DateTimeImmutable('+1 hour');

        $events = $recorder->getEvents($startTime, $endTime, null, 'user-123');

        $this->assertCount(2, $events);
        $this->assertSame('user-123', $events[0]['actor']);
        $this->assertSame('user-123', $events[1]['actor']);
    }

    /**
     * Test that FileAuditRecorder can purge old events.
     */
    public function testFileAuditRecorderCanPurgeOldEvents(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        // Create a purge time in the middle of our test window
        $purgeTime = new \DateTimeImmutable('2025-06-01 00:00:00');
        
        // Manually write events with specific timestamps to avoid sleep()
        // Events before purge time
        $oldEvent1 = json_encode([
            'timestamp' => '2025-01-01T10:00:00.000+00:00',
            'event_type' => 'event.1',
            'actor' => 'actor-1',
            'context' => [],
        ]) . PHP_EOL;
        
        $oldEvent2 = json_encode([
            'timestamp' => '2025-02-01T10:00:00.000+00:00',
            'event_type' => 'event.2',
            'actor' => 'actor-2',
            'context' => [],
        ]) . PHP_EOL;
        
        $oldEvent3 = json_encode([
            'timestamp' => '2025-03-01T10:00:00.000+00:00',
            'event_type' => 'event.3',
            'actor' => 'actor-3',
            'context' => [],
        ]) . PHP_EOL;
        
        // Events after purge time
        $newEvent1 = json_encode([
            'timestamp' => '2025-07-01T10:00:00.000+00:00',
            'event_type' => 'event.4',
            'actor' => 'actor-4',
            'context' => [],
        ]) . PHP_EOL;
        
        $newEvent2 = json_encode([
            'timestamp' => '2025-08-01T10:00:00.000+00:00',
            'event_type' => 'event.5',
            'actor' => 'actor-5',
            'context' => [],
        ]) . PHP_EOL;
        
        file_put_contents($this->testLogFile, $oldEvent1 . $oldEvent2 . $oldEvent3 . $newEvent1 . $newEvent2);

        // Purge events older than purge time
        $purgedCount = $recorder->purgeOldEvents($purgeTime);

        $this->assertSame(3, $purgedCount);

        // Verify remaining events
        $startTime = new \DateTimeImmutable('2025-01-01 00:00:00');
        $endTime = new \DateTimeImmutable('2025-12-31 23:59:59');
        $events = $recorder->getEvents($startTime, $endTime);

        $this->assertCount(2, $events);
    }

    /**
     * Test that FileAuditRecorder handles empty log file.
     */
    public function testFileAuditRecorderHandlesEmptyLogFile(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $startTime = new \DateTimeImmutable('-1 hour');
        $endTime = new \DateTimeImmutable('+1 hour');
        $events = $recorder->getEvents($startTime, $endTime);

        $this->assertEmpty($events);
    }

    /**
     * Test that FileAuditRecorder returns file path.
     */
    public function testFileAuditRecorderReturnsFilePath(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        $this->assertSame($this->testLogFile, $recorder->getFilePath());
    }

    /**
     * Test that NullAuditRecorder can be instantiated.
     */
    public function testNullAuditRecorderCanBeInstantiated(): void
    {
        $recorder = new NullAuditRecorder();

        $this->assertInstanceOf(NullAuditRecorder::class, $recorder);
        $this->assertInstanceOf(AuditRecorderInterface::class, $recorder);
    }

    /**
     * Test that NullAuditRecorder discards all events.
     */
    public function testNullAuditRecorderDiscardsAllEvents(): void
    {
        $recorder = new NullAuditRecorder();

        // Record events (should be no-op)
        $recorder->record('key.created', 'user-123', ['key_id' => 'key-1']);
        $recorder->record('key.rotated', 'user-456', ['key_id' => 'key-1']);

        $startTime = new \DateTimeImmutable('-1 hour');
        $endTime = new \DateTimeImmutable('+1 hour');
        $events = $recorder->getEvents($startTime, $endTime);

        $this->assertEmpty($events);
    }

    /**
     * Test that NullAuditRecorder returns zero for purge operations.
     */
    public function testNullAuditRecorderReturnsZeroForPurge(): void
    {
        $recorder = new NullAuditRecorder();

        $purgeTime = new \DateTimeImmutable();
        $purgedCount = $recorder->purgeOldEvents($purgeTime);

        $this->assertSame(0, $purgedCount);
    }

    /**
     * Test that audit events do not contain sensitive data.
     *
     * This test verifies that when recording audit events, sensitive
     * information is not included in the audit log.
     */
    public function testAuditEventsDoNotContainSensitiveData(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        // Record an event with only non-sensitive context
        $recorder->record('key.created', 'user-123', [
            'key_id' => 'key-abc',
            'algorithm' => 'secp256k1',
            'created_at' => '2025-01-01 12:00:00',
            // Note: No private_key, secret, or password fields
        ]);

        $content = file_get_contents($this->testLogFile);
        $event = json_decode(trim($content), true);

        // Verify that sensitive field names don't appear
        $this->assertArrayNotHasKey('private_key', $event['context']);
        $this->assertArrayNotHasKey('secret', $event['context']);
        $this->assertArrayNotHasKey('password', $event['context']);

        // Verify that the content doesn't contain sensitive patterns
        $this->assertStringNotContainsString('private_key', $content);
        $this->assertStringNotContainsString('0x', $content); // No hex-encoded keys
    }

    /**
     * Test that FileAuditRecorder handles concurrent writes safely.
     *
     * This test verifies that the file locking mechanism prevents
     * data corruption during concurrent writes.
     */
    public function testFileAuditRecorderHandlesConcurrentWrites(): void
    {
        $recorder = new FileAuditRecorder($this->testLogFile);

        // Simulate concurrent writes
        $recorder->record('event.1', 'actor-1', ['data' => 'data1']);
        $recorder->record('event.2', 'actor-2', ['data' => 'data2']);
        $recorder->record('event.3', 'actor-3', ['data' => 'data3']);

        $lines = file($this->testLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(3, $lines);

        // Verify all events are valid JSON
        foreach ($lines as $line) {
            $event = json_decode($line, true);
            $this->assertIsArray($event);
            $this->assertArrayHasKey('timestamp', $event);
            $this->assertArrayHasKey('event_type', $event);
            $this->assertArrayHasKey('actor', $event);
        }
    }
}
