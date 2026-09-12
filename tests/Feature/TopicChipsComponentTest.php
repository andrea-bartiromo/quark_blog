<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 2 (programma 100-cantieri Kairus): chip "Argomenti" estratti in
 * x-topic-chips, condiviso da notizie.blade.php e categoria.blade.php.
 * Il Cantiere 1 copriva già il caso categoria.blade.php dentro
 * CategoryDiscoveryFlowTest; qui si aggiunge il caso notizie.blade.php
 * (dove "Tutti" è la voce corrente) e si verifica che l'estrazione non
 * abbia alterato il comportamento pubblico di entrambe le pagine.
 */
class TopicChipsComponentTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(string $category): Article
    {
        return Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 3,
        ]);
    }

    public function test_notizie_page_marks_tutti_as_the_current_chip(): void
    {
        $this->publishedArticle('energia');

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertSee('<nav class="public-pill-row" aria-label="Filtra per argomento">', false);
        $response->assertSee('href="'.route('notizie').'" class="active" aria-current="page">Tutti</a>', false);
        // Nessuna categoria è mai "corrente" su /notizie: solo "Tutti" lo è.
        $response->assertSee('href="'.route('categoria', 'intelligenza-artificiale').'">Intelligenza Artificiale</a>', false);
    }

    public function test_notizie_chips_never_include_a_draft_or_scheduled_category(): void
    {
        $this->publishedArticle('energia');

        $draft = Category::create([
            'name' => 'Bozza Nascosta Notizie',
            'slug' => 'bozza-nascosta-notizie',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('categoria', $draft->slug).'"', false);
    }

    public function test_notizie_still_shows_correct_badge_label_for_a_since_deactivated_category(): void
    {
        // $categoryLabelOptions (etichette badge) resta Category::options(false),
        // indipendente dal componente chip: un articolo già pubblicato in una
        // categoria nel frattempo disattivata deve continuare a mostrare il
        // nome umano della categoria, non lo slug grezzo.
        $this->publishedArticle('energia');
        Category::where('slug', 'energia')->update(['is_active' => false]);

        $response = $this->get(route('notizie'));

        $response->assertOk();
        $response->assertSee('Energia & Clima');
        // La categoria disattivata non deve però comparire tra i chip
        // "Argomenti", che restano solo genuinamente pubblici.
        $response->assertDontSee('href="'.route('categoria', 'energia').'"', false);
    }

    /**
     * Finding Codex (P2, PR #553): sia notizie.blade.php che
     * categoria.blade.php includono anche components/sidebar.blade.php, il
     * cui topic-cloud è già un <nav aria-label="Argomenti">. Se il chip-row
     * principale usasse lo stesso nome accessibile, la pagina esporrebbe due
     * landmark "nav" indistinguibili per chi naviga con tecnologie
     * assistive. Il chip-row principale deve avere un nome distinto.
     */
    public function test_topic_chips_landmark_has_a_name_distinct_from_the_sidebar_topic_cloud(): void
    {
        $this->publishedArticle('energia');

        $response = $this->get(route('notizie'));
        $content = $response->getContent();

        $response->assertOk();
        // Esattamente un chip-row principale ("Filtra per argomento") ed
        // esattamente un topic-cloud di sidebar ("Argomenti"): due landmark
        // "nav" distinti, mai lo stesso nome accessibile ripetuto due volte.
        $this->assertSame(1, substr_count($content, 'aria-label="Filtra per argomento"'));
        $this->assertSame(1, substr_count($content, 'aria-label="Argomenti"'));
    }
}
