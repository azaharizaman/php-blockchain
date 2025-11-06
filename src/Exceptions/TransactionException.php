<?php

declare(strict_types=1);

namespace Blockchain\Exceptions;

use Exception;

/**
 * TransactionException is thrown when transaction operations fail or are invalid.
 *
 * This exception is used when:
 * - Transaction submission fails
 * - Transaction validation fails
 * - Transaction signing errors occur
 * - Insufficient funds for transaction
 * - Gas estimation fails
 *
 * @package Blockchain\Exceptions
 *
 * @example
 * ```php
 * use Blockchain\BlockchainManager;
 * use Blockchain\Exceptions\TransactionException;
 *
 * try {
 *     $blockchain = new BlockchainManager('solana', ['endpoint' => 'https://api.mainnet-beta.solana.com']);
 *     $txHash = $blockchain->sendTransaction('from_address', 'to_address', 1.5);
 * } catch (TransactionException $e) {
 *     echo "Transaction failed: " . $e->getMessage();
 *     if ($e->getTransactionHash()) {
 *         echo "Transaction hash: " . $e->getTransactionHash();
 *     }
 * }
 * ```
 */
class TransactionException extends Exception
{
    private ?string $transactionHash = null;

    /**
     * Set the transaction hash associated with this exception.
     *
     * @param string|null $hash Transaction hash
     * @return self
     */
    public function setTransactionHash(?string $hash): self
    {
        $this->transactionHash = $hash;
        return $this;
    }

    /**
     * Get the transaction hash associated with this exception.
     *
     * @return string|null Transaction hash if available
     */
    public function getTransactionHash(): ?string
    {
        return $this->transactionHash;
    }
}
