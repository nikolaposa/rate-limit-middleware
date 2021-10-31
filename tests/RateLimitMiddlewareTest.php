<?php

declare(strict_types=1);

namespace RateLimit\Middleware\Tests;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use RateLimit\InMemoryRateLimiter;
use RateLimit\Middleware\RateLimitMiddleware;
use RateLimit\Middleware\ResolveIpAddressAsUserIdentity;
use RateLimit\Rate;
use RateLimit\SilentRateLimiter;

class RateLimitMiddlewareTest extends TestCase
{
    /** @var RequestHandlerInterface */
    protected $limitExceededHandler;

    /** @var ServerRequestFactory */
    protected $requestFactory;

    /** @var RequestHandlerInterface */
    protected $requestHandler;

    protected function setUp(): void
    {
        $this->limitExceededHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['error' => 'Too many requests'], 429);
            }
        };
        $this->requestFactory = new ServerRequestFactory();
        $this->requestHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['success' => true]);
            }
        };
    }

    protected function getRateLimiter(Rate $rate): SilentRateLimiter
    {
        return new InMemoryRateLimiter($rate);
    }

    /**
     * @test
     */
    public function it_sets_rate_limit_headers(): void
    {
        $rateLimitMiddleware = new RateLimitMiddleware(
            $this->getRateLimiter(Rate::perMinute(3)),
            'api',
            new ResolveIpAddressAsUserIdentity(),
            $this->limitExceededHandler
        );

        $request = $this->requestFactory->createServerRequest('POST', '/api/posts');

        $response = $rateLimitMiddleware->process($request, $this->requestHandler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('3', $response->getHeaderLine(RateLimitMiddleware::HEADER_LIMIT));
        $this->assertSame('2', $response->getHeaderLine(RateLimitMiddleware::HEADER_REMAINING));
        $this->assertTrue($response->hasHeader(RateLimitMiddleware::HEADER_RESET));
    }

    /**
     * @test
     */
    public function it_sets_appropriate_response_status_when_limit_is_reached(): void
    {
        $rateLimitMiddleware = new RateLimitMiddleware(
            $this->getRateLimiter(Rate::perMinute(2)),
            'api',
            new ResolveIpAddressAsUserIdentity(),
            $this->limitExceededHandler
        );

        $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/posts'), $this->requestHandler);
        $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/posts'), $this->requestHandler);
        $response = $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/posts'), $this->requestHandler);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('0', $response->getHeaderLine(RateLimitMiddleware::HEADER_REMAINING));
    }

    /**
     * @test
     */
    public function it_resets_limit_after_rate_interval(): void
    {
        $rateLimitMiddleware = new RateLimitMiddleware(
            $this->getRateLimiter(Rate::perSecond(1)),
            'api_create_user',
            new ResolveIpAddressAsUserIdentity(),
            $this->limitExceededHandler
        );

        $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/users'), $this->requestHandler);
        $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/users'), $this->requestHandler);
        sleep(2);
        $response = $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/users'), $this->requestHandler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_invokes_limit_exceeded_handler(): void
    {
        $rateLimitMiddleware = new RateLimitMiddleware(
            $this->getRateLimiter(Rate::perSecond(1)),
            'api_create_user',
            new ResolveIpAddressAsUserIdentity(),
            $this->limitExceededHandler
        );

        $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/users'), $this->requestHandler);
        $response = $rateLimitMiddleware->process($this->requestFactory->createServerRequest('POST', '/api/users'), $this->requestHandler);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('Too many requests', $response->getBody()->getContents());
    }
}
