<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticlePublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public readonly string $eventKey;

    public function __construct(public readonly Article $article)
    {
        $publishedAt = $article->published_at?->utc()->format('Ymd\THis\Z') ?? 'unknown';
        $this->eventKey = 'article:'.$article->id.':published:'.$publishedAt;
    }
}
