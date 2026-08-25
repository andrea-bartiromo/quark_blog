<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 24 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Percorsi structured-data consistency."
 *
 * ContentClusterController::show() già costruisce mainEntity.itemListElement
 * a partire dallo STESSO $articles (ContentClusterPublicSequence::resolve())
 * usato per il markup visibile — mai una seconda fonte di verità. Ma nessun
 * test esistente decodifica davvero il blocco JSON-LD di un Percorso: la
 * regressione già presente (test_detail_stops_at_first_non_public_member_and_hides_non_public_pillar,
 * in ContentClusterPublicTest) verifica solo l'assenza dal <main> visibile —
 * il tag <script type="application/ld+json"> vive nella sezione 'meta',
 * PRIMA di <main>, quindi resta fuori da quel controllo. Un crawler legge
 * il JSON-LD indipendentemente da cosa un umano vede renderizzato: se
 * quella seconda fonte drifta, un motore di ricerca continuerebbe a vedere
 * una tappa nascosta anche dopo che la pagina visibile smette di mostrarla.
 * Questa missione prova esplicitamente che non è mai successo, decodificando
 * il JSON-LD invece di limitarsi al markup.
 */
class ContentClusterStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['role' => 'editor']);
    }

    private function article(string $title, string $status, $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid('', true),
            'excerpt' => 'Sommario editoriale sufficientemente completo per il test.',
            'body' => '<p>Corpo articolo di test con contenuto editoriale sufficiente.</p>',
            'category' => 'spazio',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDataFrom(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            'Nessun blocco <script type="application/ld+json"> trovato.'
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());

        return $decoded;
    }

    public function test_structured_data_item_list_never_leaks_a_gap_blocked_scheduled_or_draft_title(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Prima tappa pubblica', Article::STATUS_PUBLISHED, now()->subDays(2));
        $scheduled = $this->article('Tappa programmata segreta', Article::STATUS_SCHEDULED, now()->addDay());
        $publishedBehindGap = $this->article('Pubblicata ma dietro il gap', Article::STATUS_PUBLISHED, now()->subDay());
        $draft = $this->article('Bozza segreta', Article::STATUS_DRAFT);
        $cluster->articles()->attach([
            $first->id => ['position' => 10],
            $scheduled->id => ['position' => 20],
            $publishedBehindGap->id => ['position' => 30],
            $draft->id => ['position' => 40],
        ]);

        $html = $this->get(route('percorsi.show', $cluster->slug))->assertOk()->getContent();
        $structuredData = $this->structuredDataFrom($html);

        $itemTitles = collect($structuredData['mainEntity']['itemListElement'])->pluck('name')->all();

        $this->assertSame(['Prima tappa pubblica'], $itemTitles);
        $this->assertSame(1, $structuredData['mainEntity']['numberOfItems']);
    }

    public function test_structured_data_item_list_matches_the_full_public_prefix_in_order(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $first = $this->article('Capitolo uno', Article::STATUS_PUBLISHED, now()->subDays(3));
        $second = $this->article('Capitolo due', Article::STATUS_PUBLISHED, now()->subDays(2));
        $third = $this->article('Capitolo tre', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster->articles()->attach([
            $first->id => ['position' => 10],
            $second->id => ['position' => 20],
            $third->id => ['position' => 30],
        ]);

        $html = $this->get(route('percorsi.show', $cluster->slug))->assertOk()->getContent();
        $structuredData = $this->structuredDataFrom($html);

        $items = $structuredData['mainEntity']['itemListElement'];

        $this->assertSame(3, $structuredData['mainEntity']['numberOfItems']);
        $this->assertSame(['Capitolo uno', 'Capitolo due', 'Capitolo tre'], collect($items)->pluck('name')->all());
        $this->assertSame([1, 2, 3], collect($items)->pluck('position')->all());
        $this->assertSame(route('articolo', $first->slug), $items[0]['url']);
    }

    public function test_structured_data_is_valid_and_empty_for_a_percorso_with_no_published_articles(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach(
            $this->article('Bozza unica', Article::STATUS_DRAFT)->id,
            ['position' => 10]
        );

        $html = $this->get(route('percorsi.show', $cluster->slug))->assertOk()->getContent();
        $structuredData = $this->structuredDataFrom($html);

        $this->assertSame(0, $structuredData['mainEntity']['numberOfItems']);
        $this->assertSame([], $structuredData['mainEntity']['itemListElement']);
    }

    public function test_structured_data_breadcrumb_references_the_real_canonical_url(): void
    {
        $cluster = ContentCluster::factory()->create(['is_active' => true, 'name' => 'Percorso Breadcrumb']);
        $cluster->articles()->attach(
            $this->article('Tappa breadcrumb', Article::STATUS_PUBLISHED, now()->subDay())->id,
            ['position' => 10]
        );

        $html = $this->get(route('percorsi.show', $cluster->slug))->assertOk()->getContent();
        $structuredData = $this->structuredDataFrom($html);

        $canonical = route('percorsi.show', $cluster->slug);
        $this->assertSame($canonical, $structuredData['url']);
        $breadcrumbItems = $structuredData['breadcrumb']['itemListElement'];
        $this->assertSame($canonical, end($breadcrumbItems)['item']);
        $this->assertSame('Percorso Breadcrumb', end($breadcrumbItems)['name']);
    }

    /**
     * Stesso pattern già usato per le pagine archivio
     * (CollectionPageStructuredDataTest::test_json_ld_cannot_be_broken_out_of_by_a_category_name_containing_a_closing_script_tag),
     * mai prima applicato a un Percorso: il nome è un campo editoriale
     * libero, non una whitelist di valori — deve restare sicuro anche se
     * contiene una sequenza che chiuderebbe prematuramente il tag
     * <script>.
     */
    public function test_json_ld_cannot_be_broken_out_of_by_a_percorso_name_containing_a_closing_script_tag(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'name' => 'Percorso </script><script>alert(1)</script>',
        ]);
        $cluster->articles()->attach(
            $this->article('Tappa sicura', Article::STATUS_PUBLISHED, now()->subDay())->id,
            ['position' => 10]
        );

        $html = $this->get(route('percorsi.show', $cluster->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
        // La struttura resta comunque JSON-LD valido e decodificabile.
        $this->structuredDataFrom($html);
    }
}
