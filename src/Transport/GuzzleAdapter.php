<?php

declare(strict_types=1);

namespace Blockchain\Transport;

use Blockchain\Exceptions\ConfigurationException;
use Blockchain\Exceptions\TransactionException;
use Blockchain\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

/**
 * GuzzleAdapter provides HTTP client functionality using Guzzle.
 *
 * This adapter centralizes HTTP configuration, error handling, and request/response
 * processing for all blockchain drivers. It maps Guzzle exceptions to blockchain
 * exceptions for consistent error handling across the application.
 *
 * @package Blockchain\Transport
 */
class GuzzleAdapter implements HttpClientAdapter
{
    /**
     * The Guzzle HTTP client instance.
     */
    private Client $client;

    /**
     * Default configuration for all requests.
     *
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * Create a new GuzzleAdapter instance.
     *
     * @param Client|null $client Optional Guzzle client instance. If not provided, creates a default client.
     * @param array<string,mixed> $config Optional configuration to merge with defaults
     *
     * @example
     * ```php
     * // Using default configuration
     * $adapter = new GuzzleAdapter();
     *
     * // Using custom client (e.g., for testing with MockHandler)
     * $mockHandler = new MockHandler([new Response(200, [], '{"success": true}')]);
     * $handlerStack = HandlerStack::create($mockHandler);
     * $client = new Client(['handler' => $handlerStack]);
     * $adapter = new GuzzleAdapter($client);
     *
     * // Using custom configuration
     * $adapter = new GuzzleAdapter(null, ['timeout' => 60, 'verify' => false]);
     * ```
     */
    public function __construct(?Client $client = null, array $config = [])
    {
        // Define default configuration
        $defaultConfig = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'verify' => true,
            'http_errors' => false, // We handle errors manually for better control
        ];

        // Merge user config with defaults
        $this->config = array_merge($defaultConfig, $config);

