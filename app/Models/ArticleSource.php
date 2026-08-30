<?php

namespace App\Models;

use App\Services\EditorialSources\SourceReferenceNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EDITORIAL TRUST (Missione 26) — una fonte strutturata citata da un
 * articolo.
 *
 * Distinta, e volutamente non sovrapposta, ai due meccanismi preesistenti:
 *   - articles.primary_sources: campo libero del pannello di verifica
 *     editoriale (admin/verification), pensato per il controllo interno
 *     "ho aperto la fonte primaria?", non per il lettore;
 *   - il blocco "Fonti" ricavato dal body dopo il primo '---': markup
 *     legacy, testo non strutturato, senza link.
 * Nessuno dei due viene letto o scritto da qui. Vedi
 * docs/EDITORIAL_SOURCES_V1.md.
 */
class ArticleSource extends Model
{
    /**
     * Tipi di fonte. Il valore è dichiarato SOLO quando la redazione lo
     * conosce davvero: 'unknown' è il default e non produce mai
     * un'etichetta pubblica (Missione 28 — nessuna qualifica inventata).
     *
     * Non c'è 'peer_reviewed': Kairus non esegue peer review e non è in
     * grado di certificare che un singolo articolo linkato l'abbia
     * superata. 'academic' dice solo "pubblicazione accademica", che è
     * verificabile guardando la fonte.
     */
    public const TYPE_UNKNOWN = 'unknown';

    public const TYPE_PRIMARY = 'primary';

    public const TYPE_INSTITUTIONAL = 'institutional';

    public const TYPE_ACADEMIC = 'academic';

    public const TYPE_POPULAR = 'popular';

    /**
     * @var array<string, string> etichette pubbliche. 'unknown' è assente
     *                            di proposito: labelOrNull() restituisce
     *                            null e la UI non stampa nulla.
     */
    public static array $typeLabels = [
        self::TYPE_PRIMARY => 'Fonte primaria',
        self::TYPE_INSTITUTIONAL => 'Fonte istituzionale',
        self::TYPE_ACADEMIC => 'Pubblicazione accademica',
        self::TYPE_POPULAR => 'Fonte divulgativa',
    ];

    /**
     * @var array<string, string> etichette dell'editor admin — include
     *                            'unknown' perché in redazione "non
     *                            specificato" è una scelta esplicita e
     *                            legittima.
     */
    public static array $adminTypeLabels = [
        self::TYPE_UNKNOWN => 'Non specificato',
        self::TYPE_PRIMARY => 'Fonte primaria',
        self::TYPE_INSTITUTIONAL => 'Fonte istituzionale',
        self::TYPE_ACADEMIC => 'Pubblicazione accademica',
        self::TYPE_POPULAR => 'Fonte divulgativa',
    ];

    protected $fillable = [
        'article_id',
        'title',
        'author_or_org',
        'url',
        'doi',
        'source_type',
        'published_on',
        'accessed_on',
        'editorial_note',
        'position',
    ];

    protected $casts = [
        'published_on' => 'date',
        'accessed_on' => 'date',
        'position' => 'integer',
    ];

    /**
     * @return string[]
     */
    public static function types(): array
    {
        return array_keys(self::$adminTypeLabels);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Etichetta pubblica del tipo, o null quando il tipo non è noto —
     * così una Blade non deve mai conoscere il valore magico 'unknown'.
     */
    public function typeLabelOrNull(): ?string
    {
        return self::$typeLabels[$this->source_type] ?? null;
    }

    /**
     * Link pubblico della fonte: il DOI ha la precedenza sull'URL quando
     * entrambi sono presenti, perché è l'identificatore persistente — un
     * URL editoriale può cambiare o sparire, un DOI no.
     *
     * Restituisce null quando non esiste alcun riferimento sicuro da
     * linkare: la fonte viene comunque mostrata, ma come testo. Mai un
     * href costruito su un valore non validato.
     */
    public function publicUrl(): ?string
    {
        $normalizer = app(SourceReferenceNormalizer::class);

        $doi = $normalizer->normalizeDoi($this->doi);

        if ($doi !== null) {
            return $normalizer->doiUrl($doi);
        }

        return $normalizer->isRenderableUrl($this->url) ? $this->url : null;
    }

    /**
     * DOI in forma leggibile per il lettore (`10.1234/abcd`), o null.
     */
    public function normalizedDoi(): ?string
    {
        return app(SourceReferenceNormalizer::class)->normalizeDoi($this->doi);
    }

    /**
     * Host dell'URL, usato come etichetta discreta del link quando non c'è
     * un DOI. Null se l'URL non è linkabile: nessun testo derivato da un
     * valore che abbiamo appena deciso di non linkare.
     */
    public function displayHost(): ?string
    {
        if ($this->normalizedDoi() !== null) {
            return null;
        }

        if (! app(SourceReferenceNormalizer::class)->isRenderableUrl($this->url)) {
            return null;
        }

        $host = parse_url((string) $this->url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        // "www." è rumore per il lettore e non cambia la destinazione.
        return preg_replace('/^www\./i', '', $host);
    }
}
