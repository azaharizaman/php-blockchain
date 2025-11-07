<?php

/**
 * Solana Blockchain Configuration Example
 *
 * This file demonstrates how to configure the Solana driver for the
 * PHP Blockchain Integration Layer. Copy this file and modify the
 * values according to your needs.
 */

return [
    /**
     * RPC URL (Required)
     *
     * The endpoint URL for the Solana RPC node. This is the only
     * required configuration parameter.
     *
     * Available networks:
     * - Mainnet Beta: https://api.mainnet-beta.solana.com
     * - Devnet: https://api.devnet.solana.com
     * - Testnet: https://api.testnet.solana.com
     *
     * For production use, consider using a dedicated RPC provider like:
     * - QuickNode: https://quicknode.com
     * - Alchemy: https://alchemy.com
     * - Helius: https://helius.dev
     */
    'rpc_url' => 'https://api.mainnet-beta.solana.com',

    /**
     * Timeout (Optional)
     *
     * Request timeout in seconds. Default is 30 seconds.
     * Must be a positive integer.
     *
     * Increase this value if you experience timeout errors with
     * slow RPC endpoints or complex transactions.
     */
    'timeout' => 30,

    /**
     * Commitment Level (Optional)
     *
     * The commitment describes how finalized a block is at that point in time.
     *
     * Available values:
     * - 'finalized': The node will query the most recent block confirmed by
     *   supermajority of the cluster as having reached maximum lockout,
     *   meaning the cluster has recognized this block as finalized
     *
     * - 'confirmed': The node will query the most recent block that has been
     *   voted on by supermajority of the cluster
     *
     * - 'processed': The node will query its most recent block. Note that the
     *   block may not be complete
     *
     * Default: 'finalized' (recommended for production)
     */
    'commitment' => 'finalized',
];
