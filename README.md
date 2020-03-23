# Rate Limit Middleware

[![Build Status](https://travis-ci.com/nikolaposa/rate-limit-middleware.svg?branch=master)](https://travis-ci.com/nikolaposa/rate-limit-middleware)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nikolaposa/rate-limit-middleware/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nikolaposa/rate-limit-middleware/v/stable)](https://packagist.org/packages/nikolaposa/rate-limit-middleware)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg)](https://github.com/php-pds/skeleton)


PSR-15 middleware for rate limiting API or other application endpoints. Sits on top of general purpose [Rate Limiter](https://github.com/nikolaposa/rate-limit).

## Installation

The preferred method of installation is via [Composer](http://getcomposer.org/). Run the following
command to install the latest version of a package and add it to your project's `composer.json`:

```bash
composer require nikolaposa/rate-limit-middleware
```

## Usage

Rate Limit middleware is designed to be used per route, so that you can set up a rate limiting 
strategies for each individual endpoint or group of endpoints. This is accomplished  through a 
mechanism for composing middleware known as *piping*.

### Full example

Following examples demonstrate how `RateLimitMiddleware` can be used in Mezzio-based 
application, but the same principle applies to any middleware framework.

**dependencies.php**

```php
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RateLimit\Middleware\RateLimitMiddleware;
use RateLimit\Middleware\ResolveIpAddressAsUserIdentity;
use RateLimit\Middleware\ResolveUserIdentity;
use RateLimit\Rate;
use RateLimit\RateLimiter;
use RateLimit\RedisRateLimiter;

return [
    'dependencies' => [
        'invokables' => [
            ResolveUserIdentity::class => ResolveIpAddressAsUserIdentity::class,
        ],
        'factories'  => [
            RateLimiter::class => function () {
                $redis = new \Redis();
                $redis->connect('127.0.0.1');
                return new RedisRateLimiter($redis, 'rate_limit:');
            },
            // default limit exceeded handler; anonymous class is used only for the sake 
            // of simplicity of the example
            'RateLimit\\LimitExceededRequestHandler' => function () {
                return new class implements RequestHandlerInterface {
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return new JsonResponse(['error' => 'Too many requests']);
                    }
                };
            },
            // rate limit middleware for different endpoints
            'RateLimit\\CreatePostRateLimitMiddleware' => function (ContainerInterface $container) {
                return new RateLimitMiddleware(
                   $container->get(RateLimiter::class),
                   'post.create',
                   Rate::perSecond(5),
                   $container->get(ResolveUserIdentity::class),
                   $container->get('RateLimit\\LimitExceededRequestHandler')
               );
            },
            'RateLimit\\ApiRateLimitMiddleware' => function (ContainerInterface $container) {
                return new RateLimitMiddleware(
                   $container->get(RateLimiter::class),
                   'api',
                   Rate::perMinute(20),
                   $container->get(ResolveUserIdentity::class),
                   $container->get('RateLimit\\LimitExceededRequestHandler')
               );
            },
        ],
    ],
];
```

**index.php**

```php
$app->get('/', App\Handler\HomePageHandler::class, 'home');

$app->get('/posts', [
    App\Handler\ListPostsHandler::class,
], 'post.list');
$app->post('/posts', [
    'RateLimit\\CreatePostRateLimitMiddleware',
    App\Handler\CreatePostHandler::class,
], 'post.create');
$app->put('/posts/:id', App\Handler\UpdatePostHandler::class, 'post.edit');

$app->route('/api/resource[/{id:[a-f0-9]{32}}]', [
    AuthenticationMiddleware::class,
    'RateLimit\\ApiRateLimitMiddleware',
    ApiResource::class,
], ['GET', 'POST', 'PATCH', 'DELETE'], 'api-resource');

```

## Credits

- [Nikola Poša][link-author]
- [All Contributors][link-contributors]

## License

Released under MIT License - see the [License File](LICENSE) for details.


[link-author]: https://github.com/nikolaposa
[link-contributors]: ../../contributors
