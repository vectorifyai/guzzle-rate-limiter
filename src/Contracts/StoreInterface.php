<?php

namespace Vectorify\GuzzleRateLimiter\Contracts;

/**
 * Cache store contract for rate limit data
 */
interface StoreInterface
{
    /**
     * Get rate limit data from cache
     *
     * @param string $key Cache key
     * @return array|null Rate limit data or null if not found
     */
    public function get(string $key): ?array;

    /**
     * Store rate limit data in cache
     *
     * @param string $key Cache key
     * @param array $data Rate limit data
     * @param int $ttl Time to live in seconds
     * @return bool True if stored successfully
     */
    public function put(string $key, array $data, int $ttl): bool;

    /**
     * Remove rate limit data from cache
     *
     * @param string $key Cache key
     * @return bool True if deleted successfully
     */
    public function forget(string $key): bool;
}
