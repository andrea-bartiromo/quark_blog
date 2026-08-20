<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Copertura della trasformazione /admin/articoli in strumento editoriale
 * scalabile (paginazione server-side, filtri stato/categoria/autore,
 * persistenza query-string, stato vuoto distinto, sanitizzazione
 * parametri, budget query su dataset grandi). ArticlesIndexTest resta il
 * file di riferimento per il comportamento preesistente (riga compatta,
 * fallback copertina, ricerca su titolo/sommario/corpo, badge
 * "collegamenti ad articoli") — qui si copre solo ciò che è nuovo.
 */
class AdminArticlesManagementTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $user, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'body' => 'Testo di prova.',
            'category' => 'scienza',
            'cover_image' => null,
            'status' => 'draft',
            'read_minutes' => 2,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    /**
     * Crea $count righe direttamente via query builder (bypassa gli
     * observer di Article — sync progetti, notifiche Percorsi, redirect
     * slug — irrilevanti qui e proibitivamente lenti uno per uno su
     * migliaia di righe) per i test di scala realistica.
     */
    private function bulkInsertArticles(int $count, User $author, array $overridesPerRow = []): void
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = array_merge([
                'user_id' => $author->id,
                'title' => 'Articolo bulk numero '.$i,
                'slug' => 'articolo-bulk-'.$i.'-'.uniqid('', true),
                'excerpt' => 'Sommario dell\'articolo bulk '.$i,
                'body' => '<p>Corpo dell\'articolo bulk numero '.$i.'.</p>',
                'category' => ['spazio', 'energia', 'salute', 'societa'][$i % 4],
                'status' => Article::STATUS_PUBLISHED,
                'featured' => false,
                'read_minutes' => 3,
                'views' => 0,
                'published_at' => $now->copy()->subMinutes($i),
                'verification_status' => 'unverified',
                'created_at' => $now->copy()->subMinutes($i),
                'updated_at' => $now,
            ], $overridesPerRow[$i] ?? []);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('articles')->insert($chunk);
        }
    }

    public function test_the_list_is_paginated_server_side_not_a_single_full_table(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(40, $editor);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('Articolo bulk numero 0');
        $response->assertDontSee('Articolo bulk numero 39');
        $response->assertSee('href="'.route('admin.articles', ['page' => 2]).'"', false);
    }

    public function test_page_two_shows_the_remaining_articles(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(40, $editor);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['page' => 2]));

        $response->assertOk();
        $response->assertSee('Articolo bulk numero 39');
        $response->assertDontSee('Articolo bulk numero 0');
    }

    public function test_category_filter_shows_only_matching_articles(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Pezzo di Spazio', 'category' => 'spazio', 'status' => 'published', 'published_at' => now()]);
        $this->article($editor, ['title' => 'Pezzo di Energia', 'category' => 'energia', 'status' => 'published', 'published_at' => now()]);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'spazio']));

        $response->assertOk();
        $response->assertSee('Pezzo di Spazio');
        $response->assertDontSee('Pezzo di Energia');
    }

    public function test_category_filter_with_no_matches_shows_neither_article(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Pezzo esistente', 'category' => 'spazio']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'categoria-inesistente']));

        $response->assertOk();
        $response->assertDontSee('Pezzo esistente');
    }

    public static function editorialStatuses(): array
    {
        return [
            'bozza' => [Article::STATUS_DRAFT],
            'in revisione' => [Article::STATUS_REVIEW],
            'programmato' => [Article::STATUS_SCHEDULED],
            'pubblicato' => [Article::STATUS_PUBLISHED],
        ];
    }

    #[DataProvider('editorialStatuses')]
    public function test_status_filter_shows_only_articles_in_that_status(string $status): void
    {
        $editor = $this->editor();
        $matchingOverrides = $status === Article::STATUS_SCHEDULED
            ? ['status' => $status, 'published_at' => now()->addDay()]
            : ['status' => $status];

        $this->article($editor, array_merge(['title' => 'Articolo nello stato cercato'], $matchingOverrides));

        foreach (Article::statusOptions() as $otherStatus => $label) {
            if ($otherStatus === $status) {
                continue;
            }

            $overrides = $otherStatus === Article::STATUS_SCHEDULED
                ? ['status' => $otherStatus, 'published_at' => now()->addDay()]
                : ['status' => $otherStatus];

            $this->article($editor, array_merge(['title' => 'Articolo '.$otherStatus], $overrides));
        }

        $response = $this->actingAs($editor)->get(route('admin.articles', ['status' => $status]));

        $response->assertOk();
        $response->assertSee('Articolo nello stato cercato');

        foreach (array_keys(Article::statusOptions()) as $otherStatus) {
            if ($otherStatus === $status) {
                continue;
            }

            $response->assertDontSee('Articolo '.$otherStatus);
        }
    }

    public function test_combining_search_status_and_category_narrows_correctly(): void
    {
        $editor = $this->editor();
        $this->article($editor, [
            'title' => 'La relatività generale spiegata',
            'category' => 'spazio',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->article($editor, [
            'title' => 'La relatività generale e la salute mentale',
            'category' => 'salute',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->article($editor, [
            'title' => 'Relatività: bozza da rivedere',
            'category' => 'spazio',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles', [
            'q' => 'relatività',
            'status' => 'published',
            'category' => 'spazio',
        ]));

        $response->assertOk();
        $response->assertSee('La relatività generale spiegata');
        $response->assertDontSee('La relatività generale e la salute mentale');
        $response->assertDontSee('Relatività: bozza da rivedere');
    }

    public function test_author_filter_shows_only_that_authors_articles(): void
    {
        $editor = $this->editor();
        $otherAuthor = User::factory()->create(['role' => 'author']);

        $this->article($editor, ['title' => 'Pezzo del primo autore']);
        $this->article($otherAuthor, ['title' => 'Pezzo del secondo autore']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['author' => $otherAuthor->id]));

        $response->assertOk();
        $response->assertSee('Pezzo del secondo autore');
        $response->assertDontSee('Pezzo del primo autore');
    }

    public static function invalidAuthorIds(): array
    {
        return [
            'decimal' => ['1.5'],
            'scientific notation' => ['1e2'],
            'zero' => ['0'],
            'negative' => ['-1'],
            'text' => ['non-un-id'],
            'surrounding whitespace' => [' 1 '],
        ];
    }

    #[DataProvider('invalidAuthorIds')]
    public function test_invalid_author_ids_are_ignored(string $author): void
    {
        $editor = $this->editor();
        $otherAuthor = User::factory()->create(['role' => 'author']);
        $this->article($editor, ['title' => 'Articolo editor']);
        $this->article($otherAuthor, ['title' => 'Articolo autore']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['author' => $author]));

        $response->assertOk();
        $response->assertSee('Articolo editor');
        $response->assertSee('Articolo autore');
    }

    public function test_filters_persist_across_pagination_links(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(30, $editor, array_fill(0, 30, ['category' => 'spazio', 'status' => Article::STATUS_PUBLISHED]));
        $this->article($editor, ['title' => 'Rumore fuori categoria', 'category' => 'energia', 'status' => 'published', 'published_at' => now()]);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'spazio']));

        $response->assertOk();
        $response->assertSee('category=spazio', false);
        $response->assertDontSee('Rumore fuori categoria');

        $secondPage = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'spazio', 'page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertDontSee('Rumore fuori categoria');
    }

    public function test_no_filters_shows_the_reset_control_hidden_and_all_articles(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Primo articolo']);
        $this->article($editor, ['title' => 'Secondo articolo']);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('Primo articolo');
        $response->assertSee('Secondo articolo');
        $response->assertDontSee('Azzera filtri');
    }

    public function test_active_filters_show_the_reset_control_which_clears_them(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo bozza', 'status' => 'draft']);
        $this->article($editor, ['title' => 'Articolo pubblicato', 'status' => 'published', 'published_at' => now()]);

        $filtered = $this->actingAs($editor)->get(route('admin.articles', ['status' => 'draft']));
        $filtered->assertOk();
        $filtered->assertSee('Azzera filtri');
        $filtered->assertSee('href="'.route('admin.articles').'"', false);

        $reset = $this->actingAs($editor)->get(route('admin.articles'));
        $reset->assertOk();
        $reset->assertSee('Articolo bozza');
        $reset->assertSee('Articolo pubblicato');
    }

    public function test_result_count_reflects_the_filtered_total_not_the_page_size(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(30, $editor, array_fill(0, 30, ['status' => Article::STATUS_PUBLISHED, 'category' => 'spazio']));

        $response = $this->actingAs($editor)->get(route('admin.articles', ['category' => 'spazio']));

        $response->assertOk();
        $response->assertSee('30 risultati');
    }

    public function test_empty_archive_shows_a_create_first_article_message(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('ancora nessun articolo');
        $response->assertSee(route('admin.articles.create'), false);
    }

    public function test_no_results_for_filters_shows_a_different_message_than_a_truly_empty_archive(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Unico articolo esistente', 'status' => 'draft']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['status' => 'published']));

        $response->assertOk();
        $response->assertSee('Nessun articolo corrisponde ai filtri selezionati');
        $response->assertDontSee('ancora nessun articolo');
    }

    public function test_an_unknown_status_value_is_ignored_not_an_error(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo visibile']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['status' => 'stato-che-non-esiste']));

        $response->assertOk();
        $response->assertSee('Articolo visibile');
    }

    public function test_unknown_query_parameters_are_ignored_not_an_error(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo visibile']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['parametro_mai_esistito' => 'qualsiasi']));

        $response->assertOk();
        $response->assertSee('Articolo visibile');
    }

    public function test_a_non_numeric_page_parameter_does_not_break_the_page(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo visibile']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['page' => 'abc']));

        $response->assertOk();
        $response->assertSee('Articolo visibile');
    }

    public function test_a_page_number_beyond_the_last_page_redirects_to_the_last_valid_page(): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => 'Articolo visibile']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['page' => 9999]));

        $response->assertRedirect(route('admin.articles'));
    }

    public function test_a_long_search_term_is_trimmed_and_truncated_to_150_unicode_characters(): void
    {
        $editor = $this->editor();
        $prefix = str_repeat('è', 150);
        $this->article($editor, ['title' => $prefix.' finale']);

        $response = $this->actingAs($editor)->get(route('admin.articles', [
            'q' => '  '.$prefix.'NON-DEVE-ENTRARE  ',
        ]));

        $response->assertOk();
        $response->assertSee($prefix.' finale');
        $response->assertSee('value="'.$prefix.'"', false);
        $response->assertDontSee('NON-DEVE-ENTRARE');
    }

    public static function literalLikeSearchTerms(): array
    {
        return [
            'percent' => ['50%', 'Sconto del 50% reale'],
            'underscore' => ['nome_file', 'Manuale nome_file definitivo'],
            'escape character' => ['wow!', 'Un risultato wow! verificato'],
            'apostrophe' => ["l'apostrofo", "Guida all'apostrofo: l'apostrofo corretto"],
            'backslash' => ['C:\\temp', 'Percorso C:\\temp documentato'],
            'unicode' => ['caffè', 'La chimica del caffè spiegata'],
        ];
    }

    #[DataProvider('literalLikeSearchTerms')]
    public function test_like_special_characters_are_searched_literally(string $term, string $matchingTitle): void
    {
        $editor = $this->editor();
        $this->article($editor, ['title' => $matchingTitle]);
        $this->article($editor, ['title' => 'Articolo di controllo senza il termine cercato']);

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => $term]));

        $response->assertOk();
        $response->assertSee($matchingTitle);
        $response->assertDontSee('Articolo di controllo senza il termine cercato');
    }

    public function test_guest_is_redirected_to_login_when_visiting_the_filtered_list(): void
    {
        $response = $this->get(route('admin.articles', ['status' => 'draft']));

        $response->assertRedirect(route('login'));
    }

    public function test_with_a_thousand_articles_only_one_pages_worth_is_rendered(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(1000, $editor);

        $response = $this->actingAs($editor)->get(route('admin.articles'));

        $response->assertOk();
        $response->assertSee('1000 risultati');

        $rowCount = substr_count($response->getContent(), 'article-title-cell');
        $this->assertLessThanOrEqual(25, $rowCount, 'La pagina non deve mai renderizzare più di una pagina di righe.');
    }

    public function test_query_count_for_the_list_stays_flat_at_one_thousand_articles(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(1000, $editor);

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->actingAs($editor)->get(route('admin.articles'));
        $queriesAtOneK = $count;

        $this->bulkInsertArticles(1000, $editor);

        $count = 0;
        $this->actingAs($editor)->get(route('admin.articles'));
        $queriesAtTwoK = $count;

        $this->assertSame(
            $queriesAtOneK,
            $queriesAtTwoK,
            'Il numero di query per caricare la lista non deve crescere con la dimensione dell\'archivio (paginazione server-side, nessun N+1).'
        );
    }

    public function test_with_ten_thousand_articles_the_list_still_renders_one_page_quickly(): void
    {
        $editor = $this->editor();
        $this->bulkInsertArticles(10000, $editor);

        $start = microtime(true);
        $response = $this->actingAs($editor)->get(route('admin.articles'));
        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $response->assertSee('10000 risultati');

        $rowCount = substr_count($response->getContent(), 'article-title-cell');
        $this->assertLessThanOrEqual(25, $rowCount);

        $this->assertLessThan(
            5000,
            $elapsedMs,
            "La pagina con 10.000 articoli in archivio ha impiegato {$elapsedMs}ms: oltre la soglia che dimostrerebbe una scansione lineare dell'intero archivio invece di una query paginata."
        );
    }
}
