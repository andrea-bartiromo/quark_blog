<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * EDITORIAL SAFETY — protegge dal caso di un salvataggio esplicito
 * RIUSCITO ma sbagliato (es. un redattore cancella per errore diversi
 * paragrafi e clicca Salva): scenario distinto e complementare
 * all'autosave locale (partials/article-autosave-script.blade.php), che
 * protegge solo il lavoro NON ancora salvato. Vedi
 * docs/article-revision-history.md.
 *
 * Policy dello snapshot: PRE-CHANGE. Ogni riga in article_revisions
 * rappresenta lo stato dell'articolo immediatamente PRIMA di un
 * salvataggio esplicito che lo ha modificato — mai un pareggiamento
 * dell'autosave locale. Questo rende il ripristino banale: la revisione
 * più recente è sempre "l'articolo come appariva prima dell'ultima
 * modifica", esattamente ciò che serve dopo un salvataggio incidentale.
 */
class ArticleRevisionService
{
    /**
     * @var string[] campi editoriali coperti dallo snapshot — scelta di
     *               scope deliberatamente minima (vedi docs/article-revision-history.md
     *               per il perché SEO/copertina restano fuori da questa v1).
     */
    private const SNAPSHOT_FIELDS = ['title', 'excerpt', 'body', 'category', 'status', 'published_at'];

    /**
     * Scrive una revisione con lo stato ATTUALE di $article, ma solo se
     * $incoming differisce davvero su almeno un campo coperto: un
     * salvataggio che non cambia nulla di editorialmente rilevante (click
     * ripetuto su Salva, un solo campo non tracciato qui modificato) non
     * deve accumulare righe identiche indistinguibili l'una dall'altra.
     */
    public function recordIfChanged(Article $article, array $incoming, ?User $actor): void
    {
        $changed = false;

        foreach (self::SNAPSHOT_FIELDS as $field) {
            if (! array_key_exists($field, $incoming)) {
                continue;
            }

            $current = $article->getAttribute($field);
            $next = $incoming[$field];

            if ($field === 'published_at') {
                $current = $current?->format('Y-m-d H:i:s');
                $next = $next instanceof \DateTimeInterface ? $next->format('Y-m-d H:i:s') : $next;
            }

            if ((string) $current !== (string) $next) {
                $changed = true;
                break;
            }
        }

        if (! $changed) {
            return;
        }

        ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => $actor?->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => $article->status,
            'published_at' => $article->published_at,
            'created_at' => now(),
        ]);
    }

    /**
     * Ripristina $article ai valori di $revision. Semantica obbligatoria
     * (non negoziabile): lo stato ATTUALE viene sempre snapshottato prima
     * di applicare quello vecchio (un ripristino non deve mai distruggere
     * in modo irreversibile lo stato da cui si ripristina — è a sua volta
     * recuperabile come una revisione normale), tutto dentro una singola
     * transazione così un fallimento a metà non lascia mai l'articolo in
     * uno stato parzialmente ripristinato.
     */
    public function restore(Article $article, ArticleRevision $revision, ?User $actor): Article
    {
        return DB::transaction(function () use ($article, $revision, $actor) {
            $targetValues = [
                'title' => $revision->title,
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'category' => $revision->category,
                'status' => $revision->status,
                'published_at' => $revision->published_at,
                // read_minutes è derivato dal body, quindi non va versionato:
                // va ricalcolato nello stesso momento in cui il body storico
                // viene ripristinato, esattamente come nei normali salvataggi.
                'read_minutes' => Article::calculateReadMinutes($revision->body),
            ];

            $this->recordIfChanged($article, $targetValues, $actor);

            $article->update($targetValues);

            ActivityLog::record(
                'Versione articolo ripristinata',
                'article',
                $article->id,
                $article->title
            );

            return $article->fresh();
        });
    }
}
