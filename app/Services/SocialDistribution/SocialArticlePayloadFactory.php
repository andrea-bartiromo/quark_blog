<?php

namespace App\Services\SocialDistribution;

use App\Models\Article;
use App\Models\SocialPublication;
use RuntimeException;

class SocialArticlePayloadFactory
{
    public function forPublication(SocialPublication $publication): SocialArticlePayload
    {
        $article = Article::published()->find($publication->article_id);

        if (! $article) {
            throw new RuntimeException('article_no_longer_published');
        }

        return new SocialArticlePayload(
            articleId: $article->id,
            title: $article->metaOgTitle(),
            copy: $article->metaOgDescription(),
            canonicalUrl: $article->metaCanonicalUrl(),
            imageUrl: $article->metaOgImage(),
        );
    }
}
