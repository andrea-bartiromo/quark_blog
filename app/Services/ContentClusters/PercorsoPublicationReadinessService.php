<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterHealth;
use Illuminate\Support\Collection;

class PercorsoPublicationReadinessService
{
    public function __construct(private readonly ContentClusterHealth $health) {}

    /**
     * Warning-first, read-only readiness. It never changes or blocks a Percorso.
     *
     * @return array{status:string,findings:Collection<int,array{code:string,severity:string,message:string}>}
     */
    public function evaluate(ContentCluster $cluster, ?\DateTimeInterface $publicationAt = null): array
    {
        $cluster->loadMissing(['articles', 'pillarArticle']);
        $health = $this->health->evaluate($cluster);
        $findings = collect();

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
            $severity = in_array($code, ['EMPTY', 'PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES', 'ORDERING_ISSUE'], true) ? 'ERROR' : 'WARNING';
            $findings->push($this->finding('HEALTH_'.$code, $severity, 'ContentClusterHealth segnala: '.$code.'.'));
        }

        if ($cluster->articles->count() === 1) {
            $findings->push($this->finding('SINGLE_MEMBER', 'WARNING', 'Il Percorso contiene un solo articolo: valuta se esiste una progressione editoriale sufficiente.'));
        }

        $ordered = $cluster->articles->sortBy(fn (Article $article) => [$article->pivot?->position ?? PHP_INT_MAX, $article->id])->values();
        $missingTransitions = $ordered->skip(1)->filter(fn (Article $article) => blank($article->pivot?->transition_text))->count();
        if ($missingTransitions > 0) {
            $findings->push($this->finding('TRANSITION_TEXT_GAPS', 'WARNING', $missingTransitions.' tappa/e successive alla prima non hanno testo di raccordo.'));
        }

        if ($publicationAt !== null) {
            $at = \Illuminate\Support\Carbon::instance(\DateTime::createFromInterface($publicationAt));
            $unavailable = $cluster->articles->filter(function (Article $article) use ($at): bool {
                if ($article->status === Article::STATUS_PUBLISHED) {
                    return $article->published_at === null || $article->published_at->gt($at);
                }

                return $article->status !== Article::STATUS_SCHEDULED || $article->published_at === null || $article->published_at->gt($at);
            });

            if ($unavailable->isNotEmpty()) {
                $findings->push($this->finding('MEMBERS_UNAVAILABLE_AT_PUBLICATION', 'WARNING', $unavailable->count().' membro/i non risulterebbero disponibili alla data prevista del Percorso.'));
            }

            if ($cluster->pillarArticle !== null && ($cluster->pillarArticle->published_at === null || $cluster->pillarArticle->published_at->gt($at))) {
                $findings->push($this->finding('PILLAR_UNAVAILABLE_AT_PUBLICATION', 'ERROR', 'Il pillar non sarebbe disponibile alla data prevista del Percorso.'));
            }
        } else {
            $findings->push($this->finding('SCHEDULING_NOT_AVAILABLE', 'INFO', 'Percorsi Scheduling non è ancora su main: la readiness temporale è calcolabile solo se viene fornita esplicitamente una data prevista.'));
        }

        return [
            'status' => match (true) {
                $findings->contains('severity', 'ERROR') => 'ERROR',
                $findings->contains('severity', 'WARNING') => 'WARNING',
                default => 'READY',
            },
            'findings' => $findings->values(),
        ];
    }

    /** @return array{code:string,severity:string,message:string} */
    private function finding(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }
}
