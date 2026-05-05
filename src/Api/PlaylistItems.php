<?php

declare(strict_types=1);

namespace Hintik\YouTube\Api;

use Hintik\YouTube\Exception\RuntimeException;

class PlaylistItems extends AbstractApi
{
    public function list(array $parameters): array
    {
        $resolver = $this->createOptionsResolver();

        $resolver->setDefined('part')
            ->setAllowedTypes('part', 'string')
            ->setDefault('part', 'snippet');

        $resolver->setRequired('playlistId')
            ->setAllowedTypes('playlistId', 'string');

        $resolver->setDefined('maxResults')
            ->setAllowedTypes('maxResults', 'integer');

        $resolver->setDefined('pageToken')
            ->setAllowedTypes('pageToken', 'string');

        $response = $this->get('playlistItems', $resolver->resolve($parameters));

        if (!is_array($response)) {
            throw new RuntimeException('Unexpected response format from YouTube PlaylistItems API.');
        }

        return $response;
    }
}
