<?php

namespace App\Services\EditorialSources;

use App\Models\Article;
use App\Models\ArticleSource;
use Illuminate\Support\Facades\DB;

/**
 * EDITORIAL TRUST (Missione 27) — riconciliazione dell'elenco fonti di un
 * articolo a partire dal payload dell'editor.
 *
 * Semantica: il payload è l'elenco COMPLETO e ordinato delle fonti
 * dell'articolo. Una riga con `id` esistente viene aggiornata, una senza
 * `id` viene creata, e una riga persistita che il payload non contiene più
 * viene eliminata (l'utente l'ha rimossa dall'editor).
 *
 * Tutto in una sola transazione: un fallimento a metà non deve mai
 * lasciare l'articolo con metà elenco vecchio e metà nuovo, né con due
 * fonti sulla stessa posizione.
 */
class ArticleSourceService
{
    public function __construct(
        private readonly SourceReferenceNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  righe già validate
     * @return int numero di fonti dell'articolo dopo il salvataggio
     */
    public function sync(Article $article, array $rows): int
    {
        return DB::transaction(function () use ($article, $rows) {
            // Gli id esistenti vengono letti dal DB e non dal payload: un
            // id appartenente a un ALTRO articolo (payload manomesso) non
            // deve poter essere aggiornato o cancellato da qui.
            $existing = ArticleSource::where('article_id', $article->id)
                ->get()
                ->keyBy('id');

            $keptIds = [];
            $position = 0;

            foreach ($rows as $row) {
                $attributes = $this->attributesFrom($row, $position);

                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $model = $id !== null ? $existing->get($id) : null;

                if ($model instanceof ArticleSource) {
                    $model->fill($attributes)->save();
                    $keptIds[] = $model->id;
                } else {
                    // Anche quando il payload portava un id, se quell'id
                    // non appartiene a questo articolo la riga viene
                    // CREATA come nuova invece di essere rifiutata: il
                    // contenuto scritto dal redattore non va perso per una
                    // discrepanza di identificatore.
                    $created = ArticleSource::create(
                        $attributes + ['article_id' => $article->id]
                    );
                    $keptIds[] = $created->id;
                }

                $position++;
            }

            $removable = $existing->keys()->diff($keptIds);

            if ($removable->isNotEmpty()) {
                ArticleSource::where('article_id', $article->id)
                    ->whereIn('id', $removable->all())
                    ->delete();
            }

            return count($keptIds);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFrom(array $row, int $position): array
    {
        $rawUrl = trim((string) ($row['url'] ?? ''));
        $rawDoi = trim((string) ($row['doi'] ?? ''));

        return [
            'title' => trim((string) ($row['title'] ?? '')),
            'author_or_org' => $this->nullableTrim($row['author_or_org'] ?? null),
            // Normalizzati in scrittura, mai al rendering: il valore in
            // tabella è già quello sicuro, così una futura vista/export che
            // dimenticasse di ri-normalizzare non può pubblicare un href
            // non validato.
            'url' => $rawUrl === '' ? null : $this->normalizer->normalizeUrl($rawUrl),
            'doi' => $rawDoi === '' ? null : $this->normalizer->normalizeDoi($rawDoi),
            'source_type' => $this->sourceType($row['source_type'] ?? null),
            'published_on' => $this->nullableTrim($row['published_on'] ?? null),
            'accessed_on' => $this->nullableTrim($row['accessed_on'] ?? null),
            'editorial_note' => $this->nullableTrim($row['editorial_note'] ?? null),
            // La posizione è l'ordine di arrivo delle righe nel payload,
            // non un valore inviato dal client: l'ordine visibile
            // nell'editor È l'ordine salvato, senza buchi né collisioni.
            'position' => $position,
        ];
    }

    private function sourceType(mixed $value): string
    {
        $value = (string) $value;

        return in_array($value, ArticleSource::types(), true)
            ? $value
            : ArticleSource::TYPE_UNKNOWN;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Chiavi duplicate presenti nell'elenco (Missione 27): stesso DOI, o
     * stesso URL a meno di differenze che non cambiano la destinazione.
     *
     * Restituisce un WARNING, non un errore: citare due volte lo stesso
     * studio può essere legittimo (due passaggi diversi dell'articolo), e
     * bloccare il salvataggio farebbe perdere il lavoro appena scritto.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string> titoli delle fonti coinvolte
     */
    public function duplicateWarnings(array $rows): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $this->normalizer->duplicateKey(
                $row['url'] ?? null,
                $row['doi'] ?? null
            );

            if ($key === null) {
                continue;
            }

            if (isset($seen[$key])) {
                $duplicates[$key] = trim((string) ($row['title'] ?? ''));

                continue;
            }

            $seen[$key] = true;
        }

        return array_values(array_filter($duplicates));
    }
}
