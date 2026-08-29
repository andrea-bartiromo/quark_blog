<?php

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Jobs\PublishSocialDistribution;
use App\Models\SocialPublication;
use Illuminate\Support\Facades\Log;
use Throwable;

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

            try {
                $publication = SocialPublication::firstOrCreate(
                    ['article_id' => $event->article->id, 'channel' => $channel, 'event_key' => $event->eventKey],
                    ['status' => SocialPublication::STATUS_PENDING],
                );

                if ($publication->wasRecentlyCreated) {
                    PublishSocialDistribution::dispatch($publication->id);
                }
            } catch (Throwable $exception) {
                Log::warning('Consegna social non accodata; la pubblicazione dell’articolo resta valida.', [
                    'article_id' => $event->article->id,
                    'channel' => (string) $channel,
                    'error_code' => 'social_enqueue_failed',
                    'error_class' => class_basename($exception),
                ]);
            }
        }
    }
}
