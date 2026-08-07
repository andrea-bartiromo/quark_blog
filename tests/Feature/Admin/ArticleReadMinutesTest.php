<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleReadMinutesTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    // 1. Calcolato automaticamente alla creazione (Admin), senza alcun input manuale
    public function test_admin_store_computes_read_minutes_automatically(): void
    {
        $body = str_repeat('parola ', 600); // 600/200 = 3 min

        $this->actingAs($this->editor())->post(route('admin.articles.store'), [
            'title' => 'Articolo con testo lungo',
            'body' => $body,
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $article = Article::where('title', 'Articolo con testo lungo')->firstOrFail();
        $this->assertSame(3, $article->read_minutes);
    }

    // 2. Un valore manuale inviato nella richiesta viene ignorato: il campo non è più
    //    esposto nel form (FASE 2A), il server resta l'unica fonte di verità
    public function test_admin_store_ignores_a_manually_submitted_read_minutes_value(): void
    {
        $body = str_repeat('parola ', 600); // 3 min secondo la formula

        $this->actingAs($this->editor())->post(route('admin.articles.store'), [
            'title' => 'Articolo con minuti manuali',
            'body' => $body,
            'category' => 'energia',
            'status' => 'draft',
            'read_minutes' => 45,
        ]);

        $article = Article::where('title', 'Articolo con minuti manuali')->firstOrFail();
        $this->assertSame(3, $article->read_minutes);
    }

    // 3. Ricalcolato automaticamente quando il corpo cambia in aggiornamento
    public function test_admin_update_recomputes_read_minutes_when_body_changes(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo da aggiornare',
            'slug' => 'articolo-da-aggiornare',
            'body' => str_repeat('parola ', 200), // 1 min
            'category' => 'energia',
            'status' => 'draft',
            'read_minutes' => 1,
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'body' => str_repeat('parola ', 1000), // 5 min
            'category' => $article->category,
            'status' => 'draft',
        ]);

        $this->assertSame(5, $article->fresh()->read_minutes);
    }

    // 4. Coerenza tra le due aree: stesso corpo -> stesso risultato (prima della
    //    centralizzazione, Admin e Redazione usavano formule diverse: 200 vs 180
    //    parole/minuto, round vs ceil)
    public function test_redazione_and_admin_compute_the_same_read_minutes_for_the_same_body(): void
    {
        $body = str_repeat('parola ', 550); // valore scelto apposta: round(550/200)=3, ceil(550/180)=4 con la vecchia formula Redazione

        $this->actingAs($this->editor())->post(route('admin.articles.store'), [
            'title' => 'Articolo Admin',
            'body' => $body,
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $this->actingAs($this->author())->post(route('redazione.articles.store'), [
            'title' => 'Articolo Redazione',
            'body' => $body,
            'category' => 'energia',
        ]);

        $adminMinutes = Article::where('title', 'Articolo Admin')->value('read_minutes');
        $redazioneMinutes = Article::where('title', 'Articolo Redazione')->value('read_minutes');

        $this->assertSame($adminMinutes, $redazioneMinutes);
        $this->assertSame(3, $adminMinutes);
    }

    // 5. Il campo minuti-di-lettura editabile non è più presente nel form (era
    //    fuorviante: il valore inviato veniva comunque sempre ignorato)
    public function test_admin_form_no_longer_exposes_an_editable_read_minutes_field(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertDontSee('name="read_minutes"', false);
        $response->assertSee('Calcolato automaticamente', false);
    }

    // 6. Regressione (revisione CodeRabbit): empty('0') è true in PHP — un
    //    corpo che è letteralmente la stringa "0" (valido: passa la regola
    //    'required') non deve far saltare silenziosamente il calcolo
    public function test_admin_store_computes_read_minutes_even_when_body_is_the_string_zero(): void
    {
        $this->actingAs($this->editor())->post(route('admin.articles.store'), [
            'title' => 'Articolo con corpo zero',
            'body' => '0',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $article = Article::where('title', 'Articolo con corpo zero')->firstOrFail();
        $this->assertSame(1, $article->read_minutes);
    }
}
