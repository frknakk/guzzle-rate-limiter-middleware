<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use Psr\Http\Message\RequestInterface;

interface Store
{
    public function get(): array;

    public function push(int $timestamp, int $limit);
}
