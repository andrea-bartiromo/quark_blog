<?php

namespace App\Services\PublicPages;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use Closure;

/**
 * Cantiere 21 (programma 100-cantieri Kairus). Prima di questo servizio,
 * nessun catalogo unico elencava tutti i tipi di pagina pubblica di
 * Kairus: esistevano solo elenchi parziali e a scopo specifico —
 * `docs/PUBLIC_SURFACES_QA_MATRIX.md` (7 superfici, scope volutamente
 * ristretto a un audit di accessibilità) e
 * `SeoController::staticSitemapPages()` (elenco statico usato solo per
 * generare sitemap.xml, non pensato per essere iterato da altri audit).
 * Nessuno dei due è la fonte di verità che serve ai Cantieri 22-29
 * (audit HTTP/SEO/JSON-LD, 404/redirect, link rotti, media, performance,
 * tastiera, WCAG): tutti hanno bisogno di iterare gli STESSI tipi di
 * pagina con un URL di esempio realmente raggiungibile in QUESTO
 * ambiente, non un elenco duplicato e a rischio di divergere ogni volta.
 *
 * Questo servizio è di sola lettura: non crea, modifica o pubblica mai
 * alcun contenuto. Per le pagine "dinamiche" (che richiedono un record
 * reale nel database, es. un articolo pubblicato) interroga il database
 * con le stesse query/scope già usati altrove per la visibilità pubblica
 * (`Article::scopePublished()`, `Category::publicOptions()` — che copre
 * sia le righe DB pubblicamente visibili sia le categorie legacy
 * esistenti solo in config, entrambe realmente raggiungibili su
 * `/categoria/{slug}` — e `ContentCluster::scopePubliclyVisible()`) —
 * mai una condizione duplicata e potenzialmente divergente. Quando
 * nessun record pubblico
 * esiste ancora (es. un ambiente appena installato), `sample_url` è
 * `null`: non è un errore, è uno stato legittimo che gli audit a valle
 * devono gestire (nessun esempio da verificare, non "verifica fallita").
 *
 * Deliberatamente ESCLUSI da questo inventario (stesso principio di
 * `docs/PUBLIC_SURFACES_QA_MATRIX.md`, che esclude alcune superfici "di
 * proposito"):
 * - le pagine di conferma/cancellazione firmate da token (conferma
 *   iscrizione newsletter, disiscrizione percorsi, ecc.): non sono
 *   raggiungibili senza un token valido legato a un iscritto specifico,
 *   non sono indicizzate né linkate pubblicamente, e non hanno un "URL
 *   di esempio" costruibile senza creare un record fittizio;
 * - gli endpoint XML (sitemap, feed): non sono pagine HTML navigabili,
 *   restano di competenza di `SeoController`;
 * - `/admin/concetti/*`: i Concetti non hanno ancora una pagina pubblica
 *   (solo CRUD in admin), quindi non esiste un tipo "concetto pubblico"
 *   da inventariare oggi.
 */
class PublicPageInventory
{
    /**
     * @var list<array{key: string, label: string, route_name: string}>
     */
    private const STATIC_PAGES = [
        ['key' => 'home', 'label' => 'Home', 'route_name' => 'home'],
        ['key' => 'notizie', 'label' => 'Notizie (indice articoli)', 'route_name' => 'notizie'],
        ['key' => 'ricerca', 'label' => 'Ricerca', 'route_name' => 'ricerca'],
        ['key' => 'percorsi_index', 'label' => 'Percorsi (indice)', 'route_name' => 'percorsi.index'],
        ['key' => 'turing', 'label' => 'Turing (landing)', 'route_name' => 'turing'],
        ['key' => 'redazione', 'label' => 'La redazione', 'route_name' => 'redazione'],
        ['key' => 'chi-siamo', 'label' => 'Chi siamo', 'route_name' => 'chi-siamo'],
        ['key' => 'pubblicita', 'label' => 'Pubblicità', 'route_name' => 'pubblicita'],
        ['key' => 'contatti', 'label' => 'Contatti', 'route_name' => 'contatti'],
        ['key' => 'privacy', 'label' => 'Privacy', 'route_name' => 'privacy'],
        ['key' => 'cookie', 'label' => 'Cookie', 'route_name' => 'cookie'],
        ['key' => 'termini', 'label' => 'Termini', 'route_name' => 'termini'],
        ['key' => 'rettifiche', 'label' => 'Rettifiche', 'route_name' => 'rettifiche'],
        ['key' => 'metodologia', 'label' => 'Metodologia', 'route_name' => 'metodologia'],
    ];

