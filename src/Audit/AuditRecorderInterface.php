<?php

declare(strict_types=1);

namespace Blockchain\Audit;

/**
 * AuditRecorderInterface
 *
 * Contract for audit event recorders that track critical operations
 * in the blockchain integration layer.
 *
 * Audit events should be recorded for:
 * - Key lifecycle operations (creation, import, rotation, deletion)
 * - Idempotency key operations (creation, invalidation)
 * - Critical configuration changes
 * - Security-sensitive operations (authentication, authorization)
 * - Transaction operations (send, batch, queue operations)
 *
 * Implementations must ensure:
 * - Events are recorded reliably (persisted to storage)
 * - Sensitive data is not included in audit logs
 * - Events include sufficient context for troubleshooting
 * - Timestamps are accurate and consistent
 *
 * @package Blockchain\Audit
 */
interface AuditRecorderInterface
{
    /**
     * Record an audit event.
     *
     * @param string $eventType The type of event (e.g., 'key.created', 'key.rotated', 'transaction.sent')
     * @param string $actor The identifier of the actor performing the action (user ID, API key, service name)
     * @param array<string, mixed> $context Additional context about the event (should not include secrets)
     *
     * @return void
     */
    public function record(string $eventType, string $actor, array $context = []): void;

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
    ): array;

    /**
     * Purge audit events older than a specified date.
     *
     * This method is used for audit log retention policies.
     *
     * @param \DateTimeInterface $before Purge events older than this date
     *
     * @return int The number of events purged
     */
    public function purgeOldEvents(\DateTimeInterface $before): int;
}
