<?php

declare(strict_types=1);

namespace Hintik\YouTube\OAuth;

use DateTimeImmutable;
use Hintik\YouTube\Exception\RuntimeException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

final class OAuthTokenProvider
{
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private ?string $cachedToken = null;
    private ?DateTimeImmutable $tokenExpiry = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
    }

    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null
            && $this->tokenExpiry !== null
            && $this->tokenExpiry > new DateTimeImmutable()
        ) {
            return $this->cachedToken;
        }

        $httpClient = Psr18ClientDiscovery::find();
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $body = \http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ]);

        $request = $requestFactory->createRequest('POST', self::TOKEN_URL)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($streamFactory->createStream($body));

        $response = $httpClient->sendRequest($request);

        /** @var array<string, mixed>|null $data */
        $data = \json_decode((string) $response->getBody(), true);

        if (!\is_array($data) || !isset($data['access_token']) || !\is_string($data['access_token'])) {
            $error = \is_array($data)
                ? ($data['error_description'] ?? $data['error'] ?? 'unknown error')
                : 'invalid response';
            throw new RuntimeException(\sprintf('Failed to obtain OAuth access token: %s', $error));
        }

        $this->cachedToken = $data['access_token'];
        $expiresIn = isset($data['expires_in']) && \is_int($data['expires_in']) ? $data['expires_in'] : 3600;
        $this->tokenExpiry = new DateTimeImmutable(\sprintf('+%d seconds', $expiresIn - 60));

        return $this->cachedToken;
    }
}
