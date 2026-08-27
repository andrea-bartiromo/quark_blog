<?php

namespace App\Contracts;

use App\Services\SocialDistribution\SocialArticlePayload;
use App\Services\SocialDistribution\SocialPublishResult;

interface SocialProvider
{
    public function publishArticleDistribution(SocialArticlePayload $payload, string $idempotencyKey): SocialPublishResult;
}
