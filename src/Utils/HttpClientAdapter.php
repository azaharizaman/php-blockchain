<?php

declare(strict_types=1);

namespace Blockchain\Utils;

use Blockchain\Exceptions\ConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * HttpClientAdapter provides a simplified interface for HTTP operations.
 *
 * This class wraps Guzzle HTTP client and provides methods for making
 * GET and POST requests with automatic JSON handling and error conversion.
 *
 * @package Blockchain\Utils
 */
class HttpClientAdapter
{
    /**
     * The Guzzle HTTP client instance.
     */
    private Client $client;

    /**
     * Create a new HttpClientAdapter instance.
     *
     * @param Client|null $client Optional Guzzle client instance. If not provided, creates a default client.
     *
     * @example
     * ```php
     * // Using default client
     * $adapter = new HttpClientAdapter();
     *
     * // Using custom client
     * $client = new Client(['timeout' => 60]);
     * $adapter = new HttpClientAdapter($client);
     * ```
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Perform a GET request.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $options Additional Guzzle request options
     * @return array<string,mixed> The decoded JSON response
     * @throws ConfigurationException If the request fails or response is invalid
     *
     * @example
     * ```php
     * $adapter = new HttpClientAdapter();
     * $data = $adapter->get('https://api.example.com/data');
     * ```
     */
    public function get(string $url, array $options = []): array
    {
        try {
            $response = $this->client->get($url, $options);
            $body = (string) $response->getBody();
            
            return Serializer::fromJson($body);
        } catch (GuzzleException $e) {
            throw $this->convertException($e);
        } catch (\JsonException $e) {
            throw new ConfigurationException(
                'Invalid JSON response from server: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Perform a POST request.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $data The data to send in the request body
     * @param array<string,mixed> $options Additional Guzzle request options
     * @return array<string,mixed> The decoded JSON response
     * @throws ConfigurationException If the request fails or response is invalid
     *
     * @example
     * ```php
     * $adapter = new HttpClientAdapter();
     * $result = $adapter->post('https://api.example.com/submit', [
     *     'name' => 'Alice',
     *     'amount' => 100
     * ]);
     * ```
     */
    public function post(string $url, array $data, array $options = []): array
    {
        try {
            // Merge data into options, preserving user-provided 'json' if present
            $options = array_merge(['json' => $data], $options);
            
            $response = $this->client->post($url, $options);
            $body = (string) $response->getBody();
            
            return Serializer::fromJson($body);
        } catch (GuzzleException $e) {
            throw $this->convertException($e);
        } catch (\JsonException $e) {
            throw new ConfigurationException(
                'Invalid JSON response from server: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Convert Guzzle exceptions to blockchain exceptions.
     *
     * @param GuzzleException $exception The Guzzle exception to convert
     * @return ConfigurationException The converted exception
     */
    private function convertException(GuzzleException $exception): ConfigurationException
    {
        $message = 'HTTP request failed: ' . $exception->getMessage();
        
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $response = $exception->getResponse();
            $statusCode = $response->getStatusCode();
            $message = sprintf(
                'HTTP request failed with status %d: %s',
                $statusCode,
                $exception->getMessage()
            );
        }
        
        return new ConfigurationException($message, 0, $exception);
    }
}
