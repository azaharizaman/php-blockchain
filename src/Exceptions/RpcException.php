<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * RpcException is thrown when RPC (Remote Procedure Call) operations fail.
 *
 * This exception is used when:
 * - RPC endpoint is unreachable
 * - RPC call returns an error response
 * - Network timeout occurs
 * - Invalid RPC response format
 * - RPC method not found
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\Drivers\EthereumDriver;
 * use Blockchain\Exceptions\RpcException;
 *
 * try {
 *     $driver = new EthereumDriver();
 *     $driver->connect(['endpoint' => 'https://invalid-endpoint.example.com']);
 *     $balance = $driver->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0');
 * } catch (RpcException $e) {
 *     echo "RPC call failed: " . $e->getMessage();
 *     echo "Error code: " . $e->getErrorCode();
 *     if ($e->getRpcMethod()) {
 *         echo "Failed method: " . $e->getRpcMethod();
 *     }
 * }
 * ```
 */
class RpcException extends Exception
{
    private ?string $rpcMethod = null;
    private ?int $errorCode = null;

    /**
     * Set the RPC method that failed.
     *
     * @param string|null $method RPC method name (e.g., 'eth_call', 'eth_getBalance')
     * @return self
     */
    public function setRpcMethod(?string $method): self
    {
        $this->rpcMethod = $method;
        return $this;
    }

    /**
     * Get the RPC method that failed.
     *
     * @return string|null RPC method name if available
     */
    public function getRpcMethod(): ?string
    {
        return $this->rpcMethod;
    }

    /**
     * Set the RPC error code.
     *
     * @param int|null $code RPC error code (e.g., -32000, -32602)
     * @return self
     */
    public function setErrorCode(?int $code): self
    {
        $this->errorCode = $code;
        return $this;
    }

    /**
     * Get the RPC error code.
     *
     * @return int|null RPC error code if available
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }
}
