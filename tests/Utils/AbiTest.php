<?php

declare(strict_types=1);

namespace Blockchain\Tests\Utils;

use Blockchain\Utils\Abi;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Abi utility class.
 *
 * Verifies ABI encoding and decoding functionality for Ethereum
 * smart contract interactions with known test vectors.
 */
class AbiTest extends TestCase
{
    /**
     * Test getFunctionSelector with known ERC-20 function signatures.
     */
    public function testGetFunctionSelectorBalanceOf(): void
    {
        $selector = Abi::getFunctionSelector('balanceOf(address)');
        $this->assertEquals('0x70a08231', $selector);
    }

    public function testGetFunctionSelectorTransfer(): void
    {
        $selector = Abi::getFunctionSelector('transfer(address,uint256)');
        $this->assertEquals('0xa9059cbb', $selector);
    }

    public function testGetFunctionSelectorApprove(): void
    {
        $selector = Abi::getFunctionSelector('approve(address,uint256)');
        $this->assertEquals('0x095ea7b3', $selector);
    }

    public function testGetFunctionSelectorTransferFrom(): void
    {
        $selector = Abi::getFunctionSelector('transferFrom(address,address,uint256)');
        $this->assertEquals('0x23b872dd', $selector);
    }

    /**
     * Test getFunctionSelector with ERC-721 function signatures.
     */
    public function testGetFunctionSelectorSafeTransferFrom(): void
    {
        $selector = Abi::getFunctionSelector('safeTransferFrom(address,address,uint256)');
        $this->assertEquals('0x42842e0e', $selector);
    }

    public function testGetFunctionSelectorOwnerOf(): void
    {
        $selector = Abi::getFunctionSelector('ownerOf(uint256)');
        $this->assertEquals('0x6352211e', $selector);
    }

    /**
     * Test encodeFunctionCall with balanceOf.
     */
    public function testEncodeFunctionCallBalanceOf(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $encoded = Abi::encodeFunctionCall('balanceOf(address)', [$address]);
        
        // Should be: selector (0x70a08231) + padded address
        $this->assertStringStartsWith('0x70a08231', $encoded);
        $this->assertEquals(74, strlen($encoded)); // 0x + 8 chars (selector) + 64 chars (address)
        
        // Verify address is properly padded
        $expectedAddress = '000000000000000000000000742d35cc6634c0532925a3b844bc9e7595f0beb';
        $this->assertStringContainsString($expectedAddress, strtolower($encoded));
    }

    /**
     * Test encodeFunctionCall with transfer.
     */
    public function testEncodeFunctionCallTransfer(): void
    {
        $to = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $amount = '1000000000000000000'; // 1 token with 18 decimals
        $encoded = Abi::encodeFunctionCall('transfer(address,uint256)', [$to, $amount]);
        
        // Should be: selector (0xa9059cbb) + padded address + padded amount
        $this->assertStringStartsWith('0xa9059cbb', $encoded);
        $this->assertEquals(138, strlen($encoded)); // 0x + 8 + 64 + 64
    }

    /**
     * Test encodeFunctionCall with transferFrom (multiple addresses).
     */
    public function testEncodeFunctionCallTransferFrom(): void
    {
        $from = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $to = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';
        $amount = '500000000000000000'; // 0.5 token with 18 decimals
        
        $encoded = Abi::encodeFunctionCall(
            'transferFrom(address,address,uint256)',
            [$from, $to, $amount]
        );
        
        // Should be: selector + 3 parameters (each 64 chars)
        $this->assertStringStartsWith('0x23b872dd', $encoded);
        $this->assertEquals(202, strlen($encoded)); // 0x + 8 + 64 + 64 + 64
    }

    /**
     * Test encoding uint256 parameter.
     */
    public function testEncodeUint256Small(): void
    {
        $encoded = Abi::encodeFunctionCall('setValue(uint256)', ['1000']);
        
        // Extract the encoded uint256 (skip selector)
        $uint256Hex = substr($encoded, 10); // Skip 0x and 8-char selector
        
        // Should be padded to 64 characters
        $this->assertEquals(64, strlen($uint256Hex));
        
        // Should end with 03e8 (1000 in hex)
        $this->assertStringEndsWith('03e8', $uint256Hex);
    }

    public function testEncodeUint256Large(): void
    {
        // Large number: 1 ETH in wei
        $encoded = Abi::encodeFunctionCall('setValue(uint256)', ['1000000000000000000']);
        
        $uint256Hex = substr($encoded, 10);
        $this->assertEquals(64, strlen($uint256Hex));
        
        // 1000000000000000000 = 0de0b6b3a7640000 in hex
        $this->assertStringEndsWith('de0b6b3a7640000', $uint256Hex);
    }

