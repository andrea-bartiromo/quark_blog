<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticleRelatedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GROWTH S2 — FASE 3/4 (internal linking + "Continua da qui" quality
 * audit): un articolo che appartiene a un Percorso attivo mostra sia
 * articles/partials/path-continuation.blade.php ("Continua il percorso",
 * con link espliciti a precedente/successivo) sia
 * articles/partials/related-articles.blade.php ("Continua a leggere",
 * stessa categoria) nella STESSA pagina, uno subito sotto l'altro. Un
 * Percorso tematicamente coerente (il caso comune: tutte le tappe nella
 * stessa categoria) produceva quindi quasi sempre lo stesso identico
 * articolo mostrato due volte consecutive — trovato durante l'audit,
 * corretto escludendo precedente/successivo del Percorso dalla query dei
 * correlati.
 */
class RelatedArticlesExcludePathNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(User $author, string $title, string $category, ?\DateTimeInterface $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => $publishedAt ?? now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_a_same_category_path_neighbor_is_never_repeated_in_related_articles(): void
    {
        $author = $this->author();

        // Percorso di 3 tappe, tutte nella stessa categoria (il caso
        // comune): la tappa centrale e' quella sotto test.
        $first = $this->article($author, 'Tappa uno', 'energia');
        $middle = $this->article($author, 'Tappa due', 'energia');
        $last = $this->article($author, 'Tappa tre', 'energia');

        // Un quarto articolo, stessa categoria, ma FUORI dal Percorso:
        // deve continuare a comparire normalmente tra i correlati.
        $outsider = $this->article($author, 'Fuori dal percorso', 'energia');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach($first->id, ['position' => 10]);
        $cluster->articles()->attach($middle->id, ['position' => 20]);
        $cluster->articles()->attach($last->id, ['position' => 30]);

        $response = $this->get(route('articolo', $middle->slug));

        $response->assertOk();

        // Il percorso mostra esplicitamente precedente/successivo.
        $response->assertSee('Tappa uno');
        $response->assertSee('Tappa tre');

        // "Continua a leggere" non deve MAI ripetere le stesse due tappe
        // gia' mostrate da "Continua il percorso" appena sopra.
        $relatedIds = $response->viewData('related')->pluck('id')->all();
        $this->assertNotContains($first->id, $relatedIds);
        $this->assertNotContains($last->id, $relatedIds);

        // Ma un articolo della stessa categoria fuori dal Percorso resta
        // un correlato legittimo: l'esclusione non deve "spegnere" del
        // tutto la sezione, solo evitare il duplicato puntuale.
        $this->assertContains($outsider->id, $relatedIds);
    }

    public function test_an_article_outside_any_path_excludes_only_its_continuation_candidate(): void
    {
        $author = $this->author();
        $article = $this->article($author, 'Articolo senza percorso', 'salute', now()->subDays(3));
        $continuation = $this->article($author, 'Prosecuzione', 'salute', now());
        $related = $this->article($author, 'Correlato', 'salute', now()->subDay());

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $relatedIds = $response->viewData('related')->pluck('id')->all();
        $this->assertNotContains($continuation->id, $relatedIds);
        $this->assertContains($related->id, $relatedIds);
    }

    public function test_service_excludes_the_given_ids_directly(): void
    {
        $author = $this->author();
        $source = $this->article($author, 'Sorgente', 'ambiente');
        $toExclude = $this->article($author, 'Da escludere', 'ambiente');
        $toKeep = $this->article($author, 'Da mantenere', 'ambiente');

        $result = app(ArticleRelatedService::class)->forArticle($source, excludeIds: [$toExclude->id]);

        $this->assertFalse($result->contains('id', $toExclude->id));
        $this->assertTrue($result->contains('id', $toKeep->id));
    }
}
