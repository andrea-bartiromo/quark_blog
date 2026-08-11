<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleLinkInsertionService;
use DOMDocument;
use DOMElement;

/**
 * Editorial Quality Gate V1 — controlli deterministici, sola lettura, mai
 * scritti su un articolo (vedi tests/Feature/EditorialQualityGateReadOnlyTest.php).
 *
 * Distingue sempre COMPLETEZZA EDITORIALE (quello che questo servizio
 * verifica) da ACCURATEZZA SCIENTIFICA (quello che NON può mai verificare —
 * vedi docs/EDITORIAL_QUALITY_GATE.md, "Cosa il Quality Gate non può
 * sapere"). Nessun controllo qui giudica se un contenuto è "bello",
 * "corretto" o "pubblicabile": ognuno verifica un fatto puntuale
 * (presenza, lunghezza, coerenza) che una macchina può accertare con
 * certezza.
 *
 * Nessuna AI, nessun embedding, nessuna chiamata esterna: solo dati già
 * caricati sul modello Article passato a check().
 */
class EditorialQualityChecker
{
    private const MIN_TITLE_LENGTH = 3;

    private const MIN_EXCERPT_LENGTH = 20;

    private const MIN_BODY_WORDS = 50;

    /** Un articolo con almeno questo numero di parole nel body è considerato "lungo" ai fini del controllo struttura (sottotitoli attesi). */
    private const LONG_ARTICLE_WORD_THRESHOLD = 600;

    private const SEO_TITLE_MAX_LENGTH = 70;

    private const META_DESCRIPTION_MIN_LENGTH = 50;

    private const META_DESCRIPTION_MAX_LENGTH = 200;

    /**
     * Elenco CHIUSO e conservativo di placeholder — stesso principio già
     * applicato altrove nel codice per gli acronimi corti (allowlist
     * esplicita, non un pattern generale che rischierebbe falsi positivi
     * su testo scientifico legittimo). Confrontato su testo normalizzato
     * (minuscolo, spazi collassati).
     */
    private const PLACEHOLDER_MARKERS = [
        'lorem ipsum', 'todo', 'fixme', 'xxxxxxxx', 'da completare',
        'titolo articolo', '[inserire', 'placeholder',
    ];

    /**
     * Domini istituzionali/scientifici noti, per il segnale INFORMATIVO
     * (mai un requisito) "fonte primaria riconosciuta" — elenco
     * volutamente piccolo (FASE 19 della missione: "non creare una lista
     * gigantesca"). Non influenza mai lo stato del controllo "Fonti"
     * (presente/assente resta l'unico criterio), compare solo nei
     * $details.
     */
    private const KNOWN_INSTITUTIONAL_SOURCE_DOMAINS = [
        'nasa.gov', 'esa.int', 'nature.com', 'science.org', 'nih.gov',
        'who.int', 'arxiv.org', 'cnr.it', 'ingv.it', 'cern.ch',
    ];

    /**
     * Etichette di heading riconosciute come sezione fonti nel CORPO
     * dell'articolo (non nel campo dedicato primary_sources — vedi
     * sourcesCheck()). Elenco chiuso e conservativo, stesso principio di
     * PLACEHOLDER_MARKERS: un confronto esatto (dopo normalizzazione),
     * mai un pattern generale che potrebbe scambiare una heading
     * qualunque per una sezione fonti.
     */
    private const SOURCES_HEADING_LABELS = ['fonti', 'fonte', 'sources', 'riferimenti', 'bibliografia'];

    /** Livelli di heading accettati come apertura di una sezione fonti nel corpo (stesso h2/h3 già usato da structureCheck(), esteso a h4 per sottosezioni annidate). */
    private const SOURCES_HEADING_TAGS = ['h2', 'h3', 'h4'];

    /** Lunghezza minima di un elemento di elenco perché conti come voce bibliografica sostanziale (vedi elementIsSubstantialSource()). */
    private const MIN_SOURCE_LIST_ITEM_LENGTH = 8;

    /** @var array<string, string>|null */
    private ?array $categoryOptionsCache = null;

    public function __construct(
        private readonly ArticleLinkInsertionService $insertionService,
    ) {}

