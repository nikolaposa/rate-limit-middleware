<?php

declare(strict_types=1);

namespace RateLimit\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use RateLimit\Rate;
use RateLimit\SilentRateLimiter;
use RateLimit\Status;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public const HEADER_LIMIT = 'X-RateLimit-Limit';
    public const HEADER_REMAINING = 'X-RateLimit-Remaining';
    public const HEADER_RESET = 'X-RateLimit-Reset';

    private SilentRateLimiter $rateLimiter;
    private string $endpointName;
    private ResolveUserIdentity $resolveUserIdentity;
    private RequestHandlerInterface $limitExceededHandler;

    public function __construct(
        SilentRateLimiter $rateLimiter,
        string $endpointName,
        ResolveUserIdentity $resolveUserIdentity,
        RequestHandlerInterface $limitExceededHandler
    ) {
        $this->rateLimiter = $rateLimiter;
        $this->endpointName = $endpointName;
        $this->resolveUserIdentity = $resolveUserIdentity;
        $this->limitExceededHandler = $limitExceededHandler;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identifier = $this->getIdentifier($request);

        $status = $this->rateLimiter->limitSilently($identifier);

        $response = $status->limitExceeded()
            ? $this->limitExceededHandler->handle($request)->withStatus(429)
            : $handler->handle($request);

        return $this->setRateLimitHeaders($response, $status);
    }

    private function getIdentifier(ServerRequestInterface $request): string
    {
        $userIdentity = $this->resolveUserIdentity->fromRequest($request);

        return trim("{$this->endpointName}:$userIdentity", ':');
    }

    private function setRateLimitHeaders(ResponseInterface $response, Status $rateLimitStatus): ResponseInterface
    {
        return $response
            ->withHeader(self::HEADER_LIMIT, (string) $rateLimitStatus->getLimit())
            ->withHeader(self::HEADER_REMAINING, (string) $rateLimitStatus->getRemainingAttempts())
            ->withHeader(self::HEADER_RESET, (string) $rateLimitStatus->getResetAt()->getTimestamp());
    }
}
