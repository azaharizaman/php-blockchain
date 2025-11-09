<?php

declare(strict_types=1);

namespace Blockchain\Audit;

/**
 * NullAuditRecorder
 *
 * A no-op audit recorder that discards all audit events.
 *
 * This recorder is useful for testing or environments where audit
 * logging is explicitly disabled. It implements AuditRecorderInterface
 * but does nothing with the audit events.
 *
 * @package Blockchain\Audit
 *
 * @example
 * ```php
 * use Blockchain\Audit\NullAuditRecorder;
 *
 * $recorder = new NullAuditRecorder();
 * $recorder->record('key.created', 'user-123', ['key_id' => 'abc']);
 * // Event is discarded
 * ```
 */
class NullAuditRecorder implements AuditRecorderInterface
{
    /**
     * Record an audit event (no-op).
     *
     * @param string $eventType The type of event
     * @param string $actor The identifier of the actor performing the action
     * @param array<string, mixed> $context Additional context about the event
     *
     * @return void
     */
    public function record(string $eventType, string $actor, array $context = []): void
    {
        // No-op: discard all audit events
    }

    /**
     * Retrieve audit events for a specific time range (always returns empty).
     *
     * @param \DateTimeInterface $startTime The start of the time range
     * @param \DateTimeInterface $endTime The end of the time range
     * @param string|null $eventType Optional filter by event type
     * @param string|null $actor Optional filter by actor
     *
     * @return array<int, array<string, mixed>> Always returns an empty array
     */
    public function getEvents(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?string $eventType = null,
        ?string $actor = null
    ): array {
        return [];
    }

    /**
     * Purge audit events older than a specified date (always returns 0).
     *
     * @param \DateTimeInterface $before Purge events older than this date
     *
     * @return int Always returns 0 (no events purged)
     */
    public function purgeOldEvents(\DateTimeInterface $before): int
    {
        return 0;
    }
}
