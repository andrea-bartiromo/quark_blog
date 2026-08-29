<?php

namespace App\Services\SocialDistribution;

use App\Contracts\SocialProvider;
use Throwable;

class FakeSocialProvider implements SocialProvider
{
    /** @var array<string, SocialPublishResult> */
    private array $published = [];

    public ?Throwable $nextFailure = null;

    public function publishArticleDistribution(SocialArticlePayload $payload, string $idempotencyKey): SocialPublishResult
    {
        if ($this->nextFailure) {
            $failure = $this->nextFailure;
            $this->nextFailure = null;
            throw $failure;
        }

        return $this->published[$idempotencyKey] ??= new SocialPublishResult(
            'fake-'.hash('sha256', $idempotencyKey),
            null,
        );
    }
}
