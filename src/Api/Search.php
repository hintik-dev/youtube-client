<?php

declare(strict_types=1);

namespace Hintik\YouTube\Api;

use DateTimeInterface;
use Hintik\YouTube\Exception\RuntimeException;
use Symfony\Component\OptionsResolver\Options;

class Search extends AbstractApi
{
    public function list(array $parameters): array
    {
        $resolver = $this->createOptionsResolver();

        $datetimeNormalizer = fn(Options $resolver, DateTimeInterface $value): string => $value->format('Y-m-d\TH:i:s\Z');

        $resolver->setDefined('part')
            ->setAllowedValues('part', ['id', 'snippet'])
            ->setDefault('part', 'snippet');

        $resolver->setDefined('type')
            ->setAllowedValues('type', ['video', 'channel', 'playlist']);

        $resolver->setDefined('q')
            ->setAllowedTypes('q', 'string');

        $resolver->setDefined('channelId')
            ->setAllowedTypes('channelId', 'string');

        $resolver->setDefined('publishedAfter')
            ->setAllowedTypes('publishedAfter', DateTimeInterface::class)
            ->setNormalizer('publishedAfter', $datetimeNormalizer);

        $resolver->setDefined('publishedBefore')
            ->setAllowedTypes('publishedBefore', DateTimeInterface::class)
            ->setNormalizer('publishedBefore', $datetimeNormalizer);

        $resolver->setDefined('order')
            ->setAllowedValues('order', ['date', 'rating', 'relevance', 'title', 'videoCount', 'viewCount'])
            ->setDefault('order', 'relevance');

        $resolver->setDefined('pageToken')
            ->setAllowedTypes('pageToken', 'string');

        $resolver->setDefined('maxResults')
            ->setAllowedTypes('maxResults', 'integer');

        $resolver->setDefined('videoDuration')
            ->setAllowedValues('videoDuration', ['any', 'long', 'medium', 'short']);

        $response = $this->get('search', $resolver->resolve($parameters));

        if (!is_array($response)) {
            throw new RuntimeException('Unexpected response format from YouTube Search API.');
        }

        return $response;
    }
}
