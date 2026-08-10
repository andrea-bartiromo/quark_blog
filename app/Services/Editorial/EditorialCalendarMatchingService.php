<?php

namespace App\Services\Editorial;

use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * Confronta le voci del calendario editoriale con gli articoli reali del
 * CMS per titolo — mai per data, categoria o autore: la missione chiede
 * esplicitamente un match "per titolo", ed è l'unico campo che compare sia
 * nel documento di calendario sia nel record Article in una forma
 * direttamente confrontabile.
 *
 * Normalizzazione deliberatamente prudente (vedi normalizeTitle()): mai
 * collegare automaticamente due articoli solo perché condividono poche
 * parole. Un solo tipo di match "quasi ma non esattamente uguale"
 * (AMBIGUOUS) copre sia i piccoli cambi di titolo evidenti sia i casi
 * davvero ambigui — non è mai sicuro applicarlo in automatico, sempre e
 * solo da proporre a un umano (vedi
 * EditorialCalendarMatch::isSafeToAutoLink()).
 */
class EditorialCalendarMatchingService
{
    /** Titolo identico byte per byte. Sempre sicuro da collegare in automatico se il candidato è unico. */
    public const MATCH_EXACT = 'exact';

    /** Titolo identico dopo normalizzazione prudente (case, spazi, apostrofi, punteggiatura finale). Sicuro da collegare in automatico se il candidato è unico. */
    public const MATCH_NORMALIZED = 'normalized';

    /** Titolo "vicino" ma non identico, o più candidati identici/vicini: mai sicuro da applicare in automatico. */
    public const MATCH_AMBIGUOUS = 'ambiguous';

    /** Nessun articolo abbastanza simile da proporre. */
    public const MATCH_NONE = 'none';

    /**
     * Soglia di similarità (similar_text(), 0-100) sopra la quale un
     * titolo non identico viene comunque proposto come possibile match
     * ambiguo — mai applicato automaticamente. Sotto questa soglia il
     * titolo è considerato "non correlato" (MATCH_NONE): una soglia
     * esplicita, documentata, mai una similarità generica senza limite
     * chiaro (richiesto esplicitamente dalla missione).
     */
    public const SIMILARITY_THRESHOLD = 90.0;

    /**
     * @param  list<EditorialCalendarEntry>  $entries
     * @param  Collection<int, Article>  $articlePool  Tutti gli articoli candidati (tipicamente Article::all(['id','title'])), caricati una sola volta dal chiamante — evita N query ripetute per ogni voce.
     * @param  Collection<int, int>  $linkedArticleIds  ID degli articoli già collegati al progetto.
     * @return list<EditorialCalendarMatch>
     */
    public function matchAll(array $entries, Collection $articlePool, Collection $linkedArticleIds): array
    {
        $matches = array_map(
            fn (EditorialCalendarEntry $entry) => $this->matchEntry($entry, $articlePool, $linkedArticleIds),
            $entries
        );

        return $this->demoteMatchesSharingTheSameArticle($matches);
    }

    /**
     * Se più voci del calendario risolvono (in modo altrimenti sicuro) sullo
     * STESSO articolo — es. due voci con lo stesso titolo pianificato in
     * date diverse — nessuna delle due può essere considerata sicura da
     * collegare in automatico: non è mai chiaro a quale voce l'articolo
     * appartenga davvero, e collegarle entrambe romperebbe comunque il
     * vincolo di unicità (project_id, article_id) sulla seconda scrittura.
     * Richiede sempre una decisione umana, mai una scelta arbitraria tra le
     * voci in conflitto.
     *
     * @param  list<EditorialCalendarMatch>  $matches
     * @return list<EditorialCalendarMatch>
     */
    private function demoteMatchesSharingTheSameArticle(array $matches): array
    {
        $articleIdCounts = [];
        foreach ($matches as $match) {
            if ($match->article !== null) {
                $articleIdCounts[$match->article->id] = ($articleIdCounts[$match->article->id] ?? 0) + 1;
            }
        }

        return array_map(function (EditorialCalendarMatch $match) use ($articleIdCounts) {
            if ($match->article === null || $articleIdCounts[$match->article->id] === 1) {
                return $match;
            }

            return new EditorialCalendarMatch(
                entry: $match->entry,
                matchType: self::MATCH_AMBIGUOUS,
                article: null,
                candidates: [$match->article],
                alreadyLinkedToProject: $match->alreadyLinkedToProject,
            );
        }, $matches);
    }

    /**
     * @param  Collection<int, Article>  $articlePool
     * @param  Collection<int, int>  $linkedArticleIds
     */
    public function matchEntry(EditorialCalendarEntry $entry, Collection $articlePool, Collection $linkedArticleIds): EditorialCalendarMatch
    {
        $exact = $articlePool->filter(fn (Article $a) => $a->title === $entry->title)->values();

        if ($exact->count() === 1) {
            return $this->result($entry, self::MATCH_EXACT, $exact->first(), $exact->all(), $linkedArticleIds);
        }

        if ($exact->count() > 1) {
            return $this->result($entry, self::MATCH_AMBIGUOUS, null, $exact->all(), $linkedArticleIds);
        }

        $normalizedEntryTitle = $this->normalizeTitle($entry->title);

        $normalized = $articlePool->filter(
            fn (Article $a) => $this->normalizeTitle($a->title) === $normalizedEntryTitle
        )->values();

        if ($normalized->count() === 1) {
            return $this->result($entry, self::MATCH_NORMALIZED, $normalized->first(), $normalized->all(), $linkedArticleIds);
        }

        if ($normalized->count() > 1) {
            return $this->result($entry, self::MATCH_AMBIGUOUS, null, $normalized->all(), $linkedArticleIds);
        }

        $near = $articlePool
            ->filter(function (Article $a) use ($normalizedEntryTitle) {
                similar_text($normalizedEntryTitle, $this->normalizeTitle($a->title), $percent);

                return $percent >= self::SIMILARITY_THRESHOLD;
            })
            ->values();

        if ($near->isNotEmpty()) {
            return $this->result($entry, self::MATCH_AMBIGUOUS, null, $near->all(), $linkedArticleIds);
        }

        return $this->result($entry, self::MATCH_NONE, null, [], $linkedArticleIds);
    }

    /**
     * @param  list<Article>  $candidates
     * @param  Collection<int, int>  $linkedArticleIds
     */
    private function result(
        EditorialCalendarEntry $entry,
        string $matchType,
        ?Article $article,
        array $candidates,
        Collection $linkedArticleIds,
    ): EditorialCalendarMatch {
        return new EditorialCalendarMatch(
            entry: $entry,
            matchType: $matchType,
            article: $article,
            candidates: $candidates,
            alreadyLinkedToProject: $article !== null && $linkedArticleIds->contains($article->id),
        );
    }

    /**
     * Normalizzazione prudente e deterministica, mai una similarità
     * generica: minuscolo, apostrofi/virgolette tipografiche riportati
     * alla forma dritta, spazi multipli collassati, punteggiatura finale
     * (?!.,;: e simili) rimossa perché varia spesso tra come un titolo è
     * scritto nel calendario e come è stato scritto davvero nel CMS senza
     * cambiarne il significato.
     */
    public function normalizeTitle(string $title): string
    {
        $title = trim($title);
        $title = str_replace(['’', '‘', '´', '`'], "'", $title);
        $title = str_replace(['“', '”', '"'], '', $title);
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = rtrim($title, " \t\n\r\0\x0B?!.,;:");

        return trim($title);
    }
}
