<?php

namespace Vectorify\GuzzleRateLimiter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Vectorify\GuzzleRateLimiter\RateLimiter;
use Vectorify\GuzzleRateLimiter\Stores\InMemoryStore;

#[CoversClass(RateLimiter::class)]
class RateLimiterTest extends TestCase
{
    private InMemoryStore $store;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->rateLimiter = new RateLimiter($this->store, 'test:rate_limit');
    }

    #[Test]
    #[CoversMethod(RateLimiter::class, '__construct')]
    #[CoversMethod(RateLimiter::class, 'checkRateLimit')]
    public function check_rate_limit_does_nothing_when_no_cache_data(): void
    {
        // Should not throw any exceptions or delay
        $start = microtime(true);
        $this->rateLimiter->checkRateLimit();
        $end = microtime(true);

        // Should be nearly instantaneous (less than 0.1 seconds)
        $this->assertLessThan(0.1, $end - $start);
    }

    #[Test]
    #[CoversMethod(RateLimiter::class, 'updateRateLimit')]
    #[CoversMethod(RateLimiter::class, 'getHeader')]
    public function update_rate_limit_from_response_headers(): void
    {
        $response = $this->createMockResponse([
            'X-RateLimit-Remaining' => ['10'],
            'Retry-After' => ['60']
        ]);

        $this->rateLimiter->updateRateLimit($response);

        // Check that data was stored
        $cached = $this->store->get('test:rate_limit');
        $this->assertIsArray($cached);
        $this->assertEquals(10, $cached['remaining']);
        $this->assertGreaterThan(time(), $cached['reset_time']);
    }

    #[Test]
    #[CoversMethod(RateLimiter::class, 'handleRateLimitResponse')]
    public function handle_rate_limit_response_sets_zero_remaining(): void
    {
        $response = $this->createMockResponse([
            'Retry-After' => ['30']
        ]);

        // This will cause a delay, so we'll test quickly
        $start = microtime(true);
        $this->rateLimiter->handleRateLimitResponse($response);
        $end = microtime(true);

        // Should have waited (at least some time)
        $this->assertGreaterThan(1, $end - $start); // At least 1 second

        // Check cached data
        $cached = $this->store->get('test:rate_limit');
        $this->assertEquals(0, $cached['remaining']);
    }

    #[Test]
    #[CoversMethod(RateLimiter::class, 'checkRateLimit')]
    public function check_rate_limit_delays_when_few_requests_remaining(): void
    {
        // Set up low remaining requests
        $this->store->put('test:rate_limit', [
            'remaining' => 1,
            'reset_time' => time() + 10,
            'updated_at' => time()
        ], 60);

        $start = microtime(true);
        $this->rateLimiter->checkRateLimit();
        $end = microtime(true);

        // Should have applied some delay
        $this->assertGreaterThan(0.5, $end - $start);
    }

    private function createMockResponse(array $headers): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaders')->willReturn($headers);

        // Configure header access
        $response->method('hasHeader')->willReturnCallback(
            fn($name) => isset($headers[$name])
        );

        return $response;
    }
}
