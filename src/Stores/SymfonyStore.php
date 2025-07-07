<?php

namespace Vectorify\GuzzleRateLimiter\Stores;

use Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * Symfony cache store for rate limit data
 *
 * Integrates with Symfony's cache component to provide cross-process
 * rate limit coordination in Symfony applications.
 */
class SymfonyStore implements StoreInterface
{
    private CacheInterface $cache;
    private string $prefix;

    /**
     * Create a new Symfony cache store
     *
     * @param CacheInterface $cache Symfony cache interface
     * @param string $prefix Cache key prefix
     */
    public function __construct(CacheInterface $cache, string $prefix = 'guzzle_rate_limit')
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    /**
     * Get rate limit data from Symfony cache
     *
     * @param string $key Cache key
     * @return array|null Rate limit data or null if not found
     */
    public function get(string $key): ?array
    {
        try {
            $fullKey = $this->buildKey($key);

            return $this->cache->get($fullKey, function (ItemInterface $item) {
                // Return null if item doesn't exist
                return null;
            });
        } catch (Exception) {
            // Silent failure - rate limiting will fall back gracefully
            return null;
        }
    }

    /**
     * Store rate limit data in Symfony cache
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

            $this->cache->get($fullKey, function (ItemInterface $item) use ($data, $ttl) {
                $item->expiresAfter($ttl);
                return $data;
            });

            return true;
        } catch (Exception) {
            // Silent failure - rate limiting will fall back gracefully
            return false;
        }
    }

    /**
     * Remove rate limit data from Symfony cache
     *
     * @param string $key Cache key
     * @return bool True if deleted successfully
     */
    public function forget(string $key): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            $this->cache->delete($fullKey);

            return true;
        } catch (Exception) {
            // Silent failure
            return false;
        }
    }

    /**
     * Build cache key with prefix and sanitization
     *
     * Symfony cache keys have specific requirements:
     * - Must be valid PSR-6 cache keys
     * - Cannot contain certain characters
     *
     * @param string $key Base cache key
     * @return string Sanitized full cache key
     */
    private function buildKey(string $key): string
    {
        $fullKey = $this->prefix . '.' . $key;

        // Sanitize key for PSR-6 compliance
        // Replace invalid characters with underscores
        return preg_replace('/[^a-zA-Z0-9_.]/', '_', $fullKey);
    }

    /**
     * Get the underlying cache interface
     *
     * @return CacheInterface Symfony cache interface
     */
    public function getCache(): CacheInterface
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