    /**
     * @var list<array{key: string, label: string, route_name: string}>
     */
    private const TURING_CHAPTERS = [
        ['key' => 'turing_enigma', 'label' => 'Turing — Enigma', 'route_name' => 'turing.enigma'],
        ['key' => 'turing_ai', 'label' => 'Turing — AI', 'route_name' => 'turing.ai'],
        ['key' => 'turing_legacy', 'label' => 'Turing — Legacy', 'route_name' => 'turing.legacy'],
        ['key' => 'turing_computation', 'label' => 'Turing — Computation', 'route_name' => 'turing.computation'],
        ['key' => 'turing_intelligence', 'label' => 'Turing — Intelligence', 'route_name' => 'turing.intelligence'],
    ];

    /**
     * @return list<array{key: string, label: string, route_name: string, kind: 'static'|'dynamic', sample_url: string|null}>
     */
    public function pages(): array
    {
        $pages = array_map(fn (array $page) => $this->staticEntry($page), self::STATIC_PAGES);

        if (config('turing.chapters_public')) {
            foreach (self::TURING_CHAPTERS as $chapter) {
                $pages[] = $this->staticEntry($chapter);
            }
        }

        $pages[] = $this->dynamicEntry('categoria', 'Categoria (esempio)', 'categoria', fn () => $this->sampleCategoryUrl());
        $pages[] = $this->dynamicEntry('articolo', 'Articolo (esempio)', 'articolo', fn () => $this->sampleArticleUrl());
        $pages[] = $this->dynamicEntry('autore', 'Autore (esempio)', 'autore', fn () => $this->sampleAuthorUrl());
        $pages[] = $this->dynamicEntry('percorso', 'Percorso (esempio)', 'percorsi.show', fn () => $this->samplePercorsoUrl());

        return $pages;
    }

    /**
     * @param  array{key: string, label: string, route_name: string}  $page
     * @return array{key: string, label: string, route_name: string, kind: 'static', sample_url: string}
     */
    private function staticEntry(array $page): array
    {
        return [
            'key' => $page['key'],
            'label' => $page['label'],
            'route_name' => $page['route_name'],
            'kind' => 'static',
            'sample_url' => route($page['route_name']),
        ];
    }

    /**
     * @return array{key: string, label: string, route_name: string, kind: 'dynamic', sample_url: string|null}
     */
    private function dynamicEntry(string $key, string $label, string $routeName, Closure $resolver): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'route_name' => $routeName,
            'kind' => 'dynamic',
            'sample_url' => $resolver(),
        ];
    }

    private function sampleCategoryUrl(): ?string
    {
        // Finding Codex (P2, PR #569): una categoria legacy presente SOLO
        // in config('laboratorio.categories'), senza alcuna riga nella
        // tabella categories, resta comunque raggiungibile su
        // /categoria/{slug} — ArticleController::category() non fa mai
        // abort(404) quando $categoryModel è null (nessuna riga DB), vedi
        // il suo stesso commento: "Le categorie legacy solo da config...
        // restano raggiungibili come prima". Category::publicOptions() è
        // già l'unica fonte di verità per "quali slug categoria sono
        // davvero pubblici oggi" (righe DB pubblicamente visibili PIÙ gli
        // slug legacy solo-config): riusarla qui, mai una query duplicata
        // che ignorerebbe il fallback legacy e segnalerebbe erroneamente
        // "nessun esempio" quando invece una pagina categoria è
        // genuinamente raggiungibile.
        $slug = array_key_first(Category::publicOptions());

        return $slug !== null ? route('categoria', ['slug' => $slug]) : null;
    }

    private function sampleArticleUrl(): ?string
    {
        $article = Article::query()->published()->first();

        return $article ? route('articolo', ['slug' => $article->slug]) : null;
    }

    private function sampleAuthorUrl(): ?string
    {
        $article = Article::query()->published()->whereNotNull('user_id')->first();

        return $article ? route('autore', ['user' => $article->user_id]) : null;
    }

    private function samplePercorsoUrl(): ?string
    {
        $cluster = ContentCluster::query()->publiclyVisible()->first();

        return $cluster ? route('percorsi.show', ['slug' => $cluster->slug]) : null;
    }
}
