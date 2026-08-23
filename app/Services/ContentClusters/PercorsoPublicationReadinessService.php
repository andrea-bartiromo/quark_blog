<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterHealth;
use Illuminate\Support\Carbon;
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
            // PILLAR_NOT_PUBLIC and NO_PUBLIC_ARTICLES are facts about "now".
            // When a future publication instant is explicitly supplied they are
            // replaced by the temporal checks below, otherwise a valid scheduled
            // pillar/member would be incorrectly rejected merely for not being
            // public yet.
            if ($publicationAt !== null && in_array($code, ['PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES'], true)) {
                continue;
            }

            $severity = in_array($code, ['EMPTY', 'NO_PILLAR', 'PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES', 'ORDERING_ISSUE'], true)
                ? 'ERROR'
                : 'WARNING';
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
            $at = Carbon::instance(\DateTime::createFromInterface($publicationAt));
            $available = $cluster->articles->filter(fn (Article $article) => $this->articleAvailableAt($article, $at));
            $unavailable = $cluster->articles->reject(fn (Article $article) => $this->articleAvailableAt($article, $at));

            if ($available->isEmpty()) {
                $findings->push($this->finding('NO_MEMBERS_AVAILABLE_AT_PUBLICATION', 'ERROR', 'Nessun membro risulterebbe pubblico alla data prevista del Percorso.'));
            } elseif ($unavailable->isNotEmpty()) {
                $findings->push($this->finding('MEMBERS_UNAVAILABLE_AT_PUBLICATION', 'WARNING', $unavailable->count().' membro/i diventerebbero disponibili solo dopo la data prevista del Percorso o non sono programmati per la pubblicazione.'));
            }

            if ($cluster->pillarArticle !== null && ! $this->articleAvailableAt($cluster->pillarArticle, $at)) {
                $findings->push($this->finding('PILLAR_UNAVAILABLE_AT_PUBLICATION', 'ERROR', 'Il pillar non sarebbe pubblico alla data prevista del Percorso.'));
            }
        } else {
            $findings->push($this->finding('SCHEDULING_NOT_AVAILABLE', 'INFO', 'Percorsi Scheduling non è ancora su main: la readiness temporale è calcolabile solo se viene fornita esplicitamente una data prevista.'));
        }

        return [
            'status' => match (true) {
                $findings->contains('severity', 'ERROR') => 'NOT READY',
                $findings->contains('severity', 'WARNING') => 'READY WITH WARNINGS',
                default => 'READY',
            },
            'findings' => $findings->values(),
        ];
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
