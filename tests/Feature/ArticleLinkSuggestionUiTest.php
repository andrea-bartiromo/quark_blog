<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionUiTest extends TestCase
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

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->editor()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    // 1. Il form Admin (modifica) mostra il pannello con il pulsante "Analizza" (mai auto-submit: type="button")
    public function test_admin_edit_form_shows_the_analyze_button(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('id="link-suggestions-analyze-btn"', false);
        $response->assertSee('<button type="button" id="link-suggestions-analyze-btn"', false);
    }

    // 2. Il form Admin (nuovo articolo) non mostra il pulsante: nessun articolo salvato da analizzare
    public function test_admin_create_form_does_not_show_the_analyze_button(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.articles.create'));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('Disponibile dopo il primo salvataggio');
        $response->assertDontSee('id="link-suggestions-analyze-btn"', false);
    }

    // 3. Il form Redazione (modifica, articolo proprio) mostra il pannello
    public function test_redazione_edit_form_shows_the_analyze_button_for_the_owner(): void
    {
        $author = $this->author();
        $article = $this->article(['user_id' => $author->id, 'status' => 'review', 'published_at' => null]);

        $response = $this->actingAs($author)->get(route('redazione.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('Collegamenti interni suggeriti');
        $response->assertSee('id="link-suggestions-analyze-btn"', false);
    }

    // 4. I suggerimenti già persistiti (proposed) vengono renderizzati lato server, senza richiedere un'analisi
    public function test_edit_form_renders_already_persisted_proposed_suggestions(): void
    {
        $editor = $this->editor();
        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari',
            'reason' => 'motivo di test',
            'confidence_score' => 55,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $source));

        $response->assertOk();
        $response->assertSee('Pannelli solari di nuova generazione');
        $response->assertSee('pannelli solari');
    }

    // 5. Route corrette incorporate nel markup per l'analisi (Admin)
    public function test_admin_edit_form_embeds_the_correct_analyze_route(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertSee(route('admin.articles.link-suggestions.analyze', $article), false);
    }

    // 6. Il titolo dell'articolo target è incorporato nel JSON iniziale in modo script-safe: non può chiudere il tag <script>
    public function test_target_titles_are_embedded_safely_in_the_initial_suggestions_json(): void
    {
        $editor = $this->editor();
        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Titolo malevolo </script><script>alert(1)</script>',
        ]);
        $source = $this->article(['user_id' => $editor->id]);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'corpo',
            'reason' => 'motivo di test',
            'confidence_score' => 55,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $source));

        $response->assertOk();
        // Il payload JSON non deve MAI contenere una sequenza </script> letterale
        // dentro il tag application/json: romperebbe fuori dallo script e
        // inietterebbe markup arbitrario nel form di un altro redattore.
        $response->assertDontSee('</script><script>alert(1)</script>', false);
        // Ma il contenuto (escapato in modo script-safe) deve comunque essere presente.
        $response->assertSee('Titolo malevolo', false);
    }

    // ── UX: un link salvato nel corpo deve restare riconoscibile nell'editor ──
    //
    // Riproduce l'articolo #13 reale: "Inserisci" ha già scritto
    // correttamente <a href="...">intelligenza artificiale</a> nel body
    // salvato (il meccanismo di inserimento backend NON è toccato da questa
    // missione, solo lo styling dell'editor) — ma in TinyMCE il colore/
    // sottolineatura del link non erano distinguibili dal testo normale.
    // Questi test verificano solo ciò che PHPUnit può verificare davvero:
    // che l'HTML salvato raggiunga intatto il campo dell'editor, e che la
    // regola CSS che rende il link riconoscibile sia presente nella pagina.
    // L'aspetto visivo effettivo (colore reso, contrasto) non è verificabile
    // in PHPUnit — vedi la verifica manuale nella descrizione della PR.

    public function test_a_saved_internal_link_reaches_the_admin_editor_intact(): void
    {
        $editor = $this->editor();
        $target = $this->article(['title' => 'Target linkato']);
        $article = $this->article([
            'user_id' => $editor->id,
            'body' => '<p>Testo introduttivo.</p><p>Un testo su <a href="'.route('articolo', $target->slug).'">intelligenza artificiale</a> spiegato bene.</p>',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        // Il campo #body e' un <textarea>: il markup del body vi compare
        // correttamente HTML-escapato nella risposta grezza (altrimenti un
        // "<a href=...>" letterale chiuderebbe il tag <textarea> a metà) —
        // e' esattamente cosi' che il browser/TinyMCE lo ricevono intatto,
        // per poi decodificarlo leggendo il valore del campo. Verificare la
        // forma escapata e' la prova corretta che l'HTML salvato raggiunge
        // l'editor senza essere alterato o rimosso lungo il tragitto.
        $response->assertSee(e('<a href="'.route('articolo', $target->slug).'">intelligenza artificiale</a>'), false);
    }

    public function test_the_admin_editor_styles_body_links_as_visually_recognizable(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        // TinyMCE content_style: prima di questo fix definiva regole per
        // body/heading/img/blockquote/table ma nessuna per "a" — un link
        // salvato correttamente ereditava il colore del testo normale ed
        // era visivamente indistinguibile. Verifica minima ma diretta:
        // la regola per "a" con colore e sottolineatura è davvero nel
        // content_style inviato al browser.
        $response->assertSee('a {', false);
        $response->assertSee('color: #0d9488;', false);
        $response->assertSee('text-decoration: underline;', false);
    }

    public function test_the_redazione_editor_styles_body_links_as_visually_recognizable(): void
    {
        $author = $this->author();
        $article = $this->article(['user_id' => $author->id, 'status' => 'review', 'published_at' => null]);

        $response = $this->actingAs($author)->get(route('redazione.articles.edit', $article));

        $response->assertOk();
        $response->assertSee('a { color:#0d9488; text-decoration:underline; }', false);
    }

    // "Inserisci" resta un'azione che modifica solo il contenuto
    // dell'editor: mai un submit automatico del form (invariato da questa
    // missione, che non tocca il meccanismo di inserimento).
    public function test_the_insert_suggestion_button_is_never_a_submit_button(): void
    {
        $editor = $this->editor();
        $article = $this->article(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        // La stringa e' costruita lato client (JS), ma il template statico
        // che la genera e' inline nella pagina: verifica che il pulsante
        // "Inserisci" sia dichiarato type="button" nel sorgente del
        // template JS stesso, non in un <button> reale gia' renderizzato
        // lato server (il rendering effettivo avviene dopo "Analizza").
        $response->assertSee('\'<button type="button" class="btn btn--primary" data-link-suggestion-action="insert"', false);
    }
}
