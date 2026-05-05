<?php

declare(strict_types=1);

namespace Hintik\YouTube;

use Hintik\YouTube\Api\PlaylistItems;
use Hintik\YouTube\Api\Search;
use Hintik\YouTube\HttpClient\Builder;
use Hintik\YouTube\HttpClient\Plugin\ExceptionThrower;
use Hintik\YouTube\HttpClient\Plugin\History;
use Hintik\YouTube\HttpClient\Plugin\OAuthAuthentication;
use Hintik\YouTube\OAuth\OAuthTokenProvider;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin\HistoryPlugin;
use Http\Client\Common\Plugin\RedirectPlugin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class YouTubeClient
{
    private Builder $httpClientBuilder;
    private History $responseHistory;

    private function __construct(
        private readonly ?string $apiKey,
    ) {
        $this->httpClientBuilder = $builder = new Builder();
        $this->responseHistory = new History();

        $builder->addPlugin(new ExceptionThrower());
        $builder->addPlugin(new HistoryPlugin($this->responseHistory));
        $builder->addPlugin(new RedirectPlugin());
    }

    public static function withApiKey(string $apiKey): self
    {
        return new self($apiKey);
    }

    public static function withOAuth(string $clientId, string $clientSecret, string $refreshToken): self
    {
        $client = new self(null);
        $provider = new OAuthTokenProvider($clientId, $clientSecret, $refreshToken);
        $client->httpClientBuilder->addPlugin(new OAuthAuthentication($provider));
        return $client;
    }

    public function search(): Search
    {
        return new Search($this);
    }

    public function playlistItems(): PlaylistItems
    {
        return new PlaylistItems($this);
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->responseHistory->getLastResponse();
    }

    public function getHttpClient(): HttpMethodsClientInterface
    {
        return $this->getHttpClientBuilder()->getHttpClient();
    }

    public function getStreamFactory(): StreamFactoryInterface
    {
        return $this->httpClientBuilder->getStreamFactory();
    }

    public function getHttpClientBuilder(): Builder
    {
        return $this->httpClientBuilder;
    }
}
