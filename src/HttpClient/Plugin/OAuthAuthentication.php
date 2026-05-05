<?php

declare(strict_types=1);

namespace Hintik\YouTube\HttpClient\Plugin;

use Hintik\YouTube\OAuth\OAuthTokenProvider;
use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;

final class OAuthAuthentication implements Plugin
{
    public function __construct(
        private readonly OAuthTokenProvider $provider,
    ) {
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        return $next($request->withHeader('Authorization', \sprintf('Bearer %s', $this->provider->getAccessToken())));
    }
}
