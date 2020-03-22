<?php

declare(strict_types=1);

namespace RateLimit\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use RateLimit\SilentRateLimiter;
use RateLimit\Status;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public const HEADER_LIMIT = 'X-RateLimit-Limit';
    public const HEADER_REMAINING = 'X-RateLimit-Remaining';
    public const HEADER_RESET = 'X-RateLimit-Reset';

    /** @var SilentRateLimiter */
    private $rateLimiter;

    /** @var GetRate */
    private $getRate;

    /** @var ResolveUserIdentity */
    private $resolveUserIdentity;

    /** @var RequestHandlerInterface */
    private $limitExceededHandler;

    public function __construct(
        SilentRateLimiter $rateLimiter,
        GetRate $getRate,
        ResolveUserIdentity $resolveUserIdentity,
        RequestHandlerInterface $limitExceededHandler
    ) {
        $this->rateLimiter = $rateLimiter;
        $this->getRate = $getRate;
        $this->resolveUserIdentity = $resolveUserIdentity;
        $this->limitExceededHandler = $limitExceededHandler;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $rate = $this->getRate->forRequest($request);

        if (null === $rate) {
            return $handler->handle($request);
        }

        $identifier = $this->resolveUserIdentity->fromRequest($request);

        $status = $this->rateLimiter->limitSilently($identifier, $rate);

        $response = $status->limitExceeded()
            ? $this->limitExceededHandler->handle($request)->withStatus(429)
            : $handler->handle($request);

        return $this->setRateLimitHeaders($response, $status);
    }

    private function setRateLimitHeaders(ResponseInterface $response, Status $rateLimitStatus): ResponseInterface
    {
        return $response
            ->withHeader(self::HEADER_LIMIT, (string) $rateLimitStatus->getLimit())
            ->withHeader(self::HEADER_REMAINING, (string) $rateLimitStatus->getRemainingAttempts())
            ->withHeader(self::HEADER_RESET, (string) $rateLimitStatus->getResetAt()->getTimestamp());
    }
}
