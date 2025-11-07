<?php

declare(strict_types=1);

namespace Blockchain\Tests\Exceptions;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\UnsupportedDriverException;
use Blockchain\Exceptions\ValidationException;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for all exception classes.
 *
 * Verifies that all custom exceptions:
 * - Can be instantiated
 * - Extend the base Exception class
 * - Can be caught by type
 * - Preserve exception messages
 * - Implement additional helper methods correctly
 */
class ExceptionTest extends TestCase
{
    /**
     * Test that ConfigurationException can be instantiated and thrown.
     */
    public function testConfigurationExceptionCanBeInstantiated(): void
    {
        $message = 'Configuration is invalid';
        $exception = new ConfigurationException($message);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * Test that ConfigurationException extends Exception.
     */
    public function testConfigurationExceptionExtendsException(): void
    {
        $exception = new ConfigurationException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test that ConfigurationException can be caught by its specific type.
     */
    public function testConfigurationExceptionCanBeCaughtByType(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('No driver configured');

        throw new ConfigurationException('No driver configured');
    }

    /**
     * Test that UnsupportedDriverException can be instantiated and thrown.
     */
    public function testUnsupportedDriverExceptionCanBeInstantiated(): void
    {
        $message = 'Driver not found';
        $exception = new UnsupportedDriverException($message);

        $this->assertInstanceOf(UnsupportedDriverException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * Test that UnsupportedDriverException extends Exception.
     */
    public function testUnsupportedDriverExceptionExtendsException(): void
    {
        $exception = new UnsupportedDriverException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test that UnsupportedDriverException can be caught by its specific type.
     */
    public function testUnsupportedDriverExceptionCanBeCaughtByType(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('ethereum driver not supported');

        throw new UnsupportedDriverException('ethereum driver not supported');
    }

    /**
     * Test that TransactionException can be instantiated and thrown.
     */
    public function testTransactionExceptionCanBeInstantiated(): void
    {
        $message = 'Transaction failed';
        $exception = new TransactionException($message);

        $this->assertInstanceOf(TransactionException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * Test that TransactionException extends Exception.
     */
    public function testTransactionExceptionExtendsException(): void
    {
        $exception = new TransactionException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test that TransactionException can be caught by its specific type.
     */
    public function testTransactionExceptionCanBeCaughtByType(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Insufficient funds');

        throw new TransactionException('Insufficient funds');
    }

    /**
     * Test that TransactionException can store and retrieve transaction hash.
     */
    public function testTransactionExceptionCanStoreTransactionHash(): void
    {
        $hash = '0x1234567890abcdef';
        $exception = new TransactionException('Transaction failed');
        $exception->setTransactionHash($hash);

        $this->assertSame($hash, $exception->getTransactionHash());
    }

    /**
     * Test that TransactionException returns null when no hash is set.
     */
    public function testTransactionExceptionReturnsNullWhenNoHashSet(): void
    {
        $exception = new TransactionException('Transaction failed');

        $this->assertNull($exception->getTransactionHash());
    }

    /**
     * Test that TransactionException setTransactionHash returns self for fluent interface.
     */
    public function testTransactionExceptionSetTransactionHashReturnsself(): void
    {
        $exception = new TransactionException('Transaction failed');
        $result = $exception->setTransactionHash('0xhash');

        $this->assertSame($exception, $result);
    }

    /**
     * Test that ValidationException can be instantiated and thrown.
     */
    public function testValidationExceptionCanBeInstantiated(): void
    {
        $message = 'Validation failed';
        $exception = new ValidationException($message);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * Test that ValidationException extends Exception.
     */
    public function testValidationExceptionExtendsException(): void
    {
        $exception = new ValidationException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * Test that ValidationException can be caught by its specific type.
     */
    public function testValidationExceptionCanBeCaughtByType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid address format');

        throw new ValidationException('Invalid address format');
    }

    /**
     * Test that ValidationException can store and retrieve validation errors.
     */
    public function testValidationExceptionCanStoreErrors(): void
    {
        $errors = [
            'address' => 'Invalid format',
            'amount' => 'Must be positive'
        ];
        $exception = new ValidationException('Validation failed');
        $exception->setErrors($errors);

        $this->assertSame($errors, $exception->getErrors());
    }

    /**
     * Test that ValidationException returns empty array when no errors are set.
     */
    public function testValidationExceptionReturnsEmptyArrayWhenNoErrorsSet(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertSame([], $exception->getErrors());
    }

    /**
     * Test that ValidationException setErrors returns self for fluent interface.
     */
    public function testValidationExceptionSetErrorsReturnsSelf(): void
    {
        $exception = new ValidationException('Validation failed');
        $result = $exception->setErrors(['field' => 'error']);

        $this->assertSame($exception, $result);
    }

    /**
     * Test that all exceptions preserve exception codes.
     */
    public function testExceptionsPreserveExceptionCodes(): void
    {
        $code = 500;

        $configException = new ConfigurationException('Test', $code);
        $this->assertSame($code, $configException->getCode());

        $driverException = new UnsupportedDriverException('Test', $code);
        $this->assertSame($code, $driverException->getCode());

        $transactionException = new TransactionException('Test', $code);
        $this->assertSame($code, $transactionException->getCode());

        $validationException = new ValidationException('Test', $code);
        $this->assertSame($code, $validationException->getCode());
    }

    /**
     * Test that exceptions are autoloadable.
     */
    public function testExceptionsAreAutoloadable(): void
    {
        $this->assertTrue(class_exists(ConfigurationException::class));
        $this->assertTrue(class_exists(UnsupportedDriverException::class));
        $this->assertTrue(class_exists(TransactionException::class));
        $this->assertTrue(class_exists(ValidationException::class));
    }

    /**
     * Test that exceptions can be caught as generic Exception.
     */
    public function testExceptionsCanBeCaughtAsGenericException(): void
    {
        $caught = false;

        try {
            throw new ConfigurationException('Test');
        } catch (Exception $e) {
            $caught = true;
            $this->assertInstanceOf(ConfigurationException::class, $e);
        }

        $this->assertTrue($caught, 'Exception should be caught as generic Exception');
    }
}
