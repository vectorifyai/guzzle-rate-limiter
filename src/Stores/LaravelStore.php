<?php

namespace Vectorify\GuzzleRateLimiter\Stores;

use DateTime;
use DateInterval;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * Laravel cache store for rate limit data
 *
 * Integrates with Laravel's cache system to provide cross-process
 * rate limit coordination in Laravel applications.
 */
class LaravelStore implements StoreInterface
{
    private CacheRepository $cache;
    private string $prefix;

    /**
     * Create a new Laravel cache store
     *
     * @param CacheRepository $cache Laravel cache repository
     * @param string $prefix Cache key prefix
     */
    public function __construct(CacheRepository $cache, string $prefix = 'guzzle:rate_limit')
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    /**
     * Get rate limit data from Laravel cache
     *
     * @param string $key Cache key
     * @return array|null Rate limit data or null if not found
     */
    public function get(string $key): ?array
    {
        try {
            $fullKey = $this->buildKey($key);
            $data = $this->cache->get($fullKey);

            return is_array($data) ? $data : null;
        } catch (Exception) {
            // Silent failure - rate limiting will fall back gracefully
            return null;
        }
    }

    /**
     * Store rate limit data in Laravel cache
     *
     * @param string $key Cache key
     * @param array $data Rate limit data
     * @param int $ttl Time to live in seconds
     * @return bool True if stored successfully
     */
    public function put(string $key, array $data, int $ttl): bool
    {
        try {
            $fullKey = $this->buildKey($key);

            // Laravel cache expects DateTime for expiry
            $expiry = new DateTime();
            $expiry->add(new DateInterval("PT{$ttl}S"));

            $this->cache->put($fullKey, $data, $expiry);

            return true;
        } catch (Exception) {
            // Silent failure - rate limiting will fall back gracefully
            return false;
        }
    }

    /**
     * Remove rate limit data from Laravel cache
     *
     * @param string $key Cache key
     * @return bool True if deleted successfully
     */
    public function forget(string $key): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            $this->cache->forget($fullKey);

            return true;
        } catch (Exception) {
            // Silent failure
            return false;
        }
    }

    /**
     * Build full cache key with prefix
     *
     * @param string $key Base cache key
     * @return string Full cache key
     */
    private function buildKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    /**
     * Get the underlying cache repository
     *
     * @return CacheRepository Laravel cache repository
     */
    public function getCache(): CacheRepository
    {
        return $this->cache;
    }

    /**
     * Get the cache key prefix
     *
     * @return string Cache key prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
