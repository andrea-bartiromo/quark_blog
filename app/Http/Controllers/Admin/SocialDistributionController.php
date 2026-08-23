<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\SocialDistribution\UtmLinkGenerator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialDistributionController extends Controller
{
    public function __construct(
        private readonly UtmLinkGenerator $generator,
    ) {}

    /**
     * Sola generazione, nessuna persistenza: un GET con querystring
     * ripetibile/condivisibile (es. per rigenerare lo stesso link),
     * coerente con la natura stateless del servizio — non introduce un
     * concetto di "campagna social" salvata nel database.
     */
    public function index(Request $request): View
    {
        $slug = is_string($request->input('slug')) ? trim($request->input('slug')) : $request->input('slug');
        $campaignInput = $request->input('campaign');
        $channelInput = $request->input('channel');
        $channel = is_string($channelInput) && array_key_exists($channelInput, UtmLinkGenerator::channelOptions())
            ? $channelInput
            : UtmLinkGenerator::CHANNEL_FACEBOOK;

        $article = null;
        $link = null;
        $error = null;

        if (filled($slug)) {
            $article = mb_strlen($slug) <= 255
                ? Article::published()->where('slug', $slug)->first()
                : null;

            if (! $article) {
                $error = 'Nessun articolo pubblicato con questo slug.';
            } else {
                try {
                    $link = $this->generator->forArticle($article, $channel, filled($campaignInput) ? $campaignInput : null);
                } catch (\InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        return view('admin.social-distribution.index', [
            'slug' => $slug,
            'campaignInput' => $campaignInput,
            'channel' => $channel,
            'channelOptions' => UtmLinkGenerator::channelOptions(),
            'article' => $article,
            'link' => $link,
            'error' => $error,
        ]);
    }
}
