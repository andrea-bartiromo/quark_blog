<?php

namespace App\Services\SocialDistribution;

final readonly class SocialArticlePayload
{
    public function __construct(
        public int $articleId,
        public string $title,
        public string $copy,
        public string $canonicalUrl,
        public ?string $imageUrl,
    ) {}
}
