<?php

namespace App\Services\SocialDistribution;

use App\Contracts\SocialProvider;
use RuntimeException;

class SocialProviderRegistry
{
    public function forChannel(string $channel): SocialProvider
    {
        $class = config('social_distribution.channels.'.$channel.'.provider');

        if (! is_string($class) || $class === '') {
            throw new RuntimeException('Provider social non configurato per il canale.');
        }

        $provider = app($class);

        if (! $provider instanceof SocialProvider) {
            throw new RuntimeException('Provider social non conforme al contratto.');
        }

        return $provider;
    }
}
