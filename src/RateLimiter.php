<?php

namespace Vectorify\GuzzleRateLimiter;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * Advanced rate limiter with progressive delays and cross-process coordination
 *
 * Provides intelligent rate limiting with:
 * - Progressive delay strategies based on remaining quota
 * - Cross-process coordination via configurable cache stores
 * - Automatic recovery from rate limit violations
 * - Comprehensive logging and monitoring
 */
class RateLimiter
{
    private const MAX_RATE_LIMIT_WAIT = 90;
    private const RATE_LIMIT_THRESHOLD_CRITICAL = 0;
    private const RATE_LIMIT_THRESHOLD_LOW = 2;
    private const RATE_LIMIT_THRESHOLD_MEDIUM = 5;
    private const MAX_PROGRESSIVE_WAIT_HIGH = 30;
    private const MAX_PROGRESSIVE_WAIT_LOW = 10;
    private const CACHE_TTL_BUFFER = 10; // Extra cache time beyond reset

    private StoreInterface $store;
    private string $cachePrefix;
    private LoggerInterface $logger;

    /**
     * Create a new rate limiter instance
     *
     * @param StoreInterface $store Cache store for shared rate limiting
     * @param string $cachePrefix Cache key prefix
     * @param LoggerInterface|null $logger Logger instance for rate limit messages
     */
    public function __construct(
        StoreInterface $store,
        string $cachePrefix = 'guzzle:rate_limit',
        ?LoggerInterface $logger = null,
    ) {
        $this->store = $store;
        $this->cachePrefix = $cachePrefix;
        $this->logger = $logger ?: $this->createDefaultLogger();
    }

    /**
     * Check rate limits and apply preventive delays if necessary
     */
    public function checkRateLimit(): void
    {
        $rateLimit = $this->store->get($this->cachePrefix);

        if (! $rateLimit || !isset($rateLimit['remaining'])) {
            return;
        }

        // Be more aggressive - start rate limiting when we have few requests left
        if ($rateLimit['remaining'] > self::RATE_LIMIT_THRESHOLD_LOW) {
            return;
        }

        $resetTime = $rateLimit['reset_time'] ?? time();
        $waitTime = $resetTime - time();

        if ($waitTime <= 0) {
            $this->store->forget($this->cachePrefix);

            return;
        }

        // Add progressive delays based on remaining requests
        $delayTime = match (true) {
            $rateLimit['remaining'] <= self::RATE_LIMIT_THRESHOLD_CRITICAL => min($waitTime, self::MAX_RATE_LIMIT_WAIT),
            $rateLimit['remaining'] <= self::RATE_LIMIT_THRESHOLD_LOW => min($waitTime / 2, self::MAX_PROGRESSIVE_WAIT_HIGH),
            $rateLimit['remaining'] <= self::RATE_LIMIT_THRESHOLD_MEDIUM => min($waitTime / 4, self::MAX_PROGRESSIVE_WAIT_LOW),
            default => 0,
        };

        if ($delayTime > 0) {
            $this->logger->info("Rate limit preventive delay: {$delayTime} seconds (remaining: {$rateLimit['remaining']})");

            sleep((int) $delayTime);
        }
    }

    /**
     * Update rate limit information from API response
     *
     * @param ResponseInterface $response HTTP response with rate limit headers
     */
    public function updateRateLimit(ResponseInterface $response): void
    {
        $remaining = $this->getHeader('X-RateLimit-Remaining', $response);

        if ($remaining === null) {
            return;
        }

        $retryAfter = $this->getHeader('Retry-After', $response);
        $waitTime = $retryAfter ? (int) $retryAfter : self::MAX_RATE_LIMIT_WAIT;

        $rateLimit = [
            'remaining' => (int) $remaining,
            'reset_time' => time() + $waitTime,
            'updated_at' => time(),
        ];

        $this->store->put($this->cachePrefix, $rateLimit, $waitTime + self::CACHE_TTL_BUFFER);

        $this->logger->debug('Rate limit updated', [
            'remaining' => $rateLimit['remaining'],
            'reset_time' => date('Y-m-d H:i:s', $rateLimit['reset_time']),
        ]);
    }

    /**
     * Handle rate limit response (429 status)
     *
     * @param ResponseInterface $response HTTP response with rate limit information
     */
    public function handleRateLimitResponse(ResponseInterface $response): void
    {
        $retryAfter = $this->getHeader('Retry-After', $response);
        $waitTime = $retryAfter ? (int) $retryAfter : self::MAX_RATE_LIMIT_WAIT;

        // Update rate limit cache to reflect we've hit the limit
        $rateLimit = [
            'remaining' => 0,
            'reset_time' => time() + $waitTime,
            'updated_at' => time(),
        ];

        $this->store->put($this->cachePrefix, $rateLimit, $waitTime + self::CACHE_TTL_BUFFER);

        $this->logger->info("Rate limit hit, waiting {$waitTime} seconds before retry");

        sleep(min($waitTime, self::MAX_RATE_LIMIT_WAIT));
    }

    /**
     * Extract header value from HTTP response
     *
     * @param string $name Header name
     * @param ResponseInterface $response HTTP response
     * @return string|null Header value or null if not found
     */
    private function getHeader(string $name, ResponseInterface $response): ?string
    {
        $headers = $response->getHeaders();

        // Try exact match first
        if (isset($headers[$name])) {
            return is_array($headers[$name]) ? $headers[$name][0] : $headers[$name];
        }

        // Try lowercase match
        $lowerName = strtolower($name);
        if (isset($headers[$lowerName])) {
            return is_array($headers[$lowerName]) ? $headers[$lowerName][0] : $headers[$lowerName];
        }

        return null;
    }

    /**
     * Create a default logger instance
     *
     * @return LoggerInterface Default logger instance
     */
    private function createDefaultLogger(): LoggerInterface
    {
        $logger = new Logger('guzzle.rate_limiter');
        $logger->pushHandler(new StreamHandler('php://stderr', \Monolog\Level::Info));

        return $logger;
    }
}
