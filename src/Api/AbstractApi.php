<?php

declare(strict_types=1);

/*
 * This file is part of the Gitlab API library.
 *
 * (c) Matt Humphrey <matth@windsor-telecom.co.uk>
 * (c) Graham Campbell <hello@gjcampbell.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hintik\YouTube\Api;

use Hintik\YouTube\HttpClient\Message\ResponseMediator;
use Hintik\YouTube\HttpClient\Util\QueryStringBuilder;
use Hintik\YouTube\YouTubeClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractApi
{
    private const string URI_PREFIX = 'https://www.googleapis.com/youtube/v3/';

    public function __construct(
        private YouTubeClient $client,
    ) {
    }

    protected function getAsResponse(string $uri, array $params = [], array $headers = []): ResponseInterface
    {
        if ($this->client->getApiKey() !== null) {
            $params['key'] = $this->client->getApiKey();
        }

        return $this->client->getHttpClient()->get(self::prepareUri($uri, $params), $headers);
    }

    protected function get(string $uri, array $params = [], array $headers = []): array|string
    {
        return ResponseMediator::getContent($this->getAsResponse($uri, $params, $headers));
    }

    protected static function encodePath(int|string $uri): string
    {
        return \rawurlencode((string) $uri);
    }

    protected function createOptionsResolver(): OptionsResolver
    {
        return new OptionsResolver();
    }

    private static function prepareUri(string $uri, array $query = []): string
    {
        $query = \array_filter($query, fn($value): bool => null !== $value);

        return \sprintf('%s%s%s', self::URI_PREFIX, $uri, QueryStringBuilder::build($query));
    }
}