    public function testEncodeUint256Zero(): void
    {
        $encoded = Abi::encodeFunctionCall('setValue(uint256)', ['0']);
        
        $uint256Hex = substr($encoded, 10);
        $this->assertEquals(64, strlen($uint256Hex));
        $this->assertEquals(str_repeat('0', 64), $uint256Hex);
    }

    /**
     * Test encoding address parameter.
     */
    public function testEncodeAddressWithPrefix(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $encoded = Abi::encodeFunctionCall('setAddress(address)', [$address]);
        
        $addressHex = substr($encoded, 10);
        $this->assertEquals(64, strlen($addressHex));
        
        // Should have 24 leading zeros (12 bytes) + 40 chars address
        $this->assertStringStartsWith('000000000000000000000000', $addressHex);
        $this->assertStringEndsWith('742d35cc6634c0532925a3b844bc9e7595f0beb', strtolower($addressHex));
    }

    public function testEncodeAddressWithoutPrefix(): void
    {
        $address = '742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $encoded = Abi::encodeFunctionCall('setAddress(address)', [$address]);
        
        $addressHex = substr($encoded, 10);
        $this->assertEquals(64, strlen($addressHex));
        $this->assertStringEndsWith('742d35cc6634c0532925a3b844bc9e7595f0beb', strtolower($addressHex));
    }

    /**
     * Test encoding boolean parameter.
     */
    public function testEncodeBoolTrue(): void
    {
        $encoded = Abi::encodeFunctionCall('setValue(bool)', [true]);
        
        $boolHex = substr($encoded, 10);
        $this->assertEquals(64, strlen($boolHex));
        
        // True should be encoded as 1
        $this->assertEquals(str_repeat('0', 63) . '1', $boolHex);
    }

    public function testEncodeBoolFalse(): void
    {
        $encoded = Abi::encodeFunctionCall('setValue(bool)', [false]);
        
        $boolHex = substr($encoded, 10);
        $this->assertEquals(64, strlen($boolHex));
        
        // False should be encoded as 0
        $this->assertEquals(str_repeat('0', 64), $boolHex);
    }

    /**
     * Test encoding string parameter.
     */
    public function testEncodeStringSimple(): void
    {
        $encoded = Abi::encodeFunctionCall('setName(string)', ['Alice']);
        
        // Should contain length (5) and hex-encoded "Alice"
        $this->assertStringContainsString('0000000000000000000000000000000000000000000000000000000000000005', $encoded);
        $this->assertStringContainsString('416c696365', strtolower($encoded)); // "Alice" in hex
    }

    /**
     * Test decoding uint256.
     */
    public function testDecodeUint256Small(): void
    {
        // 1000 in hex, padded to 32 bytes
        $hex = '0x00000000000000000000000000000000000000000000000000000000000003e8';
        $decoded = Abi::decodeResponse('uint256', $hex);
        
        $this->assertEquals('1000', $decoded);
    }

    public function testDecodeUint256Large(): void
    {
        // 1 ETH in wei (1000000000000000000)
        $hex = '0x0000000000000000000000000000000000000000000000000de0b6b3a7640000';
        $decoded = Abi::decodeResponse('uint256', $hex);
        
        $this->assertEquals('1000000000000000000', $decoded);
    }

    public function testDecodeUint256Zero(): void
    {
        $hex = '0x0000000000000000000000000000000000000000000000000000000000000000';
        $decoded = Abi::decodeResponse('uint256', $hex);
        
        $this->assertEquals('0', $decoded);
    }

    public function testDecodeUint256WithoutPrefix(): void
    {
        // Without 0x prefix
        $hex = '00000000000000000000000000000000000000000000000000000000000003e8';
        $decoded = Abi::decodeResponse('uint256', $hex);
        
        $this->assertEquals('1000', $decoded);
    }

    /**
     * Test decoding address.
     */
    public function testDecodeAddress(): void
    {
        // Padded address
        $hex = '0x000000000000000000000000742d35cc6634c0532925a3b844bc9e7595f0beb';
        $decoded = Abi::decodeResponse('address', $hex);
        
        $this->assertEquals('0x742d35cc6634c0532925a3b844bc9e7595f0beb', $decoded);
    }

    public function testDecodeAddressWithoutPrefix(): void
    {
        // Without 0x prefix
        $hex = '000000000000000000000000742d35cc6634c0532925a3b844bc9e7595f0beb';
        $decoded = Abi::decodeResponse('address', $hex);
        
        $this->assertEquals('0x742d35cc6634c0532925a3b844bc9e7595f0beb', $decoded);
    }

