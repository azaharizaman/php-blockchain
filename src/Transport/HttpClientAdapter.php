<?php

declare(strict_types=1);

namespace Blockchain\Transport;

/**
 * HttpClientAdapter interface for HTTP operations.
 *
 * This interface defines the contract for HTTP client adapters that handle
 * GET and POST requests with automatic JSON handling.
 *
 * @package Blockchain\Transport
 */
interface HttpClientAdapter
{
    /**
     * Perform a GET request.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $options Additional request options
     * @return array<string,mixed> The decoded JSON response
     * @throws \Blockchain\Exceptions\ConfigurationException If the request fails
     * @throws \Blockchain\Exceptions\ValidationException If validation fails
     * @throws \Blockchain\Exceptions\TransactionException If transaction fails
     */
    public function get(string $url, array $options = []): array;

    /**
     * Perform a POST request.
     *
     * @param string $url The URL to request
     * @param array<string,mixed> $data The data to send in the request body
     * @param array<string,mixed> $options Additional request options
     * @return array<string,mixed> The decoded JSON response
     * @throws \Blockchain\Exceptions\ConfigurationException If the request fails
     * @throws \Blockchain\Exceptions\ValidationException If validation fails
     * @throws \Blockchain\Exceptions\TransactionException If transaction fails
     */
    public function post(string $url, array $data, array $options = []): array;
}
