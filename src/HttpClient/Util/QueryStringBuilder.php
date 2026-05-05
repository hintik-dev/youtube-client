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

namespace Hintik\YouTube\HttpClient\Util;

/** @internal */
final class QueryStringBuilder
{
    public static function build(array $query): string
    {
        return \sprintf('?%s', \implode('&', \array_map(
            fn($value, $key): string => self::encode($value, $key),
            $query,
            \array_keys($query)
        )));
    }

    private static function encode(mixed $query, mixed $prefix): string
    {
        if (!\is_array($query)) {
            return self::rawurlencode($prefix) . '=' . self::rawurlencode($query);
        }

        $isList = self::isList($query);

        return \implode('&', \array_map(
            function ($value, $key) use ($prefix, $isList): string {
                $prefix = $isList ? $prefix . '[]' : $prefix . '[' . $key . ']';
                return self::encode($value, $prefix);
            },
            $query,
            \array_keys($query)
        ));
    }

    private static function isList(array $query): bool
    {
        if (0 === \count($query) || !isset($query[0])) {
            return false;
        }

        return \array_keys($query) === \range(0, \count($query) - 1);
    }

    private static function rawurlencode(mixed $value): string
    {
        if (false === $value) {
            return '0';
        }

        return \rawurlencode((string) $value);
    }
}
