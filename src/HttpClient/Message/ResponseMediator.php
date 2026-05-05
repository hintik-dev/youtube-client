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

namespace Hintik\YouTube\HttpClient\Message;

use Hintik\YouTube\Exception\RuntimeException;
use Hintik\YouTube\HttpClient\Util\JsonArray;
use Psr\Http\Message\ResponseInterface;

final class ResponseMediator
{
    public const CONTENT_TYPE_HEADER = 'Content-Type';
    public const JSON_CONTENT_TYPE = 'application/json';
    public const STREAM_CONTENT_TYPE = 'application/octet-stream';
    public const MULTIPART_CONTENT_TYPE = 'multipart/form-data';

    public static function getContent(ResponseInterface $response): array|string
    {
        $body = (string) $response->getBody();

        if (!\in_array($body, ['', 'null', 'true', 'false'], true) && 0 === \strpos($response->getHeaderLine(self::CONTENT_TYPE_HEADER), self::JSON_CONTENT_TYPE)) {
            return JsonArray::decode($body);
        }

        return $body;
    }

    public static function getPagination(ResponseInterface $response): array
    {
        $header = self::getHeader($response, 'Link');

        if (null === $header) {
            return [];
        }

        $pagination = [];
        foreach (\explode(',', $header) as $link) {
            \preg_match('/<(.*)>; rel="(.*)"/i', \trim($link, ','), $match);

            /** @var string[] $match */
            if (3 === \count($match)) {
                $pagination[$match[2]] = $match[1];
            }
        }

        return $pagination;
    }

    public static function getErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $content = self::getContent($response);
        } catch (RuntimeException) {
            return null;
        }

        if (!\is_array($content)) {
            return null;
        }

        if (isset($content['message'])) {
            $message = $content['message'];

            if (\is_string($message)) {
                return $message;
            }

            if (\is_array($message)) {
                return self::getMessageAsString($content['message']);
            }
        }

        if (isset($content['error_description']) && \is_string($content['error_description'])) {
            return $content['error_description'];
        }

        if (isset($content['error']['message']) && \is_string($content['error']['message'])) {
            return $content['error']['message'];
        }

        if (isset($content['error']) && \is_string($content['error'])) {
            return $content['error'];
        }

        return null;
    }

    private static function getHeader(ResponseInterface $response, string $name): ?string
    {
        $headers = $response->getHeader($name);

        return \array_shift($headers);
    }

    private static function getMessageAsString(array $message): string
    {
        $format = '"%s" %s';
        $errors = [];

        foreach ($message as $field => $messages) {
            if (\is_array($messages)) {
                $messages = \array_unique($messages);
                foreach ($messages as $error) {
                    $errors[] = \sprintf($format, $field, $error);
                }
            } elseif (\is_int($field)) {
                $errors[] = $messages;
            } else {
                $errors[] = \sprintf($format, $field, $messages);
            }
        }

        return \implode(', ', $errors);
    }
}