        // Create client if not provided
        if ($client === null) {
            $this->client = new Client($this->config);
        } else {
            $this->client = $client;
        }
    }

    /**
     * Perform a GET request.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $options Additional Guzzle request options
     * @return array<string,mixed> The decoded JSON response
     * @throws ConfigurationException If the request fails due to network/configuration issues
     * @throws ValidationException If the request fails due to client errors (4xx)
     * @throws TransactionException If the request fails due to server errors (5xx)
     *
     * @example
     * ```php
     * $adapter = new GuzzleAdapter();
     * $data = $adapter->get('https://api.example.com/data');
     * $dataWithParams = $adapter->get('https://api.example.com/search', [
     *     'query' => ['q' => 'test', 'limit' => 10]
     * ]);
     * ```
     */
    public function get(string $url, array $options = []): array
    {
        try {
            $response = $this->client->get($url, $options);

            // Check for HTTP errors
            if ($response->getStatusCode() >= 400) {
                $this->handleHttpError($response);
            }

            return $this->parseJsonResponse($response);
        } catch (GuzzleException $e) {
            throw $this->handleException($e);
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
     * @throws ConfigurationException If the request fails due to network/configuration issues
     * @throws ValidationException If the request fails due to client errors (4xx)
     * @throws TransactionException If the request fails due to server errors (5xx)
     *
     * @example
     * ```php
     * $adapter = new GuzzleAdapter();
     * $result = $adapter->post('https://api.example.com/submit', [
     *     'name' => 'Alice',
     *     'amount' => 100
     * ]);
     * ```
     */
    public function post(string $url, array $data, array $options = []): array
    {
        try {
            // Merge data into options as 'json' key
            $options = array_merge(['json' => $data], $options);

            $response = $this->client->post($url, $options);

            // Check for HTTP errors
            if ($response->getStatusCode() >= 400) {
                $this->handleHttpError($response);
            }

            return $this->parseJsonResponse($response);
        } catch (GuzzleException $e) {
            throw $this->handleException($e);
        } catch (\JsonException $e) {
            throw new ConfigurationException(
                'Invalid JSON response from server: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Set a default header for all requests.
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return void
     *
     * @example
     * ```php
     * $adapter = new GuzzleAdapter();
     * $adapter->setDefaultHeader('Authorization', 'Bearer token123');
     * ```
     */
    public function setDefaultHeader(string $name, string $value): void
    {
        $this->config['headers'][$name] = $value;

        // Recreate client with updated config
        $this->client = new Client($this->config);
    }

    /**
     * Set the timeout for all requests.
     *
     * @param int $seconds Timeout in seconds (must be greater than 0)
     * @return void
     * @throws \InvalidArgumentException If timeout is not positive
     *
     * @example
     * ```php
     * $adapter = new GuzzleAdapter();
     * $adapter->setTimeout(60); // 60 seconds timeout
     * ```
     */
    public function setTimeout(int $seconds): void
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }

        $this->config['timeout'] = $seconds;

        // Recreate client with updated config
        $this->client = new Client($this->config);
    }

    /**
     * Parse JSON response from HTTP response.
     *
     * @param ResponseInterface $response The HTTP response
     * @return array<string,mixed> The decoded JSON data
     * @throws \JsonException If JSON parsing fails
     */
    private function parseJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Handle HTTP error responses.
     *
     * @param ResponseInterface $response The error response
     * @return never
     * @throws ValidationException For 4xx client errors
     * @throws TransactionException For 5xx server errors
     */
    private function handleHttpError(ResponseInterface $response): never
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Try to parse error message from response body
        $errorMessage = $this->extractErrorMessage($body, $statusCode);

        // Map status codes to exception types
        if ($statusCode >= 400 && $statusCode < 500) {
            throw new ValidationException(
                sprintf('HTTP %d error: %s', $statusCode, $errorMessage),
                $statusCode
            );
        }

        if ($statusCode >= 500) {
            throw new TransactionException(
                sprintf('HTTP %d error: %s', $statusCode, $errorMessage),
                $statusCode
            );
        }

        // Fallback for any other error status codes
        throw new ConfigurationException(
            sprintf('HTTP %d error: %s', $statusCode, $errorMessage),
            $statusCode
        );
    }

    /**
     * Extract error message from response body.
     *
     * @param string $body Response body
     * @param int $statusCode HTTP status code
     * @return string Extracted or default error message
     */
    private function extractErrorMessage(string $body, int $statusCode): string
    {
        // Try to parse JSON error
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            // Common error message fields
            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            }
            if (isset($data['error'])) {
                return is_string($data['error']) ? $data['error'] : json_encode($data['error']);
            }
            if (isset($data['message'])) {
                return $data['message'];
            }
        } catch (\JsonException $e) {
            // Not JSON, use raw body if it's short enough
            if (strlen($body) < 200) {
                return $body;
            }
        }

        // Return generic message based on status code
        return $this->getDefaultErrorMessage($statusCode);
    }

    /**
     * Get default error message for status code.
     *
     * @param int $statusCode HTTP status code
     * @return string Default error message
     */
    private function getDefaultErrorMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $messages[$statusCode] ?? 'Unknown Error';
    }

    /**
     * Convert Guzzle exceptions to blockchain exceptions.
     *
     * Maps specific Guzzle exception types to appropriate blockchain exceptions:
     * - ConnectException → ConfigurationException (network/connection errors)
     * - ClientException (4xx) → ValidationException (client-side errors)
     * - ServerException (5xx) → TransactionException (server-side errors)
     * - Other RequestException → ConfigurationException
     *
     * @param GuzzleException $exception The Guzzle exception to convert
     * @return ConfigurationException|ValidationException|TransactionException The converted exception
     */
    private function handleException(GuzzleException $exception): ConfigurationException|ValidationException|TransactionException
    {
        // Network/connection errors
        if ($exception instanceof ConnectException) {
            return new ConfigurationException(
                'Network connection failed: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        // Client errors (4xx)
        if ($exception instanceof ClientException) {
            $response = $exception->getResponse();
            $statusCode = $response->getStatusCode();
            $errorMessage = $this->extractErrorMessage(
                (string) $response->getBody(),
                $statusCode
            );

            return new ValidationException(
                sprintf('Client error (HTTP %d): %s', $statusCode, $errorMessage),
                $statusCode,
                $exception
            );
        }

        // Server errors (5xx)
        if ($exception instanceof ServerException) {
            $response = $exception->getResponse();
            $statusCode = $response->getStatusCode();
            $errorMessage = $this->extractErrorMessage(
                (string) $response->getBody(),
                $statusCode
            );

            return new TransactionException(
                sprintf('Server error (HTTP %d): %s', $statusCode, $errorMessage),
                $statusCode,
                $exception
            );
        }

        // Other request exceptions
        if ($exception instanceof RequestException) {
            $message = 'HTTP request failed: ' . $exception->getMessage();

            if ($exception->hasResponse()) {
                $response = $exception->getResponse();
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->extractErrorMessage(
                    (string) $response->getBody(),
                    $statusCode
                );
                $message = sprintf('HTTP request failed (status %d): %s', $statusCode, $errorMessage);
            }

            return new ConfigurationException($message, 0, $exception);
        }

        // Generic Guzzle exception
        return new ConfigurationException(
            'HTTP request failed: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
}
