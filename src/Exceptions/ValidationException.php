<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * ValidationException is thrown when input validation fails (addresses, amounts, etc.).
 *
 * This exception is used when:
 * - Invalid blockchain addresses are provided
 * - Transaction amounts are invalid or out of range
 * - Driver class validation fails
 * - Input parameters don't meet required constraints
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\Registry\DriverRegistry;
 * use Blockchain\Exceptions\ValidationException;
 *
 * try {
 *     $registry = new DriverRegistry();
 *     // Trying to register a class that doesn't implement the interface
 *     $registry->registerDriver('invalid', 'NonExistentClass');
 * } catch (ValidationException $e) {
 *     echo "Validation error: " . $e->getMessage();
 * }
 * ```
 */
class ValidationException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    private array $errors = [];

    /**
     * Set validation errors.
     *
     * @param array<string, mixed> $errors Array of validation errors
     * @return self
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Get validation errors.
     *
     * @return array<string, mixed> Array of validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
