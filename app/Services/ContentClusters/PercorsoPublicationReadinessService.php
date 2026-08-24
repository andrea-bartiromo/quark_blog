<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterHealth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PercorsoPublicationReadinessService
{
    public function __construct(
        private readonly ContentClusterHealth $health,
        private readonly ContentClusterPublicSequence $publicSequence,
    ) {}

    /**
     * Warning-first, read-only readiness. It never changes or blocks a Percorso.
     *
     * Scheduling runtime is not assumed: until publish_at exists on ContentCluster,
     * publication_state deliberately exposes only the states the current domain can
     * actually prove.
     *
     * @return array{
     *     status:string,
     *     publication_state:string,
     *     lifecycle:string,
     *     public_prefix_count:int,
     *     findings:Collection<int,array{code:string,severity:string,message:string}>
     * }
     */
    public function evaluate(ContentCluster $cluster, ?\DateTimeInterface $publicationAt = null): array
    {
        $cluster->loadMissing(['articles', 'pillarArticle']);
        $health = $this->health->evaluate($cluster);
        $findings = collect();
        $ordered = $cluster->articles
            ->sortBy(fn (Article $article) => [$article->pivot?->position ?? PHP_INT_MAX, $article->id])
            ->values();

        $required = [
            'NAME_MISSING' => ['value' => $cluster->name, 'label' => 'Nome'],
            'SLUG_MISSING' => ['value' => $cluster->slug, 'label' => 'Slug'],
            'SHORT_DESCRIPTION_MISSING' => ['value' => $cluster->short_description, 'label' => 'Descrizione breve'],
            'DESCRIPTION_MISSING' => ['value' => $cluster->description, 'label' => 'Descrizione'],
        ];

        foreach ($required as $code => $field) {
            if (blank($field['value'])) {
                $findings->push($this->finding($code, 'ERROR', $field['label'].' mancante.'));
            }
        }

        foreach ([
            'SEO_TITLE_MISSING' => [$cluster->seo_title, 'SEO title'],
            'SEO_DESCRIPTION_MISSING' => [$cluster->seo_description, 'SEO description'],
            'COVER_MISSING' => [$cluster->cover_image, 'Cover'],
            'TAKEAWAYS_MISSING' => [$cluster->takeaways, 'Takeaways'],
            'GUIDING_QUESTIONS_MISSING' => [$cluster->guiding_questions, 'Guiding questions'],
            'CLOSING_MISSING' => [$cluster->closing_text, 'Testo conclusivo'],
            'CURATOR_NOTE_MISSING' => [$cluster->curator_note, 'Nota del curatore'],
        ] as $code => [$value, $label]) {
            if (blank($value)) {
                $findings->push($this->finding($code, 'WARNING', $label.' non compilato.'));
            }
        }

        foreach ($health['findings'] as $code) {
            // These are now-time facts. A future readiness evaluation replaces
            // them with the explicit publication-time prefix checks below.
            if ($publicationAt !== null && in_array($code, ['PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES'], true)) {
                continue;
            }

            $severity = in_array($code, ['EMPTY', 'NO_PILLAR', 'PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES', 'ORDERING_ISSUE'], true)
                ? 'ERROR'
                : 'WARNING';
            $findings->push($this->finding('HEALTH_'.$code, $severity, 'ContentClusterHealth segnala: '.$code.'.'));
        }

        if ($ordered->count() === 1) {
            $findings->push($this->finding('SINGLE_MEMBER', 'WARNING', 'Il Percorso contiene un solo articolo: valuta se esiste una progressione editoriale sufficiente.'));
        }

        // transition_text belongs to the CURRENT step and narrates the move to
        // the immediately following step. Therefore every non-terminal member
        // may need one, while a terminal NULL is explicitly valid.
        $nonTerminal = $ordered->take(max(0, $ordered->count() - 1));
        $missingTransitions = $nonTerminal
            ->filter(fn (Article $article) => blank($article->pivot?->transition_text))
            ->count();
        if ($missingTransitions > 0) {
            $findings->push($this->finding(
                'TRANSITION_TEXT_GAPS',
                'WARNING',
                $missingTransitions.' raccordo/i tra tappe non terminali non sono compilati.',
            ));
        }

        if ($publicationAt !== null) {
            $at = Carbon::instance(\DateTime::createFromInterface($publicationAt));
            $prefix = $this->publicPrefixAt($ordered, $at);

            if ($ordered->isNotEmpty() && $prefix->isEmpty()) {
                $findings->push($this->finding(
                    'NO_MEMBERS_AVAILABLE_AT_PUBLICATION',
                    'ERROR',
                    'La prima tappa non risulterebbe pubblica alla data prevista: il Percorso non avrebbe alcun prefisso percorribile.',
                ));
            } elseif ($prefix->count() < $ordered->count()) {
                $findings->push($this->finding(
                    'PUBLIC_SEQUENCE_BLOCKED_AT_PUBLICATION',
                    'WARNING',
                    'Il prefisso pubblico si fermerebbe alla prima tappa non disponibile; le tappe successive resterebbero correttamente nascoste.',
                ));
            }

            if ($cluster->pillarArticle !== null && ! $prefix->contains('id', $cluster->pillarArticle->id)) {
                $findings->push($this->finding(
                    'PILLAR_UNAVAILABLE_AT_PUBLICATION',
                    'ERROR',
                    'Il pillar non apparterrebbe al prefisso pubblicamente raggiungibile alla data prevista del Percorso.',
                ));
            }

            $publicPrefixCount = $prefix->count();
        } else {
            $current = $this->publicSequence->resolve($cluster);
            $prefix = $current['articles'];

            if ($ordered->isNotEmpty() && $prefix->isEmpty()) {
                $findings->push($this->finding(
                    'NO_PUBLIC_CONTIGUOUS_PREFIX',
                    'ERROR',
                    'La prima tappa non è pubblica: nessuna tappa del Percorso è pubblicamente percorribile.',
                ));
            } elseif ($current['has_hidden_remainder']) {
                $findings->push($this->finding(
                    'PUBLIC_SEQUENCE_BLOCKED',
                    'WARNING',
                    'La sequenza pubblica si ferma al primo gap; eventuali articoli pubblicati successivi restano correttamente non raggiungibili dal Percorso.',
                ));
            }

            if ($cluster->pillarArticle !== null && ! $prefix->contains('id', $cluster->pillarArticle->id)) {
                $findings->push($this->finding(
                    'PILLAR_OUTSIDE_PUBLIC_PREFIX',
                    'ERROR',
                    'Il pillar non appartiene al prefisso pubblicamente raggiungibile del Percorso.',
                ));
            }

            $findings->push($this->finding(
                'SCHEDULING_NOT_AVAILABLE',
                'INFO',
                'Percorsi Scheduling runtime non è disponibile: non viene inventato uno stato scheduled del Percorso.',
            ));
            $publicPrefixCount = $prefix->count();
        }

        return [
            'status' => match (true) {
                $findings->contains('severity', 'ERROR') => 'NOT READY',
                $findings->contains('severity', 'WARNING') => 'READY WITH WARNINGS',
                default => 'READY',
            },
            'publication_state' => $cluster->is_active ? 'ACTIVE_IMMEDIATE_LEGACY' : 'INACTIVE',
            'lifecycle' => $cluster->lifecycle_status,
            'public_prefix_count' => $publicPrefixCount,
            'findings' => $findings->values(),
        ];
    }

    /**
     * Prefix that would be traversable at a proposed publication instant.
     * This intentionally supports both already-published and scheduled Articles:
     * at a future editorial date a scheduled Article is eligible only when its
     * own published_at has arrived. Draft/review are never eligible.
     *
     * @param  Collection<int, Article>  $ordered
     * @return Collection<int, Article>
     */
    private function publicPrefixAt(Collection $ordered, Carbon $at): Collection
    {
        $prefix = collect();

        foreach ($ordered as $article) {
            if (! $this->articleAvailableAt($article, $at)) {
                break;
            }

            $prefix->push($article);
        }

        return $prefix->values();
    }

    private function articleAvailableAt(Article $article, Carbon $at): bool
    {
        if (! in_array($article->status, [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED], true)) {
            return false;
        }

        return $article->published_at !== null && $article->published_at->lte($at);
    }

    /** @return array{code:string,severity:string,message:string} */
    private function finding(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }
}
