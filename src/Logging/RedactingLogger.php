<?php

declare(strict_types=1);

namespace Blockchain\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * RedactingLogger
 *
 * A PSR-3 compliant logger that automatically redacts sensitive information
 * from log messages and context data before passing them to an underlying logger.
 *
 * This logger acts as a decorator/wrapper around any PSR-3 logger and ensures
 * that sensitive fields like private keys, secrets, passwords, and API tokens
 * are masked before being logged.
 *
 * Default Redacted Fields:
 * - private_key, privateKey, private-key
 * - secret, client_secret, api_secret
 * - password, pass, pwd
 * - token, api_key, apiKey, access_token
 * - seed, mnemonic, seed_phrase
 * - signature (in some contexts)
 *
 * @package Blockchain\Logging
 *
 * @example
 * ```php
 * use Blockchain\Logging\RedactingLogger;
 * use Monolog\Logger;
 * use Monolog\Handler\StreamHandler;
 *
 * // Create underlying logger
 * $monolog = new Logger('blockchain');
 * $monolog->pushHandler(new StreamHandler('app.log', Logger::INFO));
 *
 * // Wrap with redacting logger
 * $logger = new RedactingLogger($monolog);
 *
 * // This will be redacted
 * $logger->info('Transaction signed', [
 *     'transaction_id' => 'tx123',
 *     'private_key' => '0x1234...5678',  // Will be masked
 *     'amount' => 1000
 * ]);
 * ```
 */
class RedactingLogger extends AbstractLogger
{
    /**
     * The underlying PSR-3 logger to delegate to after redaction.
     */
    private LoggerInterface $logger;

    /**
     * List of field names to redact (case-insensitive).
     *
     * @var array<string>
     */
    private array $redactedFields;

    /**
     * The replacement string for redacted values.
     */
    private string $redactionMask;

    /**
     * Whether to perform deep redaction in nested arrays.
     */
    private bool $deepRedaction;

    /**
     * Create a new RedactingLogger instance.
     *
     * @param LoggerInterface $logger The underlying logger to delegate to
     * @param array<string> $additionalFields Additional fields to redact beyond defaults
     * @param string $redactionMask The mask to use for redacted values
     * @param bool $deepRedaction Whether to perform deep redaction in nested arrays
     */
    public function __construct(
        LoggerInterface $logger,
        array $additionalFields = [],
        string $redactionMask = '***REDACTED***',
        bool $deepRedaction = true
    ) {
        $this->logger = $logger;
        $this->redactionMask = $redactionMask;
        $this->deepRedaction = $deepRedaction;

        // Default sensitive fields to redact
        $defaultFields = [
            'private_key',
            'privateKey',
            'private-key',
            'secret',
            'client_secret',
            'clientSecret',
            'api_secret',
            'apiSecret',
            'password',
            'pass',
            'pwd',
            'token',
            'api_key',
            'apiKey',
            'api-key',
            'access_token',
            'accessToken',
            'refresh_token',
            'refreshToken',
            'seed',
            'mnemonic',
            'seed_phrase',
            'seedPhrase',
            'recovery_phrase',
            'recoveryPhrase',
            'signature',
            'auth_token',
            'authToken',
            'bearer',
            'authorization',
        ];

        $this->redactedFields = array_merge($defaultFields, $additionalFields);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level The log level
     * @param string|\Stringable $message The log message
     * @param array<string, mixed> $context The log context
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Redact sensitive information from context
        $redactedContext = $this->redactContext($context);

        // Redact sensitive information from message if it contains interpolation
        $redactedMessage = $this->redactMessage((string) $message, $redactedContext);

        // Delegate to underlying logger
        $this->logger->log($level, $redactedMessage, $redactedContext);
    }

    /**
     * Redact sensitive fields from context array.
     *
     * @param array<string, mixed> $context The context array
     *
     * @return array<string, mixed> The redacted context
     */
    private function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->shouldRedactField($key)) {
                $redacted[$key] = $this->redactionMask;
            } elseif ($this->deepRedaction && is_array($value)) {
                $redacted[$key] = $this->redactContext($value);
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                // Convert object to string but don't redact the string itself
                $redacted[$key] = (string) $value;
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Redact sensitive information from the log message.
     *
     * This method scans the message for patterns that might contain sensitive data
     * and redacts them. It looks for common patterns like:
     * - private_key=value
     * - "secret": "value"
     * - password: value
     *
     * @param string $message The log message
     * @param array<string, mixed> $context The redacted context (for reference)
     *
     * @return string The redacted message
     */
    private function redactMessage(string $message, array $context): string
    {
        // Build pattern to match sensitive fields in the message
        $pattern = $this->buildRedactionPattern();

        if ($pattern === null) {
            return $message;
        }

        // Replace matches with redaction mask
        $redacted = preg_replace_callback(
            $pattern,
            function ($matches) {
                // $matches[1] = field name, $matches[2] = separator, $matches[3] = quote (if any)
                return $matches[1] . $matches[2] . ($matches[3] ?? '') . $this->redactionMask . ($matches[3] ?? '');
            },
            $message
        );

        return $redacted ?? $message;
    }

    /**
     * Build a regex pattern to match sensitive fields in log messages.
     *
     * @return string|null The regex pattern or null if no fields to redact
     */
    private function buildRedactionPattern(): ?string
    {
        if (empty($this->redactedFields)) {
            return null;
        }

        // Escape field names for regex
        $escapedFields = array_map(fn($field) => preg_quote($field, '/'), $this->redactedFields);
        $fieldsPattern = implode('|', $escapedFields);

        // Match patterns like:
        // field=value, field: value, "field": "value", field => value
        return '/\b(' . $fieldsPattern . ')(\s*[:=]\s*|"\s*:\s*)("|\')?([^,\s}\]"\'\n]+)/i';
    }

    /**
     * Check if a field name should be redacted.
     *
     * @param string $fieldName The field name to check
     *
     * @return bool True if the field should be redacted
     */
    private function shouldRedactField(string $fieldName): bool
    {
        $lowerFieldName = strtolower($fieldName);

        foreach ($this->redactedFields as $redactedField) {
            if (strtolower($redactedField) === $lowerFieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of redacted field names.
     *
     * @return array<string> The list of field names that are redacted
     */
    public function getRedactedFields(): array
    {
        return $this->redactedFields;
    }

    /**
     * Add additional fields to the redaction list.
     *
     * @param array<string> $fields Field names to add to the redaction list
     *
     * @return void
     */
    public function addRedactedFields(array $fields): void
    {
        $this->redactedFields = array_merge($this->redactedFields, $fields);
    }

    /**
     * Get the underlying logger instance.
     *
     * @return LoggerInterface The underlying logger
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