    /**
     * Test decoding boolean.
     */
    public function testDecodeBoolTrue(): void
    {
        $hex = '0x0000000000000000000000000000000000000000000000000000000000000001';
        $decoded = Abi::decodeResponse('bool', $hex);
        
        $this->assertTrue($decoded);
    }

    public function testDecodeBoolFalse(): void
    {
        $hex = '0x0000000000000000000000000000000000000000000000000000000000000000';
        $decoded = Abi::decodeResponse('bool', $hex);
        
        $this->assertFalse($decoded);
    }

    public function testDecodeBoolNonZero(): void
    {
        // Any non-zero value should decode to true
        $hex = '0x0000000000000000000000000000000000000000000000000000000000000064'; // 100
        $decoded = Abi::decodeResponse('bool', $hex);
        
        $this->assertTrue($decoded);
    }

    /**
     * Test decoding string.
     */
    public function testDecodeStringSimple(): void
    {
        // Length (5) + "Alice" in hex
        $hex = '0x0000000000000000000000000000000000000000000000000000000000000005416c69636500000000000000000000000000000000000000000000000000000';
        $decoded = Abi::decodeResponse('string', $hex);
        
        $this->assertEquals('Alice', $decoded);
    }

    /**
     * Test ERC-20 helper methods.
     */
    public function testEncodeBalanceOf(): void
    {
        $address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $encoded = Abi::encodeBalanceOf($address);
        
        // Should match encodeFunctionCall result
        $expected = Abi::encodeFunctionCall('balanceOf(address)', [$address]);
        $this->assertEquals($expected, $encoded);
        
        // Should start with balanceOf selector
        $this->assertStringStartsWith('0x70a08231', $encoded);
    }

    public function testEncodeTransfer(): void
    {
        $to = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
        $amount = '1000000000000000000';
        $encoded = Abi::encodeTransfer($to, $amount);
        
        // Should match encodeFunctionCall result
        $expected = Abi::encodeFunctionCall('transfer(address,uint256)', [$to, $amount]);
        $this->assertEquals($expected, $encoded);
        
        // Should start with transfer selector
        $this->assertStringStartsWith('0xa9059cbb', $encoded);
    }

    /**
     * Test error handling for unsupported types.
     */
    public function testEncodeUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type');
        
        Abi::encodeFunctionCall('setValue(bytes32)', ['0x1234']);
    }

    public function testDecodeUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported return type');
        
        Abi::decodeResponse('bytes32', '0x1234567890abcdef');
    }

    /**
     * Test with real-world ERC-20 balanceOf call.
     */
    public function testRealWorldBalanceOfEncoding(): void
    {
        // Known test vector from Ethereum
        $address = '0x0000000000000000000000000000000000000000';
        $encoded = Abi::encodeBalanceOf($address);
        
        // balanceOf selector + zero address
        $expected = '0x70a082310000000000000000000000000000000000000000000000000000000000000000';
        $this->assertEquals($expected, $encoded);
    }

    /**
     * Test with real-world ERC-20 transfer call.
     */
    public function testRealWorldTransferEncoding(): void
    {
        // Transfer 100 tokens (with 18 decimals)
        $to = '0x0000000000000000000000000000000000000000';
        $amount = '100000000000000000000'; // 100 * 10^18
        $encoded = Abi::encodeTransfer($to, $amount);
        
        // transfer selector + zero address + amount
        $this->assertStringStartsWith('0xa9059cbb', $encoded);
        $this->assertStringContainsString('0000000000000000000000000000000000000000000000000000000000000000', $encoded);
        
        // Amount: 100 * 10^18 = 0x56bc75e2d63100000 in hex
        $this->assertStringContainsString('56bc75e2d63100000', strtolower($encoded));
    }

    /**
     * Test integer types as parameters.
     */
    public function testEncodeIntegerAsParameter(): void
    {
        // Test with integer (not string)
        $encoded = Abi::encodeFunctionCall('setValue(uint256)', [1000]);
        
        $uint256Hex = substr($encoded, 10);
        $this->assertEquals(64, strlen($uint256Hex));
        $this->assertStringEndsWith('03e8', $uint256Hex);
    }

    /**
     * Test empty function signature.
     */
    public function testEncodeFunctionCallNoParameters(): void
    {
        $encoded = Abi::encodeFunctionCall('totalSupply()', []);
        
        // totalSupply() selector is 0x18160ddd
        $this->assertEquals('0x18160ddd', $encoded);
    }
}
