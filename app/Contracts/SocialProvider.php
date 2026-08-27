<?php

namespace App\Contracts;

use App\Models\SocialPublication;
use App\Services\SocialDistribution\SocialPublishResult;

interface SocialProvider
{
    public function publishArticleDistribution(SocialPublication $publication): SocialPublishResult;
}
