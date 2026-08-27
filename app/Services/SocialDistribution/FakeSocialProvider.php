<?php

namespace App\Services\SocialDistribution;

use App\Contracts\SocialProvider;
use App\Models\SocialPublication;

class FakeSocialProvider implements SocialProvider
{
    /** @var array<string, SocialPublishResult> */
    private array $published = [];

    public ?SocialProviderException $nextFailure = null;

    public function publishArticleDistribution(SocialPublication $publication): SocialPublishResult
    {
        if ($this->nextFailure) {
            $failure = $this->nextFailure;
            $this->nextFailure = null;
            throw $failure;
        }

        return $this->published[$publication->event_key] ??= new SocialPublishResult(
            'fake-'.hash('sha256', $publication->channel.'|'.$publication->event_key),
            null,
        );
    }
}
