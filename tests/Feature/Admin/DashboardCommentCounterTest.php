<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCommentCounterTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $user): Article
    {
        return Article::create([
            'user_id' => $user->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Testo di prova.',
            'category' => 'scienza',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Estrae solo la card statistica "Commenti" dalla griglia dei contatori,
     * cosi' l'assert sul valore non dipende dagli altri contatori della
     * pagina (es. "Pubblicati") che potrebbero coincidere per valore.
     */
    private function commentsCardHtml(string $fullHtml): string
    {
        $gridStart = strpos($fullHtml, 'dash-grid-5');
        $this->assertNotFalse($gridStart, 'Griglia statistiche non trovata nella dashboard.');

        $labelPos = strpos($fullHtml, 'Commenti', $gridStart);
        $this->assertNotFalse($labelPos, 'Card "Commenti" non trovata nella dashboard.');

        $cardStart = strrpos(substr($fullHtml, 0, $labelPos), '<a href=');
        $cardEnd = strpos($fullHtml, '</a>', $labelPos);

        return substr($fullHtml, $cardStart, $cardEnd - $cardStart);
    }

    // Il widget "Commenti" deve contare i commenti realmente in attesa
    // (status = pending), non una colonna inesistente sulla tabella.
    public function test_dashboard_shows_the_real_number_of_pending_comments(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        Comment::create(['article_id' => $article->id, 'name' => 'A', 'email' => 'a@a.it', 'body' => 'Uno', 'status' => 'pending']);
        Comment::create(['article_id' => $article->id, 'name' => 'B', 'email' => 'b@b.it', 'body' => 'Due', 'status' => 'pending']);
        Comment::create(['article_id' => $article->id, 'name' => 'C', 'email' => 'c@c.it', 'body' => 'Tre', 'status' => 'approved']);
        Comment::create(['article_id' => $article->id, 'name' => 'D', 'email' => 'd@d.it', 'body' => 'Quattro', 'status' => 'rejected']);

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $response->assertOk();

        $card = $this->commentsCardHtml($response->getContent());
        // 2 commenti pending: non 0 (il bug corretto) e non 4 (il totale).
        $this->assertStringContainsString('>2<', str_replace(["\n", ' '], ['', ''], $card));
    }

    public function test_dashboard_shows_zero_when_there_are_no_pending_comments(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        Comment::create(['article_id' => $article->id, 'name' => 'A', 'email' => 'a@a.it', 'body' => 'Uno', 'status' => 'approved']);

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $response->assertOk();

        $card = $this->commentsCardHtml($response->getContent());
        $this->assertStringContainsString('>0<', str_replace(["\n", ' '], ['', ''], $card));
    }
}
