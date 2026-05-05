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

use Hintik\YouTube\Exception\RuntimeException;
use Hintik\YouTube\HttpClient\Message\ResponseMediator;
use Hintik\YouTube\HttpClient\Util\JsonArray;
use Hintik\YouTube\HttpClient\Util\QueryStringBuilder;
use Hintik\YouTube\YouTubeClient;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
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
        $params['key'] = $this->client->getApiKey();

        return $this->client->getHttpClient()->get(self::prepareUri($uri, $params), $headers);
    }

    protected function get(string $uri, array $params = [], array $headers = []): array|string
    {
        return ResponseMediator::getContent($this->getAsResponse($uri, $params, $headers));
    }

    protected function post(string $uri, array $params = [], array $headers = [], array $files = [], array $uriParams = []): array|string
    {
        if (0 < \count($files)) {
            $builder = $this->createMultipartStreamBuilder($params, $files);
            $body = self::prepareMultipartBody($builder);
            $headers = self::addMultipartContentType($headers, $builder);
        } else {
            $body = self::prepareJsonBody($params);

            if (null !== $body) {
                $headers = self::addJsonContentType($headers);
            }
        }

        $response = $this->client->getHttpClient()->post(self::prepareUri($uri, $uriParams), $headers, $body);

        return ResponseMediator::getContent($response);
    }

    protected function put(string $uri, array $params = [], array $headers = [], array $files = []): array|string
    {
        if (0 < \count($files)) {
            $builder = $this->createMultipartStreamBuilder($params, $files);
            $body = self::prepareMultipartBody($builder);
            $headers = self::addMultipartContentType($headers, $builder);
        } else {
            $body = self::prepareJsonBody($params);

            if (null !== $body) {
                $headers = self::addJsonContentType($headers);
            }
        }

        $response = $this->client->getHttpClient()->put(self::prepareUri($uri), $headers, $body ?? '');

        return ResponseMediator::getContent($response);
    }

    protected function patch(string $uri, array $params = [], array $headers = [], array $files = []): array|string
    {
        if (0 < \count($files)) {
            $builder = $this->createMultipartStreamBuilder($params, $files);
            $body = self::prepareMultipartBody($builder);
            $headers = self::addMultipartContentType($headers, $builder);
        } else {
            $body = self::prepareJsonBody($params);

            if (null !== $body) {
                $headers = self::addJsonContentType($headers);
            }
        }

        $response = $this->client->getHttpClient()->patch(self::prepareUri($uri), $headers, $body ?? '');

        return ResponseMediator::getContent($response);
    }

    protected function putFile(string $uri, string $file, array $headers = [], array $uriParams = []): array|string
    {
        $resource = self::tryFopen($file, 'r');
        $body = $this->client->getStreamFactory()->createStreamFromResource($resource);

        if ($body->isReadable()) {
            $headers = \array_merge([ResponseMediator::CONTENT_TYPE_HEADER => self::guessFileContentType($file)], $headers);
        }

        $response = $this->client->getHttpClient()->put(self::prepareUri($uri, $uriParams), $headers, $body);

        return ResponseMediator::getContent($response);
    }

    protected function delete(string $uri, array $params = [], array $headers = []): array|string
    {
        $body = self::prepareJsonBody($params);

        if (null !== $body) {
            $headers = self::addJsonContentType($headers);
        }

        $response = $this->client->getHttpClient()->delete(self::prepareUri($uri), $headers, $body ?? '');

        return ResponseMediator::getContent($response);
    }

    protected static function encodePath(int|string $uri): string
    {
        return \rawurlencode((string) $uri);
    }

    protected function createOptionsResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined('page')
            ->setAllowedTypes('page', 'int')
            ->setAllowedValues('page', fn($value): bool => $value > 0);
        $resolver->setDefined('per_page')
            ->setAllowedTypes('per_page', 'int')
            ->setAllowedValues('per_page', fn($value): bool => $value > 0 && $value <= 100);

        return $resolver;
    }

    private static function prepareUri(string $uri, array $query = []): string
    {
        $query = \array_filter($query, fn($value): bool => null !== $value);

        return \sprintf('%s%s%s', self::URI_PREFIX, $uri, QueryStringBuilder::build($query));
    }

    private function createMultipartStreamBuilder(array $params = [], array $files = []): MultipartStreamBuilder
    {
        $builder = new MultipartStreamBuilder($this->client->getStreamFactory());

        foreach ($params as $name => $value) {
            $builder->addResource($name, $value);
        }

        foreach ($files as $name => $file) {
            $builder->addResource($name, self::tryFopen($file, 'r'), [
                'headers' => [ResponseMediator::CONTENT_TYPE_HEADER => self::guessFileContentType($file)],
                'filename' => \basename($file),
            ]);
        }

        return $builder;
    }

    private static function prepareMultipartBody(MultipartStreamBuilder $builder): StreamInterface
    {
        return $builder->build();
    }

    private static function addMultipartContentType(array $headers, MultipartStreamBuilder $builder): array
    {
        $contentType = \sprintf('%s; boundary=%s', ResponseMediator::MULTIPART_CONTENT_TYPE, $builder->getBoundary());

        return \array_merge([ResponseMediator::CONTENT_TYPE_HEADER => $contentType], $headers);
    }

    private static function prepareJsonBody(array $params): ?string
    {
        $params = \array_filter($params, fn($value): bool => null !== $value);

        return 0 === \count($params) ? null : JsonArray::encode($params);
    }

    private static function addJsonContentType(array $headers): array
    {
        return \array_merge([ResponseMediator::CONTENT_TYPE_HEADER => ResponseMediator::JSON_CONTENT_TYPE], $headers);
    }

    /**
     * @return resource
     * @see https://github.com/guzzle/psr7/blob/1.6.1/src/functions.php#L287-L320
     */
    private static function tryFopen(string $filename, string $mode)
    {
        $ex = null;
        // @phpstan-ignore-next-line
        \set_error_handler(function () use ($filename, $mode, &$ex): void {
            $ex = new RuntimeException(\sprintf(
                'Unable to open %s using mode %s: %s',
                $filename,
                $mode,
                \func_get_args()[1]
            ));
        });

        $handle = \fopen($filename, $mode);
        \restore_error_handler();

        if (null !== $ex) {
            throw $ex;
        }

        /** @var resource */
        return $handle;
    }

    private static function guessFileContentType(string $file): string
    {
        if (!\class_exists(\finfo::class, false)) {
            return ResponseMediator::STREAM_CONTENT_TYPE;
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $type = $finfo->file($file);

        return false !== $type ? $type : ResponseMediator::STREAM_CONTENT_TYPE;
    }
}