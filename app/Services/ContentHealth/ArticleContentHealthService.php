<?php

namespace App\Services\ContentHealth;

use App\Models\Article;
use Illuminate\Support\Collection;

class ArticleContentHealthService
{
    public const STATUS_OK = 'OK';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_NOT_APPLICABLE = 'NOT_APPLICABLE';

    /**
     * Transparent editorial checks only: no score, no write, no publishing gate.
     *
     * @return Collection<int, array{id:string,label:string,status:string,reason:string,action_url:?string}>
     */
    public function evaluate(Article $article): Collection
    {
        return collect([
            $this->cover($article),
            $this->coverAlt($article),
            $this->coverAttribution($article),
            $this->summary($article),
            $this->seoMetadata($article),
            $this->internalLinks($article),
            $this->category($article),
            $this->percorso($article),
            $this->sources($article),
            $this->freshness(),
        ]);
    }

    private function cover(Article $article): array
    {
        return $this->check(
            'cover',
            'Copertina',
            filled($article->cover_image),
            'Copertina presente.',
            'Nessuna copertina associata all’articolo.'
        );
    }

    private function coverAlt(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->notApplicable('cover_alt', 'Testo alternativo', 'Nessuna copertina da descrivere.');
        }

        return $this->check(
            'cover_alt',
            'Testo alternativo',
            filled($article->cover_alt),
            'Testo alternativo presente.',
            'La copertina non ha un testo alternativo editoriale.'
        );
    }

    private function coverAttribution(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->notApplicable('cover_attribution', 'Fonte e credito immagine', 'Nessuna copertina da attribuire.');
        }

        $hasAttribution = filled($article->cover_credit)
            && (filled($article->cover_source) || filled($article->cover_source_url));

        return $this->check(
            'cover_attribution',
            'Fonte e credito immagine',
            $hasAttribution,
            'Credito e fonte della copertina sono presenti.',
            'La copertina non ha ancora sia il credito sia una fonte verificabile.'
        );
    }

    private function summary(Article $article): array
    {
        return $this->check(
            'summary',
            'Sommario',
            filled($article->excerpt),
            'Sommario presente.',
            'Il sommario editoriale è vuoto.'
        );
    }

    private function seoMetadata(Article $article): array
    {
        $missing = collect([
            'SEO title' => $article->seo_title,
            'SEO description' => $article->seo_description,
        ])->filter(fn ($value) => blank($value))->keys()->all();

        if ($missing === []) {
            return $this->ok('seo_metadata', 'Metadati SEO', 'SEO title e SEO description editoriali sono presenti.');
        }

        return $this->warning(
            'seo_metadata',
            'Metadati SEO',
            'Mancano: '.implode(', ', $missing).'. I fallback del sito continuano a funzionare; questo è un warning editoriale.'
        );
    }

    private function internalLinks(Article $article): array
    {
        $body = (string) $article->body;
        $hasInternalArticleLink = preg_match(
            '~href=["\'](?:https?://[^/"\']+)?/articolo/[^"\'#?]+~i',
            $body
        ) === 1;

        return $this->check(
            'internal_links',
            'Collegamenti interni',
            $hasInternalArticleLink,
            'Il corpo contiene almeno un collegamento a un altro articolo Kairus.',
            'Non è stato rilevato alcun collegamento interno verso /articolo/.'
        );
    }

    private function category(Article $article): array
    {
        return $this->check(
            'category',
            'Categoria',
            filled($article->category),
            'Categoria principale presente.',
            'La categoria principale non è valorizzata.'
        );
    }

    private function percorso(Article $article): array
    {
        if (! $article->relationLoaded('contentClusters')) {
            $article->load('contentClusters:id');
        }

        return $this->check(
            'percorso',
            'Percorso',
            $article->contentClusters->isNotEmpty(),
            'L’articolo appartiene ad almeno un Percorso.',
            'L’articolo non appartiene ad alcun Percorso. Non è un errore: valuta se un Percorso esistente è pertinente.'
        );
    }

    private function sources(Article $article): array
    {
        return $this->check(
            'sources',
            'Fonti / bibliografia',
            filled($article->primary_sources),
            'Sono presenti fonti primarie o bibliografia.',
            'Il campo fonti primarie / bibliografia è vuoto.'
        );
    }

    private function freshness(): array
    {
        return $this->notApplicable(
            'freshness',
            'Freshness',
            'Nessuna soglia editoriale di anzianità è definita nel dominio corrente; la foundation non inventa un limite temporale.'
        );
    }

    private function check(string $id, string $label, bool $ok, string $okReason, string $warningReason): array
    {
        return $ok
            ? $this->ok($id, $label, $okReason)
            : $this->warning($id, $label, $warningReason);
    }

    private function ok(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_OK, $reason);
    }

    private function warning(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_WARNING, $reason);
    }

    private function notApplicable(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_NOT_APPLICABLE, $reason);
    }

    private function result(string $id, string $label, string $status, string $reason): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'reason' => $reason,
            'action_url' => null,
        ];
    }
}
