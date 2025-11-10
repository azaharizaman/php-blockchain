<?php

declare(strict_types=1);

namespace Blockchain\Utils;

/**
 * ValidationResult is an immutable value object representing endpoint validation results.
 *
 * This class encapsulates the outcome of endpoint validation, including
 * whether the endpoint is valid, the latency measured during validation,
 * and any error messages encountered.
 *
 * @package Blockchain\Utils
 */
class ValidationResult
{
    /**
     * Create a new ValidationResult instance.
     *
     * @param bool $isValid Whether the endpoint is valid
     * @param float|null $latency The latency in seconds (null if not measured)
     * @param string|null $error The error message (null if no error)
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly ?float $latency = null,
        private readonly ?string $error = null
    ) {
    }

    /**
     * Check if the endpoint is valid.
     *
     * @return bool True if the endpoint is valid, false otherwise
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Get the latency measured during validation.
     *
     * @return float|null The latency in seconds, or null if not measured
     */
    public function getLatency(): ?float
    {
        return $this->latency;
    }

    /**
     * Get the error message from validation.
     *
     * @return string|null The error message, or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
