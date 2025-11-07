<?php

declare(strict_types=1);

namespace Blockchain\Tests\Utils;

use Blockchain\Utils\AddressValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for AddressValidator utility class.
 *
 * Verifies address validation and normalization functionality
 * for various blockchain networks.
 */
class AddressValidatorTest extends TestCase
{
    /**
     * Test isValid() with valid Solana addresses.
     */
    public function testIsValidWithValidSolanaAddresses(): void
    {
        // Valid minimum length Solana address (32 characters)
        $this->assertTrue(
            AddressValidator::isValid('11111111111111111111111111111111', 'solana')
        );

        // Valid 44-character Solana address (typical length)
        $this->assertTrue(
            AddressValidator::isValid('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM', 'solana')
        );

        // Valid 43-character Solana address
        $this->assertTrue(
            AddressValidator::isValid('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', 'solana')
        );

        // Test with default network (solana)
        $this->assertTrue(
            AddressValidator::isValid('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM')
        );
    }

    /**
     * Test isValid() with various valid Solana address lengths.
     */
    public function testIsValidWithVariousLengths(): void
    {
        // 32 characters (minimum)
        $address32 = str_repeat('1', 32);
        $this->assertTrue(AddressValidator::isValid($address32, 'solana'));

        // 44 characters (maximum)
        $address44 = str_repeat('A', 44);
        $this->assertTrue(AddressValidator::isValid($address44, 'solana'));

        // 38 characters (middle range)
        $address38 = str_repeat('B', 38);
        $this->assertTrue(AddressValidator::isValid($address38, 'solana'));
    }

    /**
     * Test isValid() with addresses that are too short.
     */
    public function testIsValidWithTooShortAddresses(): void
    {
        // Too short (31 characters)
        $this->assertFalse(
            AddressValidator::isValid(str_repeat('1', 31), 'solana')
        );

        // Very short
        $this->assertFalse(
            AddressValidator::isValid('123', 'solana')
        );

        // Empty string
        $this->assertFalse(
            AddressValidator::isValid('', 'solana')
        );
    }

    /**
     * Test isValid() with addresses that are too long.
     */
    public function testIsValidWithTooLongAddresses(): void
    {
        // Too long (45 characters)
        $this->assertFalse(
            AddressValidator::isValid(str_repeat('1', 45), 'solana')
        );

        // Much too long
        $this->assertFalse(
            AddressValidator::isValid(str_repeat('A', 100), 'solana')
        );
    }

    /**
     * Test isValid() with invalid characters.
     */
    public function testIsValidWithInvalidCharacters(): void
    {
        // Contains '0' (not in base58)
        $this->assertFalse(
            AddressValidator::isValid('0000000000000000000000000000000000', 'solana')
        );

        // Contains 'O' (not in base58)
        $this->assertFalse(
            AddressValidator::isValid('OOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOO', 'solana')
        );

        // Contains 'I' (not in base58)
        $this->assertFalse(
            AddressValidator::isValid('IIIIIIIIIIIIIIIIIIIIIIIIIIIIIIII', 'solana')
        );

        // Contains 'l' (lowercase L, not in base58)
        $this->assertFalse(
            AddressValidator::isValid('llllllllllllllllllllllllllllllll', 'solana')
        );

        // Contains special characters
        $this->assertFalse(
            AddressValidator::isValid('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGY@#$%', 'solana')
        );

        // Contains spaces
        $this->assertFalse(
            AddressValidator::isValid('9WzDXwBb mkg8ZTbN MqUxvQRA yrZzDsGY', 'solana')
        );
    }

    /**
     * Test isValid() with unsupported networks.
     */
    public function testIsValidWithUnsupportedNetworks(): void
    {
        $address = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';

        $this->assertFalse(AddressValidator::isValid($address, 'ethereum'));
        $this->assertFalse(AddressValidator::isValid($address, 'bitcoin'));
        $this->assertFalse(AddressValidator::isValid($address, 'unknown'));
    }

    /**
     * Test isValid() with case variations in network name.
     */
    public function testIsValidWithCaseVariationsInNetworkName(): void
    {
        $address = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';

        $this->assertTrue(AddressValidator::isValid($address, 'solana'));
        $this->assertTrue(AddressValidator::isValid($address, 'Solana'));
        $this->assertTrue(AddressValidator::isValid($address, 'SOLANA'));
        $this->assertTrue(AddressValidator::isValid($address, 'SoLaNa'));
    }

    /**
     * Test normalize() with whitespace trimming.
     */
    public function testNormalizeWithWhitespace(): void
    {
        $address = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';

        $this->assertEquals(
            $address,
            AddressValidator::normalize('  ' . $address)
        );

        $this->assertEquals(
            $address,
            AddressValidator::normalize($address . '  ')
        );

        $this->assertEquals(
            $address,
            AddressValidator::normalize('  ' . $address . '  ')
        );

        $this->assertEquals(
            $address,
            AddressValidator::normalize("\t" . $address . "\n")
        );
    }

    /**
     * Test normalize() with hex addresses (lowercase conversion).
     */
    public function testNormalizeWithHexAddresses(): void
    {
        // Ethereum-style hex addresses should be converted to lowercase
        $this->assertEquals(
            '0x1234abcdef',
            AddressValidator::normalize('0x1234ABCDEF')
        );

        $this->assertEquals(
            '0xabcdef1234567890',
            AddressValidator::normalize('0xABCDEF1234567890')
        );

        // With whitespace
        $this->assertEquals(
            '0xabcdef',
            AddressValidator::normalize('  0xABCDEF  ')
        );
    }

    /**
     * Test normalize() with non-hex addresses (preserve case).
     */
    public function testNormalizeWithNonHexAddresses(): void
    {
        // Solana addresses should preserve case
        $address = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';
        $this->assertEquals(
            $address,
            AddressValidator::normalize($address)
        );

        // With whitespace
        $this->assertEquals(
            $address,
            AddressValidator::normalize('  ' . $address . '  ')
        );
    }

    /**
     * Test normalize() with empty string.
     */
    public function testNormalizeWithEmptyString(): void
    {
        $this->assertEquals('', AddressValidator::normalize(''));
        $this->assertEquals('', AddressValidator::normalize('   '));
        $this->assertEquals('', AddressValidator::normalize("\t\n"));
    }
}
