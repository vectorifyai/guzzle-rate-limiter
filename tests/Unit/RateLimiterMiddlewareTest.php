<?php

namespace Vectorify\GuzzleRateLimiter\Tests\Unit;

use GuzzleHttp\Promise\Create;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Vectorify\GuzzleRateLimiter\RateLimiterMiddleware;
use Vectorify\GuzzleRateLimiter\Stores\InMemoryStore;

#[CoversClass(RateLimiterMiddleware::class)]
class RateLimiterMiddlewareTest extends TestCase
{
    private InMemoryStore $store;
    private RateLimiterMiddleware $middleware;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->middleware = new RateLimiterMiddleware($this->store, 'test:middleware');
    }

    #[Test]
    #[CoversMethod(RateLimiterMiddleware::class, '__construct')]
    #[CoversMethod(RateLimiterMiddleware::class, '__invoke')]
    public function middleware_processes_successful_response(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMockResponse(['X-RateLimit-Remaining' => ['50']]);

        $handler = function ($req, $options) use ($response) {
            return Create::promiseFor($response);
        };

        $middleware = $this->middleware;
        $middlewareHandler = $middleware($handler);

        $promise = $middlewareHandler($request, []);
        $result = $promise->wait();

        $this->assertSame($response, $result);

        // Check that rate limit data was cached
        $cached = $this->store->get('test:middleware');
        $this->assertIsArray($cached);
        $this->assertEquals(50, $cached['remaining']);
    }

    #[Test]
    #[CoversMethod(RateLimiterMiddleware::class, '__invoke')]
    public function middleware_handles_rate_limit_response(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMockResponse([
            'X-RateLimit-Remaining' => ['0'],
            'Retry-After' => ['1']
        ], 429);

        $handler = function ($req, $options) use ($response) {
            return Create::promiseFor($response);
        };

        $middleware = $this->middleware;
        $middlewareHandler = $middleware($handler);

        $start = microtime(true);
        $promise = $middlewareHandler($request, []);
        $result = $promise->wait();
        $end = microtime(true);

        $this->assertSame($response, $result);

        // Should have applied delay for rate limit
        $this->assertGreaterThan(0.5, $end - $start);

        // Check cached data shows 0 remaining
        $cached = $this->store->get('test:middleware');
        $this->assertEquals(0, $cached['remaining']);
    }

    private function createMockResponse(array $headers, int $statusCode = 200): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }
}
