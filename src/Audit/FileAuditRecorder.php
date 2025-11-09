<?php

declare(strict_types=1);

namespace Blockchain\Audit;

use Blockchain\Exceptions\ConfigurationException;

/**
 * FileAuditRecorder
 *
 * A simple file-based audit event recorder for testing and development.
 *
 * This implementation stores audit events as JSON lines in a file.
 * Each event is stored as a single JSON object on one line, making it
 * easy to parse and process with standard tools.
 *
 * WARNING: This implementation is suitable for development and testing only.
 * For production use, consider a more robust solution like:
 * - Database-backed audit logging
 * - Centralized logging service (e.g., Elasticsearch, Splunk)
 * - Cloud audit logging (e.g., AWS CloudTrail, Azure Monitor)
 *
 * @package Blockchain\Audit
 *
 * @example
 * ```php
 * use Blockchain\Audit\FileAuditRecorder;
 *
 * $recorder = new FileAuditRecorder('/var/log/blockchain-audit.log');
 * $recorder->record('key.created', 'user-123', [
 *     'key_id' => 'key-abc',
 *     'algorithm' => 'secp256k1'
 * ]);
 * ```
 */
class FileAuditRecorder implements AuditRecorderInterface
{
    /**
     * Path to the audit log file.
     */
    private string $filePath;

    /**
     * Whether to automatically create the directory if it doesn't exist.
     */
    private bool $autoCreateDirectory;

    /**
     * Create a new FileAuditRecorder instance.
     *
     * @param string $filePath Path to the audit log file
     * @param bool $autoCreateDirectory Whether to create the directory if it doesn't exist
     *
     * @throws ConfigurationException If the directory doesn't exist and autoCreateDirectory is false
     */
    public function __construct(string $filePath, bool $autoCreateDirectory = true)
    {
        $this->filePath = $filePath;
        $this->autoCreateDirectory = $autoCreateDirectory;

        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if ($autoCreateDirectory) {
                if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new ConfigurationException(
                        "Failed to create audit log directory: {$directory}"
                    );
                }
            } else {
                throw new ConfigurationException(
                    "Audit log directory does not exist: {$directory}"
                );
            }
        }

        if (!is_writable($directory)) {
            throw new ConfigurationException(
                "Audit log directory is not writable: {$directory}"
            );
        }
    }

    /**
     * Record an audit event.
     *
     * @param string $eventType The type of event
     * @param string $actor The identifier of the actor performing the action
     * @param array<string, mixed> $context Additional context about the event
     *
     * @return void
     */
    public function record(string $eventType, string $actor, array $context = []): void
    {
        $event = [
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'event_type' => $eventType,
            'actor' => $actor,
            'context' => $context,
        ];

        $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode audit event as JSON: ' . json_last_error_msg());
        }

        // Append to file with a newline
        $result = file_put_contents($this->filePath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Failed to write audit event to file: {$this->filePath}");
        }
    }

    /**
     * Retrieve audit events for a specific time range.
     *
     * @param \DateTimeInterface $startTime The start of the time range
     * @param \DateTimeInterface $endTime The end of the time range
     * @param string|null $eventType Optional filter by event type
     * @param string|null $actor Optional filter by actor
     *
     * @return array<int, array<string, mixed>> Array of audit events
     */
    public function getEvents(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?string $eventType = null,
        ?string $actor = null
    ): array {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $events = [];
        $handle = fopen($this->filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Failed to open audit log file: {$this->filePath}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $event = json_decode($line, true);
                if ($event === null) {
                    continue; // Skip invalid JSON lines
                }

                // Parse timestamp
                $eventTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $event['timestamp'] ?? '');
                if ($eventTime === false) {
                    continue; // Skip events with invalid timestamps
                }

                // Check time range
                if ($eventTime < $startTime || $eventTime > $endTime) {
                    continue;
                }

                // Filter by event type if specified
                if ($eventType !== null && ($event['event_type'] ?? '') !== $eventType) {
                    continue;
                }

                // Filter by actor if specified
                if ($actor !== null && ($event['actor'] ?? '') !== $actor) {
                    continue;
                }

                $events[] = $event;
            }
        } finally {
            fclose($handle);
        }

        return $events;
    }

    /**
     * Purge audit events older than a specified date.
     *
     * @param \DateTimeInterface $before Purge events older than this date
     *
     * @return int The number of events purged
     */
    public function purgeOldEvents(\DateTimeInterface $before): int
    {
        if (!file_exists($this->filePath)) {
            return 0;
        }

        $tempFile = $this->filePath . '.tmp';
        $handle = fopen($this->filePath, 'r');
        $tempHandle = fopen($tempFile, 'w');

        if ($handle === false || $tempHandle === false) {
            if ($handle !== false) {
                fclose($handle);
            }
            if ($tempHandle !== false) {
                fclose($tempHandle);
            }
            throw new \RuntimeException("Failed to open audit log file for purging: {$this->filePath}");
        }

        $purgedCount = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $event = json_decode($line, true);
                if ($event === null) {
                    // Keep malformed lines
                    fwrite($tempHandle, $line . PHP_EOL);
                    continue;
                }

                // Parse timestamp
                $eventTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $event['timestamp'] ?? '');
                if ($eventTime === false) {
                    // Keep events with invalid timestamps
                    fwrite($tempHandle, $line . PHP_EOL);
                    continue;
                }

                // Check if event should be kept
                if ($eventTime >= $before) {
                    fwrite($tempHandle, $line . PHP_EOL);
                } else {
                    $purgedCount++;
                }
            }
        } finally {
            fclose($handle);
            fclose($tempHandle);
        }

        // Replace original file with temp file
        if (!rename($tempFile, $this->filePath)) {
            unlink($tempFile);
            throw new \RuntimeException("Failed to replace audit log file after purging: {$this->filePath}");
        }

        return $purgedCount;
    }

    /**
     * Get the path to the audit log file.
     *
     * @return string The file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
