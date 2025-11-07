<?php

declare(strict_types=1);

namespace Blockchain\Tests\Utils;

use Blockchain\Utils\Serializer;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Serializer utility class.
 *
 * Verifies JSON and Base64 serialization/deserialization functionality
 * with proper error handling.
 */
class SerializerTest extends TestCase
{
    /**
     * Test toJson() with simple arrays.
     */
    public function testToJsonWithSimpleArrays(): void
    {
        $data = ['name' => 'Alice', 'balance' => 100];
        $json = Serializer::toJson($data);

        $this->assertIsString($json);
        $this->assertJson($json);
        $this->assertEquals('{"name":"Alice","balance":100}', $json);
    }

    /**
     * Test toJson() with nested arrays.
     */
    public function testToJsonWithNestedArrays(): void
    {
        $data = [
            'user' => [
                'name' => 'Bob',
                'address' => [
                    'city' => 'New York',
                    'zip' => '10001'
                ]
            ],
            'balance' => 250.50
        ];

        $json = Serializer::toJson($data);

        $this->assertIsString($json);
        $this->assertJson($json);
        $this->assertStringContainsString('Bob', $json);
        $this->assertStringContainsString('New York', $json);
    }

    /**
     * Test toJson() with special characters.
     */
    public function testToJsonWithSpecialCharacters(): void
    {
        $data = [
            'message' => 'Hello "World"',
            'emoji' => '🚀',
            'unicode' => 'Ñoño'
        ];

        $json = Serializer::toJson($data);

        $this->assertIsString($json);
        $this->assertJson($json);
        
        // Verify it can be decoded back
        $decoded = Serializer::fromJson($json);
        $this->assertEquals($data, $decoded);
    }

    /**
     * Test toJson() with empty array.
     */
    public function testToJsonWithEmptyArray(): void
    {
        $json = Serializer::toJson([]);
        $this->assertEquals('[]', $json);
    }

    /**
     * Test toJson() with numeric arrays.
     */
    public function testToJsonWithNumericArrays(): void
    {
        $data = [1, 2, 3, 4, 5];
        $json = Serializer::toJson($data);

        $this->assertEquals('[1,2,3,4,5]', $json);
    }

    /**
     * Test fromJson() with valid JSON strings.
     */
    public function testFromJsonWithValidJsonStrings(): void
    {
        $json = '{"name":"Alice","balance":100}';
        $data = Serializer::fromJson($json);

        $this->assertIsArray($data);
        $this->assertEquals('Alice', $data['name']);
        $this->assertEquals(100, $data['balance']);
    }

    /**
     * Test fromJson() with nested JSON.
     */
    public function testFromJsonWithNestedJson(): void
    {
        $json = '{"user":{"name":"Bob","age":30},"active":true}';
        $data = Serializer::fromJson($json);

        $this->assertIsArray($data);
        $this->assertIsArray($data['user']);
        $this->assertEquals('Bob', $data['user']['name']);
        $this->assertEquals(30, $data['user']['age']);
        $this->assertTrue($data['active']);
    }

    /**
     * Test fromJson() with empty JSON object.
     */
    public function testFromJsonWithEmptyJsonObject(): void
    {
        $data = Serializer::fromJson('{}');
        $this->assertEquals([], $data);
    }

    /**
     * Test fromJson() with empty JSON array.
     */
    public function testFromJsonWithEmptyJsonArray(): void
    {
        $data = Serializer::fromJson('[]');
        $this->assertEquals([], $data);
    }

    /**
     * Test fromJson() throws exception on invalid JSON.
     */
    public function testFromJsonThrowsExceptionOnInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        Serializer::fromJson('{"invalid": json}');
    }

    /**
     * Test fromJson() throws exception on malformed JSON.
     */
    public function testFromJsonThrowsExceptionOnMalformedJson(): void
    {
        $this->expectException(JsonException::class);

        Serializer::fromJson('{name: "Alice"}');
    }

    /**
     * Test fromJson() throws exception on truncated JSON.
     */
    public function testFromJsonThrowsExceptionOnTruncatedJson(): void
    {
        $this->expectException(JsonException::class);

        Serializer::fromJson('{"name":"Alice"');
    }

    /**
     * Test toBase64() encoding.
     */
    public function testToBase64Encoding(): void
    {
        $data = 'Hello World';
        $encoded = Serializer::toBase64($data);

        $this->assertEquals('SGVsbG8gV29ybGQ=', $encoded);
    }

    /**
     * Test toBase64() with empty string.
     */
    public function testToBase64WithEmptyString(): void
    {
        $encoded = Serializer::toBase64('');
        $this->assertEquals('', $encoded);
    }

    /**
     * Test toBase64() with special characters.
     */
    public function testToBase64WithSpecialCharacters(): void
    {
        $data = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $encoded = Serializer::toBase64($data);

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);
        
        // Verify it can be decoded
        $decoded = Serializer::fromBase64($encoded);
        $this->assertEquals($data, $decoded);
    }

    /**
     * Test fromBase64() decoding.
     */
    public function testFromBase64Decoding(): void
    {
        $encoded = 'SGVsbG8gV29ybGQ=';
        $decoded = Serializer::fromBase64($encoded);

        $this->assertEquals('Hello World', $decoded);
    }

    /**
     * Test fromBase64() with empty string.
     */
    public function testFromBase64WithEmptyString(): void
    {
        $decoded = Serializer::fromBase64('');
        $this->assertEquals('', $decoded);
    }

    /**
     * Test fromBase64() throws exception on invalid Base64.
     */
    public function testFromBase64ThrowsExceptionOnInvalidBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Base64 encoded string');

        Serializer::fromBase64('Invalid!!!Base64@@@');
    }

    /**
     * Test roundtrip conversion (toBase64 + fromBase64).
     */
    public function testBase64RoundtripConversion(): void
    {
        $originalData = 'This is a test string with 🚀 emoji and special chars: !@#$%';
        
        $encoded = Serializer::toBase64($originalData);
        $decoded = Serializer::fromBase64($encoded);

        $this->assertEquals($originalData, $decoded);
    }

    /**
     * Test roundtrip with binary data.
     */
    public function testBase64RoundtripWithBinaryData(): void
    {
        // Create binary data
        $binaryData = pack('C*', 0, 1, 2, 255, 254, 253);
        
        $encoded = Serializer::toBase64($binaryData);
        $decoded = Serializer::fromBase64($encoded);

        $this->assertEquals($binaryData, $decoded);
    }

    /**
     * Test JSON roundtrip conversion (toJson + fromJson).
     */
    public function testJsonRoundtripConversion(): void
    {
        $originalData = [
            'string' => 'test',
            'number' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value']
        ];

        $json = Serializer::toJson($originalData);
        $decoded = Serializer::fromJson($json);

        $this->assertEquals($originalData, $decoded);
    }

    /**
     * Test toJson() with numeric keys preserves structure.
     */
    public function testToJsonWithNumericKeys(): void
    {
        $data = [
            0 => 'first',
            1 => 'second',
            2 => 'third'
        ];

        $json = Serializer::toJson($data);
        $decoded = Serializer::fromJson($json);

        // PHP will decode numeric keys back as array with numeric indices
        $this->assertEquals($data, $decoded);
    }
}