    /**
     * @param  array<string, int>|null  $duplicateTitleIndex  V1 — mappa "titolo normalizzato => quante volte compare nel corpus", precalcolata UNA VOLTA da EditorialQualityAuditService per l'audit su più articoli (evita una query di duplicateTitleCheck() per ciascun articolo — N+1 osservato in self-review, vedi tests/Feature/EditorialQualityAuditPerformanceTest.php). Se null (uso standalone, es. un futuro pannello "Qualità editoriale" sulla pagina di UN SOLO articolo), duplicateTitleCheck() esegue la propria query — comportamento identico, solo il costo cambia in base al contesto di chiamata.
     */
    public function check(Article $article, ?array $duplicateTitleIndex = null): EditorialQualityReport
    {
        $plainBody = $this->plainText((string) $article->body);
        $wordCount = $this->wordCount($plainBody);

        $results = [
            $this->titleCheck($article),
            $this->slugCheck($article),
            $this->excerptCheck($article),
            $this->bodyCheck($article, $plainBody, $wordCount),
            $this->placeholderCheck($article, $plainBody),
            $this->coverCheck($article),
            $this->coverAltCheck($article),
            $this->bodyImagesAltCheck($article),
            $this->seoTitleCheck($article),
            $this->metaDescriptionCheck($article),
            $this->indexabilityCheck($article),
            $this->structureCheck($article, $wordCount),
            $this->sourcesCheck($article),
            $this->internalLinksCheck($article),
            $this->duplicateTitleCheck($article, $duplicateTitleIndex),
            $this->authorCheck($article),
            $this->categoryCheck($article),
            $this->publishingCoherenceCheck($article),
        ];

        return new EditorialQualityReport($article->id, $results);
    }

    // ── CONTENT ──────────────────────────────────────────────────

    private function titleCheck(Article $article): EditorialQualityCheckResult
    {
        $title = trim((string) $article->title);

        if ($title === '') {
            return $this->fail('title_present', 'Titolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Il titolo è vuoto.');
        }

        if (mb_strlen($title, 'UTF-8') < self::MIN_TITLE_LENGTH) {
            return $this->fail('title_present', 'Titolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Il titolo è troppo corto per essere un titolo reale.');
        }

        return $this->pass('title_present', 'Titolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Titolo presente.');
    }

