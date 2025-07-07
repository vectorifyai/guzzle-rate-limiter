<?php

namespace Vectorify\GuzzleRateLimiter;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * Guzzle middleware for advanced rate limiting
 *
 * Integrates the RateLimiter with Guzzle's middleware stack to provide
 * automatic rate limiting, 429 handling, and cross-process coordination.
 */
class RateLimiterMiddleware
{
    private RateLimiter $rateLimiter;

    /**
     * Create a new rate limiter middleware instance
     *
     * @param StoreInterface $store Cache store for rate limit coordination
     * @param string $cachePrefix Cache key prefix
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(
        StoreInterface $store,
        string $cachePrefix = 'guzzle:rate_limit',
        ?LoggerInterface $logger = null
    ) {
        $this->rateLimiter = new RateLimiter($store, $cachePrefix, $logger);
    }

    /**
     * Middleware invocation handler
     *
     * @param callable $handler Next handler in the middleware stack
     * @return callable Middleware handler function
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // Check rate limits before making request
            $this->rateLimiter->checkRateLimit();

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) {
                    // Update rate limit information from successful response
                    $this->rateLimiter->updateRateLimit($response);

                    // Handle 429 responses
                    if ($response->getStatusCode() === 429) {
                        $this->rateLimiter->handleRateLimitResponse($response);
                    }

                    return $response;
                },
                function ($reason) {
                    // Handle rejected promises (usually exceptions)
                    if ($reason instanceof RequestException && $reason->hasResponse()) {
                        $response = $reason->getResponse();

                        // Update rate limit information from error response
                        $this->rateLimiter->updateRateLimit($response);

                        // Handle rate limit responses specifically
                        if ($response->getStatusCode() === 429) {
                            $this->rateLimiter->handleRateLimitResponse($response);
                        }
                    }

                    throw $reason;
                }
            );
        };
    }
}
