# Rate Limit Middleware

[![Build Status](https://travis-ci.org/nikolaposa/rate-limit-middleware.svg?branch=master)](https://travis-ci.org/nikolaposa/rate-limit-middleware)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nikolaposa/rate-limit-middleware/v/stable)](https://packagist.org/packages/nikolaposa/rate-limit-middleware)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg)](https://github.com/php-pds/skeleton)


PSR-15 middleware for rate limiting API or other application endpoints.

## Installation

The preferred method of installation is via [Composer](http://getcomposer.org/). Run the following
command to install the latest version of a package and add it to your project's `composer.json`:

```bash
composer require nikolaposa/rate-limit-middleware
```

## Usage

**Laminas example**

```php
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Server;
use Laminas\Stratigility\Middleware\NotFoundHandler;
use Laminas\Stratigility\MiddlewarePipe;
use RateLimit\Http\RateLimitMiddleware;
use RateLimit\Rate;
use RateLimit\RedisRateLimiter;
use Redis;

$rateLimitMiddleware = new RateLimitMiddleware(
    new RedisRateLimiter(new Redis()),
    '',
    new GetRateViaPathPatternMap([
        '|/api/.+|' => Rate::perMinute(20),
    ]),
    new ResolveIpAddressAsUserIdentity(),
    new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new JsonResponse(['error' => 'Too many requests']);
        }
    }
);

$app = new MiddlewarePipe();
$app->pipe($rateLimitMiddleware);

$server = Server::createServer([$app, 'handle'], $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
$server->listen(function ($req, $res) {
    return $res;
});
```

## Credits

- [Nikola Poša][link-author]
- [All Contributors][link-contributors]

## License

Released under MIT License - see the [License File](LICENSE) for details.


[link-author]: https://github.com/nikolaposa
[link-contributors]: ../../contributors