    private function slugCheck(Article $article): EditorialQualityCheckResult
    {
        $slug = trim((string) $article->slug);

        if ($slug === '') {
            return $this->fail('slug_present', 'Slug', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Lo slug è vuoto.');
        }

        // Formato minimo (route pubblica /articolo/{slug}): non impone
        // slug === Str::slug(titolo) — slug storici intenzionalmente
        // diversi dal titolo attuale restano validi (FASE 7 della
        // missione).
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return $this->fail('slug_present', 'Slug', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Lo slug contiene caratteri non validi per la route pubblica.');
        }

        return $this->pass('slug_present', 'Slug', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Slug presente e valido.');
    }

    private function excerptCheck(Article $article): EditorialQualityCheckResult
    {
        $excerpt = trim((string) $article->excerpt);

        if ($excerpt === '') {
            return $this->warning('excerpt_present', 'Sommario', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessun sommario impostato.');
        }

        if (mb_strlen($excerpt, 'UTF-8') < self::MIN_EXCERPT_LENGTH) {
            return $this->warning('excerpt_present', 'Sommario', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Il sommario è molto corto.');
        }

        return $this->pass('excerpt_present', 'Sommario', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Sommario presente.');
    }

    private function bodyCheck(Article $article, string $plainBody, int $wordCount): EditorialQualityCheckResult
    {
        if (trim($plainBody) === '') {
            return $this->fail('body_present', 'Corpo articolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Il corpo dell\'articolo non contiene testo (solo markup vuoto o solo immagini).');
        }

        if ($wordCount < self::MIN_BODY_WORDS) {
            return $this->fail('body_present', 'Corpo articolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, "Il corpo contiene solo {$wordCount} parole — troppo poco per un articolo pubblicabile.");
        }

        return $this->pass('body_present', 'Corpo articolo', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, "Corpo presente ({$wordCount} parole).");
    }

    private function placeholderCheck(Article $article, string $plainBody): EditorialQualityCheckResult
    {
        $haystacks = [
            'titolo' => (string) $article->title,
            'sommario' => (string) $article->excerpt,
            'corpo' => $plainBody,
        ];

        foreach ($haystacks as $field => $text) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 'UTF-8');

            foreach (self::PLACEHOLDER_MARKERS as $marker) {
                if ($normalized !== '' && $this->containsWholeWordMarker($normalized, $marker)) {
                    return $this->fail(
                        'no_placeholder_markers',
                        'Contenuto segnaposto',
                        EditorialQualityCheckResult::CATEGORY_CONTENT,
                        EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL,
                        "Rilevato un possibile segnaposto (\"{$marker}\") nel campo {$field}.",
                        ['field' => $field, 'marker' => $marker]
                    );
                }
            }
        }

        return $this->pass('no_placeholder_markers', 'Contenuto segnaposto', EditorialQualityCheckResult::CATEGORY_CONTENT, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Nessun segnaposto rilevato.');
    }

    /**
     * Verifica se $marker compare in $haystack come unità "delimitata": non
     * incorporato dentro una parola più lunga. Un carattere del testo è
     * considerato di confine (spazio, punteggiatura, tag/entità HTML già
     * spogliati a monte, inizio/fine stringa) oppure "di parola"
     * (lettera/cifra Unicode, vedi isWordChar()). Il marker è considerato
     * delimitato solo se, ai suoi due estremi, non si trova un carattere di
     * parola adiacente sullo stesso lato — così "todo" non scatta dentro
     * "metodo", ma resta rilevato quando è standalone o separato da spazi,
     * punteggiatura o tag HTML. I marker che iniziano o finiscono già con un
     * carattere non di parola (es. "[inserire") non necessitano di
     * considerazioni speciali: la regola è simmetrica e si applica allo
     * stesso modo a entrambi gli estremi.
     */
    private function containsWholeWordMarker(string $haystack, string $marker): bool
    {
        $offset = 0;
        $markerLength = mb_strlen($marker, 'UTF-8');
        $haystackLength = mb_strlen($haystack, 'UTF-8');

        while ($offset <= $haystackLength - $markerLength) {
            $position = mb_strpos($haystack, $marker, $offset, 'UTF-8');

            if ($position === false) {
                return false;
            }

            if ($this->markerMatchesAsWholeUnit($haystack, $marker, $position)) {
                return true;
            }

            $offset = $position + 1;
        }

        return false;
    }

    private function markerMatchesAsWholeUnit(string $haystack, string $marker, int $position): bool
    {
        $markerLength = mb_strlen($marker, 'UTF-8');

        $charBefore = $position > 0 ? mb_substr($haystack, $position - 1, 1, 'UTF-8') : '';
        $firstMarkerChar = mb_substr($marker, 0, 1, 'UTF-8');

        if ($this->isWordChar($firstMarkerChar) && $this->isWordChar($charBefore)) {
            return false;
        }

        $charAfter = mb_substr($haystack, $position + $markerLength, 1, 'UTF-8');
        $lastMarkerChar = mb_substr($marker, -1, 1, 'UTF-8');

        if ($this->isWordChar($lastMarkerChar) && $this->isWordChar($charAfter)) {
            return false;
        }

        return true;
    }

    /**
     * Un carattere "di parola", ai fini del confine di un marker, è una
     * lettera (\p{L}) o un segno diacritico combinante (\p{M}) — MAI una
     * cifra (\p{N}). Le cifre sono deliberatamente escluse:
     *
     * - Le lettere estendono sempre una parola: "todo" preceduto da "me"
     *   (come in "metodo") resta incorporato in una parola legittima.
     * - I segni combinanti sono sempre "attaccati" al carattere base che
     *   li precede nel testo (es. l'accento in "é" scritta in forma
     *   Unicode decomposta NFD, "e" + accento combinante) e non
     *   rappresentano mai un confine — sia che la sequenza sia componibile
     *   in un carattere precomposto (NFC) sia che non lo sia (es. segni
     *   diacritici usati in trascrizioni fonetiche).
     * - Le cifre invece NON devono bloccare il riconoscimento: varianti
     *   numerate di un segnaposto reale ("TODO1", "TODO2", "FIXME42",
     *   "placeholder2") sono una convenzione comune e vanno comunque
     *   rilevate. Nessuna parola italiana legittima ha mai una cifra
     *   incollata direttamente a un marker come "todo"/"fixme"/ecc., quindi
     *   non trattare le cifre come "di parola" qui non introduce falsi
     *   positivi realistici sul lato lettere (quel confine resta gestito
     *   da \p{L}/\p{M}, invariato).
     */
    private function isWordChar(string $char): bool
    {
        return $char !== '' && preg_match('/[\p{L}\p{M}]/u', $char) === 1;
    }

    // ── MEDIA ────────────────────────────────────────────────────

    private function coverCheck(Article $article): EditorialQualityCheckResult
    {
        if (blank($article->cover_image)) {
            return $this->fail('cover_present', 'Cover', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Nessuna immagine di copertina impostata.');
        }

        return $this->pass('cover_present', 'Cover', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Cover presente.');
    }

    private function coverAltCheck(Article $article): EditorialQualityCheckResult
    {
        if (blank($article->cover_image)) {
            return $this->notApplicable('cover_alt_present', 'Alt cover', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessuna cover da descrivere.');
        }

        if (blank($article->cover_alt)) {
            return $this->fail('cover_alt_present', 'Alt cover', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'La cover non ha un testo alternativo.');
        }

        return $this->pass('cover_alt_present', 'Alt cover', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Alt cover presente.');
    }

    private function bodyImagesAltCheck(Article $article): EditorialQualityCheckResult
    {
        $images = $this->parseElements((string) $article->body, 'img');

        if ($images === []) {
            return $this->notApplicable('body_images_alt', 'Alt immagini nel corpo', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessuna immagine nel corpo dell\'articolo.');
        }

        $withoutAlt = 0;

        foreach ($images as $img) {
            if (! $img->hasAttribute('alt') || trim($img->getAttribute('alt')) === '') {
                $withoutAlt++;
            }
        }

        if ($withoutAlt > 0) {
            return $this->warning(
                'body_images_alt',
                'Alt immagini nel corpo',
                EditorialQualityCheckResult::CATEGORY_MEDIA,
                EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED,
                "{$withoutAlt} immagine/i nel corpo senza testo alternativo.",
                ['without_alt' => $withoutAlt, 'total_images' => count($images)]
            );
        }

        return $this->pass('body_images_alt', 'Alt immagini nel corpo', EditorialQualityCheckResult::CATEGORY_MEDIA, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Tutte le immagini del corpo hanno un testo alternativo.');
    }

    // ── SEO ──────────────────────────────────────────────────────

    private function seoTitleCheck(Article $article): EditorialQualityCheckResult
    {
        if ($article->isDraft() || $article->isInReview()) {
            return $this->notApplicable('seo_title', 'Titolo SEO', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Non ancora rilevante prima della pubblicazione.');
        }

        // Sempre un fallback (Article::metaTitle() ricade sul titolo): il
        // valore RENDERIZZATO non può mai essere vuoto, solo troppo lungo.
        $rendered = $article->metaTitle();

        if (mb_strlen($rendered, 'UTF-8') > self::SEO_TITLE_MAX_LENGTH) {
            return $this->warning('seo_title', 'Titolo SEO', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Il titolo SEO effettivo supera i '.self::SEO_TITLE_MAX_LENGTH.' caratteri: potrebbe essere troncato nei risultati di ricerca.');
        }

        return $this->pass('seo_title', 'Titolo SEO', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Titolo SEO entro una lunghezza ragionevole.');
    }

    private function metaDescriptionCheck(Article $article): EditorialQualityCheckResult
    {
        if ($article->isDraft() || $article->isInReview()) {
            return $this->notApplicable('meta_description', 'Meta description', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Non ancora rilevante prima della pubblicazione.');
        }

        $rendered = trim($article->metaDescription());
        $length = mb_strlen($rendered, 'UTF-8');

        if ($rendered === '') {
            return $this->warning('meta_description', 'Meta description', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessuna meta description calcolabile (titolo, sommario e corpo sono tutti vuoti).');
        }

        if ($length < self::META_DESCRIPTION_MIN_LENGTH) {
            return $this->warning('meta_description', 'Meta description', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'La meta description effettiva è molto corta.');
        }

        if ($length > self::META_DESCRIPTION_MAX_LENGTH) {
            return $this->warning('meta_description', 'Meta description', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'La meta description effettiva supera i '.self::META_DESCRIPTION_MAX_LENGTH.' caratteri.');
        }

        return $this->pass('meta_description', 'Meta description', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Meta description entro una lunghezza ragionevole.');
    }

    private function indexabilityCheck(Article $article): EditorialQualityCheckResult
    {
        if (! $article->isPublished()) {
            return $this->notApplicable('indexability', 'Indicizzabilità', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Non ancora pubblico.');
        }

        if (str_contains($article->metaRobots(), 'noindex')) {
            return $this->warning('indexability', 'Indicizzabilità', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Questo articolo pubblicato è impostato su "noindex": non verrà indicizzato dai motori di ricerca. Verifica che sia intenzionale.');
        }

        return $this->pass('indexability', 'Indicizzabilità', EditorialQualityCheckResult::CATEGORY_SEO, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessun blocco di indicizzazione rilevato.');
    }

    // ── STRUCTURE ────────────────────────────────────────────────

    private function structureCheck(Article $article, int $wordCount): EditorialQualityCheckResult
    {
        if ($wordCount < self::LONG_ARTICLE_WORD_THRESHOLD) {
            return $this->notApplicable('structure_headings', 'Struttura (sottotitoli)', EditorialQualityCheckResult::CATEGORY_STRUCTURE, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Articolo breve: la presenza di sottotitoli non è un requisito.');
        }

        $headings = $this->parseElements((string) $article->body, ['h2', 'h3']);

        if ($headings === []) {
            return $this->warning('structure_headings', 'Struttura (sottotitoli)', EditorialQualityCheckResult::CATEGORY_STRUCTURE, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, "Articolo lungo ({$wordCount} parole) senza alcun sottotitolo (H2/H3).");
        }

        $emptyHeadings = count(array_filter($headings, fn (DOMElement $h) => trim($h->textContent) === ''));

        if ($emptyHeadings > 0) {
            return $this->warning('structure_headings', 'Struttura (sottotitoli)', EditorialQualityCheckResult::CATEGORY_STRUCTURE, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, "{$emptyHeadings} sottotitolo/i vuoto/i nel corpo.");
        }

        return $this->pass('structure_headings', 'Struttura (sottotitoli)', EditorialQualityCheckResult::CATEGORY_STRUCTURE, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Sottotitoli presenti in un articolo lungo.');
    }

    // ── SOURCES ──────────────────────────────────────────────────

    /**
     * primary_sources è un campo dedicato del pannello di verifica
     * (resources/views/admin/verification.blade.php), non il modo in cui
     * la maggior parte degli articoli documenta davvero le fonti: nella
     * pratica reale un redattore scrive una sezione "Fonti" strutturata
     * direttamente nel corpo (heading + elenco bibliografico), che questo
     * controllo ignorava completamente — falso negativo osservato su un
     * articolo reale già pubblicato, con una sezione Fonti completa nel
     * body ma primary_sources mai compilato. Il campo dedicato resta il
     * primo segnale controllato (comportamento esistente invariato,
     * incluso il rilevamento del dominio istituzionale); solo se è vuoto
     * si cerca una sezione fonti strutturata nel corpo.
     */
    private function sourcesCheck(Article $article): EditorialQualityCheckResult
    {
        $sources = trim((string) $article->primary_sources);

        if ($sources !== '') {
            $recognizedDomain = null;

            foreach (self::KNOWN_INSTITUTIONAL_SOURCE_DOMAINS as $domain) {
                if (str_contains(mb_strtolower($sources, 'UTF-8'), $domain)) {
                    $recognizedDomain = $domain;
                    break;
                }
            }

            return $this->pass(
                'sources_present',
                'Fonti',
                EditorialQualityCheckResult::CATEGORY_SOURCES,
                EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED,
                $recognizedDomain !== null ? "Fonti presenti (dominio istituzionale riconosciuto: {$recognizedDomain})." : 'Fonti presenti.',
                $recognizedDomain !== null ? ['recognized_domain' => $recognizedDomain] : null
            );
        }

        if ($this->hasStructuredSourcesSectionInBody((string) $article->body)) {
            return $this->pass(
                'sources_present',
                'Fonti',
                EditorialQualityCheckResult::CATEGORY_SOURCES,
                EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED,
                'Fonti presenti nel corpo dell\'articolo (sezione strutturata rilevata).',
                ['detected_in' => 'body_heading']
            );
        }

        if ($this->hasDelimitedSourcesSectionInBody((string) $article->body)) {
            return $this->pass(
                'sources_present',
                'Fonti',
                EditorialQualityCheckResult::CATEGORY_SOURCES,
                EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED,
                'Fonti presenti nel corpo dell\'articolo (sezione dopo "---" rilevata).',
                ['detected_in' => 'body_delimiter']
            );
        }

        return $this->warning('sources_present', 'Fonti', EditorialQualityCheckResult::CATEGORY_SOURCES, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessuna fonte identificabile per questo articolo.');
    }

    /**
     * Cerca, nel corpo dell'articolo, una heading che corrisponde
     * esattamente (dopo normalizzazione) a una delle etichette
     * riconosciute — MAI una ricerca generica della parola "fonti"
     * ovunque nel testo, che scambierebbe una menzione narrativa casuale
     * per una sezione fonti — e verifica che il contenuto SUCCESSIVO, fino
     * alla prossima heading di livello pari o superiore, contenga almeno
     * un elemento sostanziale (vedi elementIsSubstantialSource()). Una
     * heading vuota o seguita solo da una frase generica ("Nessuna fonte
     * disponibile...") non è sufficiente.
     */
    private function hasStructuredSourcesSectionInBody(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $wrapper = $dom->getElementsByTagName('div')->item(0);

        if ($wrapper === null) {
            return false;
        }

        $collecting = false;
        $sectionHeadingLevel = null;

        foreach ($wrapper->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            $headingLevel = preg_match('/^h([1-6])$/', $tag, $m) === 1 ? (int) $m[1] : null;

            if (! $collecting) {
                if ($headingLevel !== null && in_array($tag, self::SOURCES_HEADING_TAGS, true) && $this->isSourcesHeadingLabel($node->textContent)) {
                    $collecting = true;
                    $sectionHeadingLevel = $headingLevel;
                }

                continue;
            }

            if ($headingLevel !== null && $headingLevel <= $sectionHeadingLevel) {
                // Heading di livello pari o superiore: la sezione fonti finisce qui.
                return false;
            }

            if ($this->nodeHasSubstantialSourceContent($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seconda convenzione editoriale reale e documentata, distinta dalla
     * heading strutturata: le linee guida della Redazione istruiscono
     * esplicitamente a "Separare le fonti con --- alla fine del testo"
     * (resources/views/redazione/article-form.blade.php), e il renderer
     * pubblico (resources/views/articolo.blade.php) implementa già questa
     * esatta divisione — explode('---', $body), la parte dopo il primo
     * delimitatore è il testo libero delle fonti, mostrato sotto una
     * propria heading "Fonti" (resources/views/articles/partials/body.blade.php).
     * Trovato in review (Codex): il solo rilevamento basato su heading
     * strutturata lasciava un falso negativo identico per QUALSIASI
     * articolo scritto secondo questa convenzione ufficiale, dato che il
     * testo dopo "---" è testo libero (nl2br), non necessariamente
     * avvolto in una heading HTML riconosciuta.
     *
     * Stessa cautela della sezione a heading: una singola riga di prosa
     * (es. "Nessuna fonte disponibile per questo articolo.") non deve mai
     * bastare da sola — richiede un segnale forte (link/DOI/anno). Righe
     * MULTIPLE non vuote sono già di per sé un segnale strutturale forte
     * (un elenco di fonti scritto una per riga), quindi in quel caso la
     * sola lunghezza minima per riga è sufficiente — stesso principio già
     * applicato agli elementi <li> in elementIsSubstantialSource().
     */
    private function hasDelimitedSourcesSectionInBody(string $body): bool
    {
        $parts = explode('---', $body);

        if (count($parts) < 2) {
            return false;
        }

        $sourcesText = trim($this->plainText($parts[1]));

        if ($sourcesText === '') {
            return false;
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $sourcesText) ?: []),
            fn (string $line) => $line !== ''
        ));

        if ($lines === []) {
            return false;
        }

        if (count($lines) === 1) {
            return $this->textLooksLikeIdentifiableCitation($lines[0]);
        }

        foreach ($lines as $line) {
            if ($this->textLooksLikeIdentifiableCitation($line) || mb_strlen($line, 'UTF-8') >= self::MIN_SOURCE_LIST_ITEM_LENGTH) {
                return true;
            }
        }

        return false;
    }

    /**
     * Confronto esatto, non una sottostringa: "Le fonti utilizzate" o un
     * paragrafo che nomina casualmente "fonti" non aprono una sezione.
     * Tollerante a spazi (incluso NBSP da entità HTML tipo &nbsp;) e a un
     * eventuale ":" finale — DOMDocument decodifica già le altre entità
     * HTML nel textContent, quindi non serve alcuna decodifica aggiuntiva.
     */
    private function isSourcesHeadingLabel(string $text): bool
    {
        $normalized = str_replace("\xc2\xa0", ' ', $text);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = mb_strtolower(trim($normalized, " \t\n\r\0\x0B:："), 'UTF-8');

        return in_array($normalized, self::SOURCES_HEADING_LABELS, true);
    }

    private function nodeHasSubstantialSourceContent(DOMElement $node): bool
    {
        $tag = strtolower($node->tagName);

        if (in_array($tag, ['ul', 'ol'], true)) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'li' && $this->elementIsSubstantialSource($child, isListItem: true)) {
                    return true;
                }
            }

            return false;
        }

        if ($tag === 'li') {
            return $this->elementIsSubstantialSource($node, isListItem: true);
        }

        return $this->elementIsSubstantialSource($node, isListItem: false);
    }

    /**
     * Un singolo elemento (paragrafo, voce di elenco, ecc.) conta come
     * fonte sostanziale se contiene: un link esterno o DOI (segnale forte,
     * indipendente dalla lingua); oppure un anno di pubblicazione tra
     * parentesi (pattern bibliografico comune, anch'esso indipendente
     * dalla lingua). Il solo criterio di lunghezza minima ("nome ente/
     * autore + titolo" senza altri segnali) è accettato SOLO dentro un
     * elemento di elenco: una lista puntata è già di per sé un segnale
     * strutturale forte ("un editore scrive una bibliografia come lista"),
     * mentre un paragrafo isolato può benissimo essere una lunga frase
     * narrativa — anche una negazione ("Nessuna fonte disponibile per
     * questo articolo." è lunga 47 caratteri) — quindi per un paragrafo
     * fuori da un elenco la sola lunghezza non basta mai: serve un link,
     * un DOI o un anno.
     */
    private function elementIsSubstantialSource(DOMElement $element, bool $isListItem): bool
    {
        foreach ($element->getElementsByTagName('a') as $link) {
            if ($this->isIdentifiableSourceLink($link)) {
                return true;
            }
        }

        $text = str_replace("\xc2\xa0", ' ', $element->textContent);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return false;
        }

        if ($this->textLooksLikeIdentifiableCitation($text)) {
            return true;
        }

        return $isListItem && mb_strlen($text, 'UTF-8') >= self::MIN_SOURCE_LIST_ITEM_LENGTH;
    }

    private function isIdentifiableSourceLink(DOMElement $link): bool
    {
        $href = trim($link->getAttribute('href'));

        if ($href === '') {
            return false;
        }

        return preg_match('#^https?://#i', $href) === 1 || str_contains(mb_strtolower($href, 'UTF-8'), 'doi.org');
    }

    /**
     * Segnali di citazione forti e indipendenti dalla lingua, condivisi
     * tra il rilevamento a heading (per un paragrafo/voce di elenco) e
     * quello a delimitatore "---" (per una singola riga di testo libero):
     * un DOI, un anno di pubblicazione tra parentesi, oppure un URL
     * assoluto presente come TESTO semplice (non necessariamente dentro
     * un tag <a> — il testo dopo "---" non è mai HTML, solo testo con
     * nl2br(), quindi un link lì compare sempre così).
     */
    private function textLooksLikeIdentifiableCitation(string $text): bool
    {
        if (preg_match('/10\.\d{4,9}\/\S+/', $text) === 1) {
            return true;
        }

        if (preg_match('/\(\d{4}[a-z]?\)/i', $text) === 1) {
            return true;
        }

        return preg_match('#https?://\S+#i', $text) === 1;
    }

    // ── DISCOVERY ────────────────────────────────────────────────

    private function internalLinksCheck(Article $article): EditorialQualityCheckResult
    {
        if ($article->isDraft()) {
            return $this->notApplicable('internal_links_present', 'Collegamenti interni', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Non ancora rilevante in fase di bozza.');
        }

        $count = $this->insertionService->countArticleLinks((string) $article->body);

        if ($count === 0) {
            return $this->warning('internal_links_present', 'Collegamenti interni', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessun collegamento verso altri contenuti Kairus.');
        }

        return $this->pass('internal_links_present', 'Collegamenti interni', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, "{$count} collegamento/i verso altri articoli Kairus.");
    }

    /**
     * @param  array<string, int>|null  $duplicateTitleIndex  Vedi docblock di check().
     */
    private function duplicateTitleCheck(Article $article, ?array $duplicateTitleIndex): EditorialQualityCheckResult
    {
        $normalizedTitle = mb_strtolower(trim($article->title), 'UTF-8');

        if ($normalizedTitle === '') {
            return $this->notApplicable('duplicate_title', 'Titolo duplicato', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Titolo assente.');
        }

        $duplicateExists = $duplicateTitleIndex !== null
            ? ($duplicateTitleIndex[$normalizedTitle] ?? 0) > 1
            : Article::query()->where('id', '!=', $article->id)->whereRaw('LOWER(TRIM(title)) = ?', [$normalizedTitle])->exists();

        if ($duplicateExists) {
            return $this->warning('duplicate_title', 'Titolo duplicato', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Esiste già un altro articolo con lo stesso titolo.');
        }

        return $this->pass('duplicate_title', 'Titolo duplicato', EditorialQualityCheckResult::CATEGORY_DISCOVERY, EditorialQualityCheckResult::IMPORTANCE_RECOMMENDED, 'Nessun titolo duplicato rilevato.');
    }

    // ── PUBLISHING ───────────────────────────────────────────────

    private function authorCheck(Article $article): EditorialQualityCheckResult
    {
        if ($article->user_id === null || $article->author === null) {
            return $this->fail('author_present', 'Autore', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Nessun autore valido associato all\'articolo.');
        }

        return $this->pass('author_present', 'Autore', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Autore presente.');
    }

    private function categoryCheck(Article $article): EditorialQualityCheckResult
    {
        $category = trim((string) $article->category);

        if ($category === '') {
            return $this->fail('category_valid', 'Categoria', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Nessuna categoria impostata.');
        }

        if (! array_key_exists($category, $this->categoryOptions())) {
            return $this->fail('category_valid', 'Categoria', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, "La categoria \"{$category}\" non corrisponde a nessuna categoria esistente.");
        }

        return $this->pass('category_valid', 'Categoria', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Categoria valida.');
    }

    /**
     * Self-review: Category::options() esegue una query ogni volta che è
     * chiamata — senza questa memoizzazione per-istanza, categoryCheck()
     * ne scatenerebbe una per OGNI articolo analizzato (stesso N+1 già
     * risolto per duplicateTitleCheck() e author). Un'istanza di
     * EditorialQualityChecker vive per la durata di un solo audit/richiesta,
     * mai più a lungo: nessun rischio di servire un elenco categorie
     * ormai scaduto.
     *
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return $this->categoryOptionsCache ??= Category::options(activeOnly: false);
    }

    private function publishingCoherenceCheck(Article $article): EditorialQualityCheckResult
    {
        if ($article->isPublished() && $article->published_at === null) {
            return $this->fail('publishing_coherence', 'Coerenza pubblicazione', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Stato "Pubblicato" senza una data di pubblicazione.');
        }

        if ($article->isScheduleOverdue()) {
            return $this->warning('publishing_coherence', 'Coerenza pubblicazione', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Articolo programmato con data di pubblicazione già passata — lo scheduler non è ancora transitato.');
        }

        return $this->pass('publishing_coherence', 'Coerenza pubblicazione', EditorialQualityCheckResult::CATEGORY_PUBLISHING, EditorialQualityCheckResult::IMPORTANCE_ESSENTIAL, 'Stato e data di pubblicazione coerenti.');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function pass(string $code, string $label, string $category, string $importance, string $message, ?array $details = null): EditorialQualityCheckResult
    {
        return new EditorialQualityCheckResult($code, $label, EditorialQualityCheckResult::STATUS_PASS, $importance, $category, $message, $details);
    }

    private function warning(string $code, string $label, string $category, string $importance, string $message, ?array $details = null): EditorialQualityCheckResult
    {
        return new EditorialQualityCheckResult($code, $label, EditorialQualityCheckResult::STATUS_WARNING, $importance, $category, $message, $details);
    }

    private function fail(string $code, string $label, string $category, string $importance, string $message, ?array $details = null): EditorialQualityCheckResult
    {
        return new EditorialQualityCheckResult($code, $label, EditorialQualityCheckResult::STATUS_FAIL, $importance, $category, $message, $details);
    }

    private function notApplicable(string $code, string $label, string $category, string $importance, string $message): EditorialQualityCheckResult
    {
        return new EditorialQualityCheckResult($code, $label, EditorialQualityCheckResult::STATUS_NOT_APPLICABLE, $importance, $category, $message);
    }

    /**
     * DECISIONE ARCHITETTURALE (dopo due round di review): il corpo
     * dell'articolo NON è HTML a vocabolario chiuso. Il plugin "code" di
     * TinyMCE nell'editor admin (vedi resources/views/admin/article-form
     * .blade.php, toolbar con "code") apre una vista sorgente dove
     * un utente può scrivere qualunque tag HTML valido, non solo quelli
     * raggiungibili dai pulsanti della toolbar. Di conseguenza NESSUNA
     * allowlist di tag (né di "blocco", né "di fraseggio") può mai essere
     * completa con certezza: due round di review hanno già trovato
     * <label> e <dialog> mancanti da una precedente allowlist di soli tag
     * di blocco/replaced, ciascuno un falso NEGATIVO (placeholder reale
     * non rilevato perché fuso con testo adiacente da un tag non
     * previsto) — l'esito peggiore per un controllo la cui missione è non
     * lasciar passare inosservato un vero segnaposto.
     *
     * La scelta finale è quindi asimmetrica e deliberata: il DEFAULT è
     * "inserisci uno spazio" (separatore) per qualunque tag non elencato
     * qui sotto, incluso qualunque tag esotico, sconosciuto o non ancora
     * immaginato (<dialog>, elementi custom, ...) — un tag mancante da
     * questa lista produce nella peggiore delle ipotesi un raro falso
     * POSITIVO (una parola legittima spezzata da un tag non comune,
     * verificabile a vista da chi rivede l'articolo), mai un falso
     * negativo silenzioso. Sono elencati qui SOLO i tag di puro
     * fraseggio/formattazione testuale per cui siamo certi che il
     * contenuto non ha mai un confine visivo proprio nel rendering (es.
     * "<em>me</em>todo" o "me<label>todo</label>" sono sempre "metodo"
     * per un lettore) — un elenco piccolo e conservativo, non un
     * tentativo di enumerare per completezza il contenuto di fraseggio
     * HTML5.
     */
    private const INLINE_MERGE_TAGS = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'code', 'data', 'del', 'dfn',
        'em', 'i', 'ins', 'kbd', 'label', 'mark', 'output', 'q', 'rp', 'rt',
        'ruby', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub',
        'sup', 'time', 'u', 'var', 'wbr',
    ];

    /**
     * Estrazione testo via DOMDocument (stesso approccio del resto della
     * classe, vedi parseElements()) invece che via regex: una regex che
     * combacia i tag con "[^>]*" tronca in anticipo davanti a un ">"
     * dentro un attributo tra virgolette (es. title="A > TODO"),
     * facendo trapelare testo di attributi nel corpo analizzato — un
     * parser HTML vero non ha questo problema, in nessun caso limite.
     *
     * Normalmente il div sintetico resta l'unico nodo di primo livello.
     * Un tag di chiusura non bilanciato nell'HTML salvato può però farlo
     * chiudere in anticipo (stesso bug già noto in TableOfContentsService
     * e ArticleBodyImageService), lasciando il resto del contenuto come
     * fratelli dello stesso wrapper: a differenza di quei due servizi
     * (che rinunciano e restituiscono l'HTML originale, perché operano
     * per il rendering), qui la scelta di "arrendersi" produrrebbe un
     * corpo vuoto/troncato e un falso FAIL su "corpo vuoto" — quindi si
     * raccoglie il testo da ogni nodo di primo livello del documento
     * (uno solo, nel caso normale), senza bisogno di un secondo percorso
     * di estrazione via regex.
     */
    private function plainText(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return '';
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__plain_text_root__">'.$trimmed.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $parts = [];

        foreach ($dom->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $this->insertSeparators($dom, $node);
            $parts[] = $node->textContent;
        }

        $text = implode(' ', $parts);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function insertSeparators(DOMDocument $dom, DOMElement $scope): void
    {
        foreach (iterator_to_array($scope->getElementsByTagName('*')) as $element) {
            if (in_array(mb_strtolower($element->nodeName), self::INLINE_MERGE_TAGS, true)) {
                continue;
            }

            $element->parentNode?->insertBefore($dom->createTextNode(' '), $element);
            $element->parentNode?->insertBefore($dom->createTextNode(' '), $element->nextSibling);
        }
    }

    private function wordCount(string $plainText): int
    {
        $tokens = preg_split('/\s+/u', trim($plainText), -1, PREG_SPLIT_NO_EMPTY);

        return count($tokens);
    }

    /**
     * Parsing DOM-safe (stesso approccio già usato in
     * App\Services\ArticleLinkInsertionService): mai un'eccezione su HTML
     * malformato, mai una modifica al documento.
     *
     * @param  string|array<int, string>  $tagNames
     * @return array<int, DOMElement>
     */
    private function parseElements(string $html, string|array $tagNames): array
    {
        if (trim($html) === '') {
            return [];
        }

        $tagNames = (array) $tagNames;

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $elements = [];

        foreach ($tagNames as $tagName) {
            foreach ($dom->getElementsByTagName($tagName) as $element) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}
