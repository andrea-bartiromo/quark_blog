<?php

namespace App\Services\SocialDistribution;

final readonly class SocialPublishResult
{
    public function __construct(public string $remoteId, public ?string $remoteUrl = null) {}
}
