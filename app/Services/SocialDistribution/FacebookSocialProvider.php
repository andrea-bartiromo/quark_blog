<?php

namespace App\Services\SocialDistribution;

use App\Contracts\SocialProvider;
use App\Models\Article;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacebookSocialProvider implements SocialProvider
{
    public function __construct(private readonly UtmLinkGenerator $utm) {}

    public function publishArticleDistribution(SocialArticlePayload $payload, string $idempotencyKey): SocialPublishResult
    {
        $pageId = (string) config('social_distribution.facebook.page_id');
        $token = (string) config('social_distribution.facebook.access_token');

        if ($pageId === '' || $token === '') {
            throw new SocialProviderException('facebook_not_configured', false);
        }

        $article = Article::published()->find($payload->articleId);
        if (! $article) {
            throw new SocialProviderException('article_no_longer_published', false);
        }

        $body = [
            'message' => $this->message($payload),
            'link' => $this->utm->forArticle($article, UtmLinkGenerator::CHANNEL_FACEBOOK),
            'access_token' => $token,
        ];

        if ($this->validPublicImage($payload->imageUrl)) {
            $body['picture'] = $payload->imageUrl;
        }

        $base = rtrim((string) config('social_distribution.facebook.graph_url'), '/');
        $version = trim((string) config('social_distribution.facebook.graph_version'), '/');
        $response = Http::asForm()
            ->timeout((int) config('social_distribution.facebook.timeout_seconds', 10))
            ->post($base.'/'.$version.'/'.$pageId.'/feed', $body);

        if (! $response->successful()) {
            throw $this->mappedException($response);
        }

        $remoteId = $response->json('id');
        if (! is_string($remoteId) || $remoteId === '') {
            throw new SocialProviderException('facebook_invalid_response', false);
        }

        $remoteUrl = $response->json('permalink_url');

        return new SocialPublishResult($remoteId, is_string($remoteUrl) ? $remoteUrl : null);
    }

    private function message(SocialArticlePayload $payload): string
    {
        return mb_substr(trim($payload->title."\n\n".$payload->copy), 0, 500);
    }

    private function validPublicImage(?string $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function mappedException(Response $response): SocialProviderException
    {
        $code = (int) $response->json('error.code', 0);
        $status = $response->status();

        if ($code === 190) {
            return new SocialProviderException('facebook_token_expired', false);
        }

        if ($status === 429 || in_array($code, [4, 17, 32, 613], true)) {
            return new SocialProviderException('facebook_rate_limited', true);
        }

        return new SocialProviderException(
            $status >= 500 ? 'facebook_server_error' : 'facebook_request_rejected',
            $status >= 500,
        );
    }
}
