<?php

namespace App\Services\Discover;

use App\Models\Article;
use App\Services\ContentHealth\ArticleContentHealthService;
use Illuminate\Support\Collection;

/**
 * Audit editoriale/tecnico dei prerequisiti noti e pubblicamente
 * documentati per l'idoneità a Google Discover (immagine grande presente,
 * autore dichiarato, articolo effettivamente pubblico, nessuna
 * incoerenza dato/file).
 *
 * Non fa MAI una chiamata di rete a Google, non consulta la Search
 * Console API, non calcola un punteggio: PASS/WARNING/ERROR spiegabili,
 * un prerequisito verificabile alla volta. Anche a tutti i controlli in
 * PASS, questo servizio non garantisce né promette l'inclusione reale in
 * Discover — quella dipende da segnali editoriali ed algoritmici fuori
 * dal controllo del sito (Batch 04B, Mission 7).
 *
 * Riusa ArticleContentHealthService::evaluate() (Content Health, PR #280)
 * per il controllo dei metadati SEO invece di duplicarne la logica —
 * unico punto di lettura, mai scritto qui.
 */
class DiscoverReadinessService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_ERROR = 'ERROR';

    /**
     * Soglia pubblicamente documentata da Google per l'immagine "large"
     * (max-image-preview:large / Discover): almeno 1200px di larghezza.
     * Non è un'invenzione di questo progetto — sotto questa soglia
     * un'immagine resta indicizzabile ma non idonea al formato grande.
     */
    public const MIN_LARGE_IMAGE_WIDTH = 1200;

    public function __construct(
        private readonly ArticleContentHealthService $contentHealth,
    ) {}

    /**
     * @return Collection<int, array{id:string,label:string,status:string,reason:string,action_url:?string}>
     */
    public function evaluate(Article $article): Collection
    {
        return collect([
            $this->publicationState($article),
            $this->coverPresence($article),
            $this->coverFileIntegrity($article),
            $this->coverImageSize($article),
            $this->author($article),
            $this->seoMetadata($article),
        ])->filter()->values();
    }

    private function publicationState(Article $article): array
    {
        $isLivePublic = $article->status === Article::STATUS_PUBLISHED
            && $article->published_at !== null
            && $article->published_at->isPast();

        if ($isLivePublic) {
            return $this->pass('publication_state', 'Stato di pubblicazione', 'L’articolo è pubblicato e già visibile pubblicamente.');
        }

        return $this->error(
            'publication_state',
            'Stato di pubblicazione',
            $article->status === Article::STATUS_SCHEDULED
                ? 'L’articolo è programmato ma non ancora pubblico: Discover può indicizzare solo contenuto già live.'
                : 'L’articolo non è in stato pubblicato: Discover può indicizzare solo contenuto già live.'
        );
    }

    private function coverPresence(Article $article): array
    {
        if (filled($article->cover_image)) {
            return $this->pass('cover_image', 'Immagine di copertina', 'È presente un’immagine di copertina.');
        }

        return $this->error(
            'cover_image',
            'Immagine di copertina',
            'Nessuna copertina associata: senza un’immagine grande l’articolo non è idoneo al formato visivo di Discover.'
        );
    }

    private function coverFileIntegrity(Article $article): ?array
    {
        if (blank($article->cover_image)) {
            return null;
        }

        if ($this->coverImageInfo($article) !== false) {
            return $this->pass('cover_file_integrity', 'Integrità del file di copertina', 'Il file dichiarato in copertina esiste ed è leggibile come immagine.');
        }

        return $this->error(
            'cover_file_integrity',
            'Integrità del file di copertina',
            'La copertina dichiarata in archivio non esiste o non è leggibile come immagine sul disco: dato e file non coincidono.'
        );
    }

    private function coverImageSize(Article $article): ?array
    {
        if (blank($article->cover_image)) {
            return null;
        }

        $info = $this->coverImageInfo($article);

        if ($info === false) {
            // File assente/illeggibile: già segnalato da cover_file_integrity,
            // qui non c'è nulla da misurare.
            return null;
        }

        $width = $info[0];

        if ($width >= self::MIN_LARGE_IMAGE_WIDTH) {
            return $this->pass(
                'cover_image_size',
                'Dimensione immagine di copertina',
                "Larghezza reale {$width}px: soddisfa la soglia Google per il formato immagine grande (≥".self::MIN_LARGE_IMAGE_WIDTH.'px).'
            );
        }

        return $this->warning(
            'cover_image_size',
            'Dimensione immagine di copertina',
            "Larghezza reale {$width}px, sotto la soglia Google per il formato immagine grande (≥".self::MIN_LARGE_IMAGE_WIDTH.'px): l’articolo resta indicizzabile ma non idoneo al formato visivo prominente.'
        );
    }

    private function author(Article $article): array
    {
        if (! $article->relationLoaded('author')) {
            $article->load('author:id,name');
        }

        if ($article->author !== null && filled($article->author->name)) {
            return $this->pass('author', 'Autore', 'L’articolo ha un autore dichiarato.');
        }

        return $this->error(
            'author',
            'Autore',
            'Nessun autore dichiarato: la byline è un segnale editoriale atteso per contenuto giornalistico in Discover.'
        );
    }

    private function seoMetadata(Article $article): ?array
    {
        $check = $this->contentHealth->evaluate($article)->firstWhere('id', 'seo_metadata');

        if ($check === null || $check['status'] === ArticleContentHealthService::STATUS_NOT_APPLICABLE) {
            return null;
        }

        $status = $check['status'] === ArticleContentHealthService::STATUS_OK
            ? self::STATUS_PASS
            : self::STATUS_WARNING;

        return $this->result('seo_metadata', 'Metadati SEO', $status, $check['reason']);
    }

    /**
     * @return array{0:int,1:int,...}|false
     */
    private function coverImageInfo(Article $article): array|false
    {
        $path = public_path('assets/img/'.$article->cover_image);

        return is_file($path) ? @getimagesize($path) : false;
    }

    private function pass(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_PASS, $reason);
    }

    private function warning(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_WARNING, $reason);
    }

    private function error(string $id, string $label, string $reason): array
    {
        return $this->result($id, $label, self::STATUS_ERROR, $reason);
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
