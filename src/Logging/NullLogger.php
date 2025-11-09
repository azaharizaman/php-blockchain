<?php

declare(strict_types=1);

namespace Blockchain\Logging;

use Psr\Log\AbstractLogger;

/**
 * NullLogger
 *
 * A no-op PSR-3 logger that discards all log messages.
 *
 * This logger is useful for testing, non-production environments,
 * or cases where logging is explicitly disabled. It implements
 * the PSR-3 LoggerInterface but does nothing with the log messages.
 *
 * @package Blockchain\Logging
 *
 * @example
 * ```php
 * use Blockchain\Logging\NullLogger;
 *
 * $logger = new NullLogger();
 * $logger->info('This will not be logged anywhere');
 * $logger->error('Neither will this');
 * ```
 */
class NullLogger extends AbstractLogger
{
    /**
     * Logs with an arbitrary level.
     *
     * This implementation discards all log messages.
     *
     * @param mixed $level The log level
     * @param string|\Stringable $message The log message
     * @param array<string, mixed> $context The log context
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // No-op: discard all log messages
    }
}
