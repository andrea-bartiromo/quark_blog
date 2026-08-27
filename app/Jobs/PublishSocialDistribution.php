<?php

namespace App\Jobs;

use App\Models\SocialPublication;
use App\Services\SocialDistribution\SocialProviderException;
use App\Services\SocialDistribution\SocialProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishSocialDistribution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $publicationId) {}

    public function handle(SocialProviderRegistry $providers): void
    {
        $publication = DB::transaction(function () {
            $row = SocialPublication::query()->lockForUpdate()->find($this->publicationId);

            if (! $row || ! in_array($row->status, [SocialPublication::STATUS_PENDING, SocialPublication::STATUS_RETRYABLE], true)) {
                return null;
            }

            if ($row->attempt_count >= (int) config('social_distribution.max_attempts', 3)) {
                $row->update(['status' => SocialPublication::STATUS_FAILED]);
                return null;
            }

            $row->update([
                'status' => SocialPublication::STATUS_PROCESSING,
                'attempt_count' => $row->attempt_count + 1,
                'last_attempted_at' => now(),
                'last_error_class' => null,
                'last_error_message' => null,
            ]);

            return $row->fresh();
        });

        if (! $publication) {
            return;
        }

        try {
            $result = $providers->forChannel($publication->channel)->publishArticleDistribution($publication);
            SocialPublication::whereKey($publication->id)
                ->where('status', SocialPublication::STATUS_PROCESSING)
                ->update([
                    'status' => SocialPublication::STATUS_SUCCEEDED,
                    'remote_id' => $result->remoteId,
                    'remote_url' => $result->remoteUrl,
                    'succeeded_at' => now(),
                ]);
        } catch (Throwable $exception) {
            $retryable = $exception instanceof SocialProviderException && $exception->retryable
                && $publication->attempt_count < (int) config('social_distribution.max_attempts', 3);

            SocialPublication::whereKey($publication->id)
                ->where('status', SocialPublication::STATUS_PROCESSING)
                ->update([
                    'status' => $retryable ? SocialPublication::STATUS_RETRYABLE : SocialPublication::STATUS_FAILED,
                    'last_error_class' => class_basename($exception),
                    'last_error_message' => mb_substr($exception->getMessage(), 0, 500),
                ]);

            if ($retryable) {
                throw $exception;
            }
        }
    }
}
