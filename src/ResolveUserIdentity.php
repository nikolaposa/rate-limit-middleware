<?php

declare(strict_types=1);

namespace RateLimit\Middleware;

use Psr\Http\Message\ServerRequestInterface;

interface ResolveUserIdentity
{
    public function fromRequest(ServerRequestInterface $request): string;
}
