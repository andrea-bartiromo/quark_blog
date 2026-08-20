<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S6 FASE 4 — Admin\ArticleController::store() derivava lo slug con
 * Str::slug($data['title']) senza alcuna garanzia di unicità, poi passava
 * direttamente ad Article::create() senza try/catch: due articoli con lo
 * stesso titolo (o un doppio invio dello stesso form) collidono sulla
 * colonna UNIQUE articles.slug e la seconda INSERT lancia una
 * QueryException non gestita — un 500 grezzo, non un errore di validazione
 * leggibile dall'editor. Non richiede vera concorrenza per essere
 * riprodotto: basta creare due articoli con lo stesso titolo in sequenza.
 */
class ArticleSlugCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function storePayload(string $title): array
    {
        return [
            'title' => $title,
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ];
    }

    public function test_creating_two_articles_with_the_same_title_does_not_crash_with_an_unhandled_500(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->post(route('admin.articles.store'), $this->storePayload('Un titolo identico'))
            ->assertRedirect(route('admin.articles'));

        $response = $this->actingAs($editor)
            ->post(route('admin.articles.store'), $this->storePayload('Un titolo identico'));

        $response->assertSessionDoesntHaveErrors();
        $this->assertNotSame(500, $response->getStatusCode(), 'La seconda creazione con titolo duplicato ha prodotto un 500 non gestito (violazione UNIQUE su articles.slug).');

        $this->assertSame(2, Article::where('title', 'Un titolo identico')->count());

        $slugs = Article::where('title', 'Un titolo identico')->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count(), 'I due articoli con lo stesso titolo devono avere slug distinti.');
    }
}
