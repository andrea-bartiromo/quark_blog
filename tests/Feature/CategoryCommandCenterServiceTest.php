<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\ContentHealth\ArticleContentHealthService;
use App\Services\EditorialOperations\CategoryCommandCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cantiere 54 (programma "100 cantieri Kairus", dipende dal Cantiere 49).
 *
 * Il servizio è puramente un aggregatore per categoria di audit già
 * esistenti e già testati altrove (CategoryPublicationReadinessTest,
 * il proprio test di ArticleContentHealthService dentro
 * EditorialOperationsDashboardServiceTest) — questi test verificano SOLO
 * l'aggregazione/raggruppamento per categoria, mai una regola di dominio
 * già coperta altrove.
 *
 * Il database di test viene seminato con alcune categorie reali
 * (DatabaseSeeder): ogni fixture qui usa uno slug reso univoco con
 * uniqid() per non collidere con quelle, e ogni asserzione filtra sul
 * proprio $category->id invece di assumere un elenco/ordine fisso.
 */
class CategoryCommandCenterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CategoryCommandCenterService
    {
        return app(CategoryCommandCenterService::class);
    }

    private function author(): User
    {
        return User::factory()->create();
    }

    private function category(string $namePrefix, array $overrides = []): Category
    {
        $slug = Str::slug($namePrefix).'-'.uniqid();

        return Category::create(array_merge([
            'name' => $namePrefix.' '.uniqid(),
            'slug' => $slug,
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function article(string $categorySlug, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $categorySlug,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides));
    }

    public function test_snapshot_includes_every_category(): void
    {
        $salute = $this->category('Salute Test');
        $energia = $this->category('Energia Test');

        $ids = collect($this->service()->snapshot())->pluck('category_id');

        $this->assertTrue($ids->contains($salute->id));
        $this->assertTrue($ids->contains($energia->id));
    }

    public function test_published_article_count_only_counts_this_categorys_published_articles(): void
    {
        $salute = $this->category('Salute Test');
        $energia = $this->category('Energia Test');

        $this->article($salute->slug);
        $this->article($salute->slug);
        $this->article($energia->slug);
        $this->article($salute->slug, ['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $row = collect($this->service()->snapshot())->firstWhere('category_id', $salute->id);

        $this->assertSame(2, $row['published_article_count']);
    }

    public function test_content_health_warning_count_reflects_a_real_missing_cover(): void
    {
        $salute = $this->category('Salute Test');
        // Nessuna cover_image: ArticleContentHealthService::cover() produce
        // una vera WARNING, non simulata.
        $article = $this->article($salute->slug, ['cover_image' => null]);

        // Il numero atteso viene dal servizio reale, non da una cifra fissa:
        // così il test cattura anche un filtro sabotato (es. STATUS_OK al
        // posto di STATUS_WARNING), che altrimenti resterebbe non rilevato
        // finché la collezione risulta comunque non vuota.
        $expectedWarningCount = app(ArticleContentHealthService::class)->evaluate($article)
            ->where('status', ArticleContentHealthService::STATUS_WARNING)
            ->count();

        $this->assertGreaterThan(0, $expectedWarningCount);

        $row = collect($this->service()->snapshot())->firstWhere('category_id', $salute->id);

        $this->assertSame($expectedWarningCount, $row['content_health_warning_count']);
        $this->assertCount(1, $row['articles_with_warnings']);
        $this->assertSame($expectedWarningCount, $row['articles_with_warnings'][0]['warning_count']);
    }

    public function test_content_health_warning_count_is_zero_when_no_published_articles_exist(): void
    {
        $salute = $this->category('Salute Test');

        $row = collect($this->service()->snapshot())->firstWhere('category_id', $salute->id);

        $this->assertSame(0, $row['content_health_warning_count']);
        $this->assertSame([], $row['articles_with_warnings']);
    }

    public function test_readiness_is_computed_via_categorypublicationreadiness_not_reimplemented(): void
    {
        // Categoria bozza: CategoryPublicationReadiness la segnala come non
        // pronta a causa dell'assenza di articoli pubblicati/programmati.
        $salute = $this->category('Salute Test', ['status' => Category::STATUS_DRAFT]);

        $row = collect($this->service()->snapshot())->firstWhere('category_id', $salute->id);

        $this->assertFalse($row['readiness']['ready']);
        $this->assertNotEmpty($row['readiness']['findings']);
    }

    public function test_published_article_count_also_includes_articles_via_secondary_category(): void
    {
        // Codex P2 (PR #636): un articolo con categoria PRINCIPALE diversa ma
        // associato via pivot article_category deve comunque comparire qui,
        // stesso criterio già usato da CategoryDiscoveryPageData::build()
        // (pagina pubblica) e da CategoryPublicationReadiness.
        $salute = $this->category('Salute Test');
        $energia = $this->category('Energia Test');

        $article = $this->article($energia->slug);
        $article->secondaryCategories()->attach($salute->id);

        $row = collect($this->service()->snapshot())->firstWhere('category_id', $salute->id);

        $this->assertSame(1, $row['published_article_count']);
    }

    public function test_query_count_does_not_grow_with_the_number_of_published_articles(): void
    {
        // Codex P2 (PR #636): ArticleContentHealthService::evaluate() richiede
        // la relazione contentClusters per il check "percorso" — se non fosse
        // eager-loaded qui, ogni articolo aggiuntivo farebbe una query in più
        // (N+1), come già verificato per EditorialOperationsDashboardService
        // (EditorialOperationsDashboardServiceTest::test_query_count_does_not_grow_with_article_count).
        $category = $this->category('Query Budget Test');

        // Articoli ricreati da zero a ogni misurazione (stesso pattern di
        // EditorialOperationsDashboardServiceTest::test_query_count_does_not_grow_with_article_count):
        // il numero di categorie resta costante, così l'unica variabile è il
        // numero di articoli pubblicati.
        $countQueriesFor = function (int $articleCount) use ($category): int {
            Article::query()->delete();
            for ($i = 0; $i < $articleCount; $i++) {
                $this->article($category->slug);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->service()->snapshot();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $countQueriesFor(3);
        $large = $countQueriesFor(15);

        $this->assertSame(
            $small,
            $large,
            'Il conteggio query del Command Center non deve dipendere dal numero di articoli pubblicati (nessun N+1).'
        );
    }

    public function test_the_service_never_writes_anything(): void
    {
        $salute = $this->category('Salute Test');
        $this->article($salute->slug);

        $countBefore = Category::count();
        $articlesBefore = Article::count();

        $this->service()->snapshot();

        $this->assertSame($countBefore, Category::count());
        $this->assertSame($articlesBefore, Article::count());
    }
}
