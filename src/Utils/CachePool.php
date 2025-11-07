<?php

declare(strict_types=1);

namespace Blockchain\Utils;

/**
 * Simple array-backed cache pool with TTL support.
 * 
 * This is an in-memory cache implementation that stores items with expiration times.
 * It provides PSR-6-like interface without requiring the full PSR-6 dependency.
 */
class CachePool
{
    /**
     * @var array<string,array{value:mixed,expires:int}>
     */
    private array $cache = [];

    private int $defaultTtl = 300; // 5 minutes

    /**
     * Retrieve an item from the cache.
     *
     * @param string $key The unique key for this cache item
     * @param mixed $default Default value to return if the key doesn't exist
     * @return mixed The cached value or default if not found/expired
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }

        $item = $this->cache[$key];

        // Check if item has expired
        if ($item['expires'] < time()) {
            unset($this->cache[$key]);
            return $default;
        }

        return $item['value'];
    }

    /**
     * Store an item in the cache.
     *
     * @param string $key The unique key for this cache item
     * @param mixed $value The value to store
     * @param int|null $ttl Time to live in seconds (null uses default)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expires = time() + $ttl;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        return true;
    }

    /**
     * Check if an item exists in the cache and is not expired.
     *
     * @param string $key The unique key for this cache item
     * @return bool True if the item exists and is not expired
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // Check if expired
        if ($this->cache[$key]['expires'] < time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key The unique key for this cache item
     * @return bool True if the item existed, false otherwise
     */
    public function delete(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    /**
     * Clear all items from the cache.
     *
     * @return bool Always returns true
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * Set the default TTL for cache items.
     *
     * @param int $seconds TTL in seconds (must be greater than 0)
     * @return void
     * @throws \InvalidArgumentException If seconds is not greater than 0
     */
    public function setDefaultTtl(int $seconds): void
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('TTL must be greater than 0');
        }

        $this->defaultTtl = $seconds;
    }

    /**
     * Retrieve multiple items from the cache.
     *
     * @param array<string> $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array<string,mixed> Array of key => value pairs
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Store multiple items in the cache.
     *
     * @param array<string,mixed> $values Array of key => value pairs to store
     * @param int|null $ttl Time to live in seconds (null uses default)
     * @return bool True on success
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Generate a deterministic cache key from method name and parameters.
     *
     * @param string $method The method name
     * @param array<string,mixed> $params The parameters
     * @return string The generated cache key
     */
    public static function generateKey(string $method, array $params): string
    {
        $paramsHash = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
        return "blockchain:{$method}:{$paramsHash}";
    }
}
