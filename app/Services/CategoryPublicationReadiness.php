<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;

/**
 * Prompt 3/4 (pianificazione categorie) — stesso pattern già in uso per i
 * Percorsi (vedi ContentClusterHealth::evaluate()): un elenco di "findings"
 * testuali, mai bloccante, riusato sia dal pannello di anteprima
 * nell'editor admin (Prompt 3, resources/views/admin/categories-edit.blade.php)
 * sia dal comando Artisan di sola lettura category:publication-readiness
 * (Prompt 4). Nessuna delle due chiamate modifica mai la categoria o gli
 * articoli collegati.
 */
class CategoryPublicationReadiness
{
    /** @return array{findings: list<string>, ready: bool} */
    public function evaluate(Category $category): array
    {
        $findings = collect();

        if (blank($category->description)) {
            $findings->push('MISSING_DESCRIPTION');
        }
        if (blank($category->image)) {
            $findings->push('MISSING_IMAGE');
        }
        if (blank($category->color)) {
            $findings->push('MISSING_COLOR');
        }

        // "Programmata"/"pubblicata" senza alcun articolo pubblicato o
        // programmato non ha ancora nulla da mostrare quando la sua pagina
        // si aprirà: un segnale editoriale, non un errore.
        $hasRelevantArticles = $category->articles()
            ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->exists()
            || $category->secondaryArticles()
                ->whereIn('status', [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
                ->exists();

        if (! $hasRelevantArticles) {
            $findings->push('NO_SCHEDULED_OR_PUBLISHED_ARTICLES');
        }

        $hasRelatedPercorso = ContentCluster::query()
            ->whereHas('articles', function ($query) use ($category) {
                $query->where('articles.category', $category->slug)
                    ->orWhereHas('secondaryCategories', fn ($secondary) => $secondary->where('categories.slug', $category->slug));
            })
            ->exists();

        if (! $hasRelatedPercorso) {
            $findings->push('NO_RELATED_PERCORSO');
        }

        return [
            'findings' => $findings->values()->all(),
            'ready' => $findings->isEmpty(),
        ];
    }

    public static function label(string $finding): string
    {
        return match ($finding) {
            'MISSING_DESCRIPTION' => 'Manca la descrizione',
            'MISSING_IMAGE' => 'Manca l\'immagine',
            'MISSING_COLOR' => 'Manca il colore badge',
            'NO_SCHEDULED_OR_PUBLISHED_ARTICLES' => 'Nessun articolo pubblicato o programmato in questa categoria',
            'NO_RELATED_PERCORSO' => 'Nessun Percorso collegato ad articoli di questa categoria',
            default => $finding,
        };
    }
}
