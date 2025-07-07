<?php

namespace Vectorify\GuzzleRateLimiter\Stores;

use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * In-memory cache store for rate limit data
 *
 * Stores rate limit information in memory, suitable for single-process
 * applications or when cross-process coordination is not required.
 */
class InMemoryStore implements StoreInterface
{
    private array $data = [];

    /**
     * Get rate limit data from memory
     *
     * @param string $key Cache key
     * @return array|null Rate limit data or null if not found
     */
    public function get(string $key): ?array
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        $item = $this->data[$key];

        // Check if expired
        if ($item['expires_at'] < time()) {
            unset($this->data[$key]);
            return null;
        }

        return $item['data'];
    }

    /**
     * Store rate limit data in memory
     *
     * @param string $key Cache key
     * @param array $data Rate limit data
     * @param int $ttl Time to live in seconds
     * @return bool Always returns true for in-memory storage
     */
    public function put(string $key, array $data, int $ttl): bool
    {
        $this->data[$key] = [
            'data' => $data,
            'expires_at' => time() + $ttl,
        ];

        return true;
    }

    /**
     * Remove rate limit data from memory
     *
     * @param string $key Cache key
     * @return bool Always returns true
     */
    public function forget(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    /**
     * Clear all cached data
     */
    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * Get all cached keys (for debugging/testing)
     *
     * @return array Array of cache keys
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }
}
