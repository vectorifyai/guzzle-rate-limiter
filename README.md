# Rate limiter middleware for Guzzle

A sophisticated `Guzzle` middleware for preventive rate limiting with multi-store support, progressive delays, and cross-process coordination. It works in accordance with the [IETF standard](https://datatracker.ietf.org/doc/html/draft-ietf-httpapi-ratelimit-headers) by using the `X-RateLimit-Remaining` (number of requests remaining in the current rate limit window) and `Retry-After` (number of seconds to wait before retrying the request again) values available in the response headers.

## Features

- **Intelligent Rate Limiting**: Progressive delays based on remaining API quota
- **Multi-Store Support**: InMemory, Laravel Cache, Symfony Cache, and more
- **Cross-Process Coordination**: Share rate limit state across multiple processes
- **Automatic Recovery**: Handles 429 responses with exponential backoff
- **Flexible Configuration**: Customizable thresholds and delays
- **PSR-3 Logging**: Built-in logging with configurable levels

## Installation

```bash
composer require vectorify/guzzle-rate-limiter
```

## Quick Start

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Vectorify\GuzzleRateLimiter\RateLimiterMiddleware;
use Vectorify\GuzzleRateLimiter\Stores\InMemoryStore;

$stack = HandlerStack::create();
$stack->push(new RateLimiterMiddleware(new InMemoryStore()));

$client = new Client([
    'handler' => $stack
]);
```

## Usage

### Basic Usage

```php
use Vectorify\GuzzleRateLimiter\RateLimiterMiddleware;
use Vectorify\GuzzleRateLimiter\Stores\InMemoryStore;

$middleware = new RateLimiterMiddleware(new InMemoryStore());
```

### Laravel Integration

```php
use Vectorify\GuzzleRateLimiter\Stores\LaravelStore;

$store = new LaravelStore(cache(), 'api:rate_limit');
$middleware = new RateLimiterMiddleware($store);
```

### Symfony Integration

```php
use Vectorify\GuzzleRateLimiter\Stores\SymfonyStore;
use Symfony\Component\Cache\Adapter\RedisAdapter;

$cache = new RedisAdapter(/* redis client */);
$store = new SymfonyStore($cache, 'api:rate_limit');
$middleware = new RateLimiterMiddleware($store);
```

### Advanced Configuration

```php
$middleware = new RateLimiterMiddleware(
    store: new InMemoryStore(),
    cachePrefix: 'my_api:rate_limit',
    logger: $customLogger
);
```

## Changelog

Please see [Releases](../../releases) for more information on what has changed recently.

## Contributing

Pull requests are more than welcome. You must follow the PSR coding standards.

## Security

Please review [our security policy](https://github.com/vectorifyai/laravel-vectorify/security/policy) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
