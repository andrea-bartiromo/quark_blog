<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Article;
use App\Models\Concept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 24/25 — Content Graph Public Consumer: unico consumer pubblico
 * del Content Graph, deliberatamente limitato ai dati strutturati
 * (schema.org `about` sul nodo NewsArticle) — nessun blocco UI visibile
 * ancora (vedi ArticleController::show()). Riusa
 * ContentGraphService::discoverableConceptsForArticle(), lo stesso
 * contratto già certificato da ContentGraphPublicSafetyContractTest: ogni
 * test qui riverifica quel contratto specificamente nel contesto della
 * pagina pubblica articolo.
 */
class ContentGraphPublicStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => 'copertina.jpg',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function newsArticleNodeFor(Article $article): array
    {
        $html = $this->get(route('articolo', $article->slug))->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches, 'Nessun blocco <script type="application/ld+json"> trovato sulla pagina articolo.');

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());

        $node = collect($decoded['@graph'] ?? [])->first(fn ($item) => ($item['@type'] ?? null) === 'NewsArticle');
        $this->assertIsArray($node, 'Nessun nodo @type=NewsArticle trovato nel @graph.');

        return $node;
    }

    public function test_an_active_linked_concept_appears_in_the_about_field(): void
    {
        $article = $this->publishedArticle();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $article->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'primary', 'weight' => 90]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayHasKey('about', $node);
        $this->assertSame([['@type' => 'Thing', 'name' => 'Entropia']], $node['about']);
    }

    public function test_the_about_key_is_entirely_absent_when_no_concept_is_linked(): void
    {
        $article = $this->publishedArticle();

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayNotHasKey('about', $node);
    }

    public function test_a_draft_concepts_link_never_appears_in_about(): void
    {
        $article = $this->publishedArticle();
        $draftConcept = Concept::create(['name' => 'Bozza', 'slug' => 'bozza', 'status' => 'draft']);
        $article->contentConcepts()->create(['concept_id' => $draftConcept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayNotHasKey('about', $node);
    }

    public function test_an_inactive_concepts_link_never_appears_in_about(): void
    {
        $article = $this->publishedArticle();
        $inactiveConcept = Concept::create(['name' => 'Inattivo', 'slug' => 'inattivo', 'status' => 'inactive']);
        $article->contentConcepts()->create(['concept_id' => $inactiveConcept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertArrayNotHasKey('about', $node);
    }

    public function test_a_concepts_aliases_are_never_leaked_into_about_only_the_canonical_name(): void
    {
        $article = $this->publishedArticle();
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $concept->aliases()->create(['alias' => 'Disordine termodinamico']);
        $article->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'supporting', 'weight' => 50]);

        $node = $this->newsArticleNodeFor($article);

        $names = array_column($node['about'], 'name');
        $this->assertSame(['Entropia'], $names);
        $this->assertNotContains('Disordine termodinamico', $names);
    }

    public function test_multiple_active_linked_concepts_all_appear(): void
    {
        $article = $this->publishedArticle();
        $a = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $b = Concept::create(['name' => 'Buco nero', 'slug' => 'buco-nero', 'status' => 'active']);
        $article->contentConcepts()->create(['concept_id' => $a->id, 'relation_type' => 'primary', 'weight' => 90]);
        $article->contentConcepts()->create(['concept_id' => $b->id, 'relation_type' => 'supporting', 'weight' => 40]);

        $node = $this->newsArticleNodeFor($article);

        $this->assertCount(2, $node['about']);
        $this->assertContains('Entropia', array_column($node['about'], 'name'));
        $this->assertContains('Buco nero', array_column($node['about'], 'name'));
    }
}
