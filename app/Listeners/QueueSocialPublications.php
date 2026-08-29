<?php

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Jobs\PublishSocialDistribution;
use App\Models\SocialPublication;

class QueueSocialPublications
{
    public function handle(ArticlePublished $event): void
    {
        if (! config('social_distribution.enabled', false)) {
            return;
        }

        foreach (config('social_distribution.channels', []) as $channel => $settings) {
            if (! ($settings['enabled'] ?? false)) {
                continue;
            }

            $publication = SocialPublication::firstOrCreate(
                ['article_id' => $event->article->id, 'channel' => $channel, 'event_key' => $event->eventKey],
                ['status' => SocialPublication::STATUS_PENDING],
            );

            if ($publication->wasRecentlyCreated) {
                PublishSocialDistribution::dispatch($publication->id);
            }
        }
    }
}
