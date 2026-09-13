<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;

/**
 * Cantiere 36 (programma 100-cantieri Kairus), dipende dai Cantieri 30-35.
 * Stesso pattern già in uso per CategoryPublicationReadiness (Cantiere 12,
 * app/Services/CategoryPublicationReadiness.php): un elenco di "findings"
 * testuali, mai bloccante — un ASSISTENTE, mai un gate (stesso principio
 * di partials/editorial-quality-gate.blade.php), per l'articolo marcato
 * "in evidenza" (hero homepage, il "primo piano" del titolo di questo
 * cantiere — Article::featured, vedi HomeController::index()).
 *
 * MAI ricalcola le regole di EditorialQualityChecker: prende in ingresso
 * l'EditorialQualityReport già calcolato altrove per lo stesso articolo
 * (stesso principio guida già seguito da PublicHealthBaselineService per
 * PublicHealthDashboardService, Cantiere 35).
 */
class FeaturedArticleCertificationService
{
    public const FINDING_NOT_PUBLISHED = 'NOT_PUBLISHED';

    public const FINDING_QUALITY_INCOMPLETE = 'QUALITY_INCOMPLETE';

    public const FINDING_QUALITY_ATTENTION = 'QUALITY_ATTENTION';

    public const FINDING_MULTIPLE_FEATURED = 'MULTIPLE_FEATURED';

    /**
     * @return array{findings: list<string>, ready: bool}
     */
    public function evaluate(Article $article, EditorialQualityReport $qualityReport): array
    {
        $findings = collect();

        // HomeController::index() legge Article::published()->featured()
        // ->first(): un articolo "in evidenza" ma non ancora pubblicato non
        // apparirà mai come hero finché non lo è — un segnale editoriale,
        // non un errore (stessa distinzione già in
        // CategoryPublicationReadiness::evaluate()).
        if ($article->status !== Article::STATUS_PUBLISHED) {
            $findings->push(self::FINDING_NOT_PUBLISHED);
        }

        if ($qualityReport->level() === EditorialQualityReport::LEVEL_INCOMPLETE) {
            $findings->push(self::FINDING_QUALITY_INCOMPLETE);
        } elseif ($qualityReport->level() === EditorialQualityReport::LEVEL_ATTENTION) {
            $findings->push(self::FINDING_QUALITY_ATTENTION);
        }

        // HomeController::index() non applica alcun ordinamento esplicito
        // a Article::published()->featured()->first(): se più di un
        // articolo pubblicato è marcato "in evidenza", solo uno (per
        // ordine di query, non per scelta editoriale) apparirà come hero
        // — un'ambiguità reale, stesso principio già segnalato per le
        // heading "Fonti" duplicate nel corpo (Cantiere 33).
        if ($this->anotherPublishedFeaturedArticleExists($article)) {
            $findings->push(self::FINDING_MULTIPLE_FEATURED);
        }

        return [
            'findings' => $findings->values()->all(),
            'ready' => $findings->isEmpty(),
        ];
    }

    private function anotherPublishedFeaturedArticleExists(Article $article): bool
    {
        return Article::query()
            ->where('id', '!=', $article->id)
            ->where('featured', true)
            ->where('status', Article::STATUS_PUBLISHED)
            ->exists();
    }

    public static function label(string $finding): string
    {
        return match ($finding) {
            self::FINDING_NOT_PUBLISHED => 'Non ancora pubblicato: non apparirà in homepage finché non lo è',
            self::FINDING_QUALITY_INCOMPLETE => 'Qualità editoriale "Da completare" — almeno un controllo essenziale non superato',
            self::FINDING_QUALITY_ATTENTION => 'Qualità editoriale "Attenzione" — almeno una segnalazione da rivedere',
            self::FINDING_MULTIPLE_FEATURED => 'Un altro articolo pubblicato è già segnato "in evidenza" — solo uno apparirà come hero',
            default => $finding,
        };
    }
}
