<?php

namespace App\Services\InternalLinking;

use App\Models\Article;

/**
 * Internal Linking V2.1 — unica definizione di "un target è temporalmente
 * sicuro come collegamento interno da una data sorgente", condivisa da
 * App\Services\ArticleLinkSuggestionService (motore dei suggerimenti) e
 * App\Services\InternalLinking\InternalLinkAuditService (audit dei link già
 * presenti). Nessun altro punto del codice deve confrontare status/
 * published_at "a mano" per questa decisione.
 *
 * Pura e senza side effect: entrambi i metodi leggono solo gli attributi già
 * caricati sui due Article passati, nessuna query, nessuna scrittura.
 *
 * Principio guida (non "permettere link verso articoli non pubblicati", ma):
 * permettere, durante la preparazione di un articolo futuro (scheduled),
 * collegamenti verso contenuti che saranno deterministicamente già pubblici
 * quando quell'articolo diventerà pubblico.
 *
 * Regola:
 *   A) $target è già pubblicamente visibile ORA → sempre eleggibile,
 *      indipendentemente dallo stato di $source (comportamento V2
 *      preesistente, invariato).
 *   B) altrimenti, eleggibile SOLO SE $source è essa stessa 'scheduled' (con
 *      published_at valorizzato) E $target è 'scheduled' (con published_at
 *      valorizzato) E target.published_at è STRETTAMENTE precedente a
 *      source.published_at — mai <=, per non dipendere dall'ordine di
 *      esecuzione dello scheduler quando i due istanti coincidono (vedi
 *      App\Console\Commands\PublishScheduledArticles, che processa gli
 *      articoli scheduled dovuti in ordine di published_at crescente: due
 *      istanti identici non garantiscono un ordine relativo deterministico
 *      tra loro).
 *
 * Qualunque altra combinazione (source draft/review/published verso un
 * target non pubblico, target draft/review, target scheduled senza
 * published_at) NON è mai eleggibile — self-link resta escluso a monte dai
 * due chiamanti (identità, non una regola temporale), non duplicato qui.
 */
class InternalLinkTemporalEligibility
{
    /**
     * Stessa definizione di "pubblicamente visibile" di
     * Article::scopePublished() (status published E published_at non nel
     * futuro), applicabile qui su modelli già in memoria senza una query
     * aggiuntiva — spostata qui da InternalLinkAuditService (era una sua
     * copia privata) perché ora serve anche a isTargetSafeForSource().
     */
    public function isPubliclyVisible(Article $article): bool
    {
        return $article->status === Article::STATUS_PUBLISHED
            && $article->published_at !== null
            && ! $article->published_at->isFuture();
    }

    public function isTargetSafeForSource(Article $source, Article $target): bool
    {
        if ($this->isPubliclyVisible($target)) {
            return true;
        }

        if (! $source->isScheduled() || $source->published_at === null) {
            return false;
        }

        if (! $target->isScheduled() || $target->published_at === null) {
            return false;
        }

        return $target->published_at->lt($source->published_at);
    }
}
