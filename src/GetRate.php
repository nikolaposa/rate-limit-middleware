<?php

declare(strict_types=1);

namespace RateLimit\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use RateLimit\Rate;

interface GetRate
{
    public function forRequest(ServerRequestInterface $request): ?Rate;
}
