<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * ContractException is thrown when smart contract interactions fail.
 *
 * This exception is used when:
 * - Contract does not exist at the specified address
 * - Contract does not implement expected interface (e.g., ERC-20)
 * - ABI encoding/decoding fails
 * - Contract function call reverts
 * - Invalid contract address format
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\Drivers\EthereumDriver;
 * use Blockchain\Exceptions\ContractException;
 *
 * try {
 *     $driver = new EthereumDriver();
 *     $driver->connect(['endpoint' => 'https://mainnet.infura.io/v3/YOUR_KEY']);
 *     // Trying to query a non-ERC-20 contract
 *     $balance = $driver->getTokenBalance(
 *         '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0',
 *         '0x0000000000000000000000000000000000000000'
 *     );
 * } catch (ContractException $e) {
 *     echo "Contract interaction failed: " . $e->getMessage();
 *     if ($e->getContractAddress()) {
 *         echo "Contract address: " . $e->getContractAddress();
 *     }
 *     if ($e->getFunctionName()) {
 *         echo "Function: " . $e->getFunctionName();
 *     }
 * }
 * ```
 */
class ContractException extends Exception
{
    private ?string $contractAddress = null;
    private ?string $functionName = null;

    /**
     * Set the contract address associated with this exception.
     *
     * @param string|null $address Contract address
     * @return self
     */
    public function setContractAddress(?string $address): self
    {
        $this->contractAddress = $address;
        return $this;
    }

    /**
     * Get the contract address associated with this exception.
     *
     * @return string|null Contract address if available
     */
    public function getContractAddress(): ?string
    {
        return $this->contractAddress;
    }

    /**
     * Set the contract function name that failed.
     *
     * @param string|null $functionName Function name (e.g., 'balanceOf', 'decimals')
     * @return self
     */
    public function setFunctionName(?string $functionName): self
    {
        $this->functionName = $functionName;
        return $this;
    }

    /**
     * Get the contract function name that failed.
     *
     * @return string|null Function name if available
     */
    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }
}
