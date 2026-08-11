<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleLinkSuggestionControllerTest extends TestCase
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

    // 1. L'analisi (Admin) persiste e restituisce i suggerimenti pertinenti
    public function test_admin_analyze_returns_pertinent_suggestions(): void
    {
        $editor = $this->editor();

        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
            'excerpt' => 'Analisi dei pannelli solari più efficienti',
        ]);

        $source = $this->article([
            'user_id' => $editor->id,
            'title' => 'Guida alla transizione energetica',
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $response = $this->actingAs($editor)->postJson(route('admin.articles.link-suggestions.analyze', $source));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $target->id]);

        $this->assertSame(1, ArticleLinkSuggestion::where('source_article_id', $source->id)->count());
    }

    // 2. "Inserisci" modifica il body ricevuto e NON salva l'articolo (l'Article resta invariato)
    public function test_insert_returns_updated_body_without_saving_the_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalBody = '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id, 'body' => $originalBody]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => $originalBody]
        );

        $response->assertOk();
        $response->assertJsonStructure(['body']);
        $this->assertStringContainsString('<a href=', $response->json('body'));

        // Il record Article non è stato toccato: il salvataggio resta un'azione umana esplicita.
        $this->assertSame($originalBody, $source->fresh()->body);

        // "Inserisci" non marca ancora accettato: lo fa solo il salvataggio
        // effettivo dell'articolo (vedi markAccepted). Se la redazione
        // abbandona la modifica senza salvare, il suggerimento resta
        // riproponibile invece di risultare "gestito" per sempre.
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
        $this->assertNull($suggestion->fresh()->reviewed_at);
        $this->assertNull($suggestion->fresh()->reviewed_by);
    }

    /**
     * Codex (PR #165, P1): tra la creazione del suggerimento e il click su
     * "Inserisci" il target può aver perso l'eleggibilità temporale
     * (riprogrammato DOPO la source) — deve essere rifiutato, non inserito,
     * e il suggerimento marcato superato (non lasciato "proposed" per un
     * secondo tentativo che fallirebbe di nuovo allo stesso modo).
     */
    public function test_insert_rejects_a_suggestion_whose_target_was_rescheduled_after_the_source(): void
    {
        $editor = $this->editor();

        $source = $this->article([
            'user_id' => $editor->id,
            'status' => 'scheduled',
            'published_at' => '2026-08-12 15:30:00',
        ]);

        // Al momento della proposta il target era scheduled 19/08 — DOPO la
        // source (12/08): già non sarebbe stato eleggibile da subito, ma
        // qui simuliamo il caso più subdolo, quello di un target che DIVENTA
        // non sicuro tra "Analizza" e "Inserisci" (vedi test successivo per
        // il caso "già non sicuro fin da subito" coperto allo stesso modo).
        $target = $this->article(['user_id' => $editor->id, 'status' => 'scheduled', 'published_at' => '2026-08-19 15:30:00']);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'articolo di prova',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => (string) $source->body]
        );

        $response->assertStatus(409);
        $this->assertSame($source->body, $source->fresh()->body);
        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);
    }

    /**
     * Stesso principio, ma il target viene retrocesso a bozza dopo la
     * proposta (non più temporalmente sicuro in alcun modo, a prescindere
     * dalla data).
     */
    public function test_insert_rejects_a_suggestion_whose_target_was_demoted_to_draft(): void
    {
        $editor = $this->editor();

        $source = $this->article([
            'user_id' => $editor->id,
            'status' => 'scheduled',
            'published_at' => '2026-08-19 15:30:00',
        ]);

        $target = $this->article(['user_id' => $editor->id, 'status' => 'scheduled', 'published_at' => '2026-08-12 15:30:00']);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'articolo di prova',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target era sicuro al momento della proposta; ora viene
        // retrocesso a bozza prima che la redazione clicchi "Inserisci".
        $target->update(['status' => 'draft', 'published_at' => null]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => (string) $source->body]
        );

        $response->assertStatus(409);
        $this->assertSame($source->body, $source->fresh()->body);
        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);
    }

    // 2b. Il salvataggio effettivo dell'articolo (Admin) marca accettati i suggerimenti applicati nel form
    public function test_admin_update_marks_applied_suggestions_as_accepted_on_save(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.route('articolo', $target->slug).'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertNotNull($suggestion->fresh()->reviewed_at);
        $this->assertSame($editor->id, $suggestion->fresh()->reviewed_by);
    }

    // 2c. Se l'articolo non viene mai salvato (o è salvato senza quel suggerimento tra gli applicati), resta "proposed"
    public function test_admin_update_without_applied_suggestions_leaves_them_proposed(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $source->body,
            'category' => $source->category,
            'status' => 'draft',
        ]);

        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
    }

    // 2d. Codex (PR #165, P1 round 2): se nello stesso salvataggio la redazione
    // sposta la programmazione della SOURCE a una data che rende il target
    // (già scheduled, sicuro contro la vecchia data) non più sicuro, il
    // suggerimento non va accettato e il link — già presente nel body inviato
    // dal form — va tolto, non solo lasciato "non accettato".
    public function test_admin_update_supersedes_and_strips_link_when_new_source_schedule_makes_target_unsafe(): void
    {
        $editor = $this->editor();

        $targetAt = now()->addDays(20);
        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
            'status' => 'scheduled',
            'published_at' => $targetAt,
        ]);

        $targetUrl = route('articolo', $target->slug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';

        $source = $this->article([
            'user_id' => $editor->id,
            'status' => 'scheduled',
            'published_at' => now()->addDays(30),
        ]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Al momento di "Inserisci" (in una richiesta precedente, non
        // riprodotta qui) il target era sicuro: 30 giorni > 20 giorni. Ma
        // nello stesso salvataggio la redazione riprogramma la source a 15
        // giorni, PRIMA del target: il link appena inserito non è più
        // temporalmente sicuro.
        $newSourceAt = now()->addDays(15);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'scheduled',
            'published_date' => $newSourceAt->format('Y-m-d'),
            'published_time' => $newSourceAt->format('H:i'),
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
    }

    // 2e. Codex (PR #165, P1 round 3): un'"Analizza" intermedia (tra "Inserisci" e il
    // salvataggio) può già aver marcato il suggerimento come superato — il link, già
    // presente nel body inviato dal form, va comunque ripulito, non lasciato perché il
    // suggerimento non è più 'proposed'.
    public function test_admin_update_strips_link_even_if_an_intervening_analyze_already_superseded_the_suggestion(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $targetUrl = route('articolo', $target->slug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target viene retrocesso a bozza DOPO che "Inserisci" ha già
        // messo il link nel body lato client, ma PRIMA del salvataggio.
        $target->update(['status' => 'draft', 'published_at' => null]);

        // Una "Analizza" intermedia (es. un refresh del pannello prima di
        // salvare) supera già il suggerimento.
        $this->actingAs($editor)->postJson(route('admin.articles.link-suggestions.analyze', $source));
        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
    }

    // 2f. Codex (PR #165, P2 round 3): se il target viene rinominato (nuovo slug) tra
    // "Inserisci" e il salvataggio, l'href inviato dal form punta ancora al vecchio
    // slug — la pulizia deve coprire anche gli slug storici (ArticleSlugRedirect), non
    // solo quello attuale del target.
    public function test_admin_update_strips_link_using_the_targets_old_slug_after_a_rename(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $oldTargetUrl = route('articolo', $target->slug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$oldTargetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target viene rinominato (nuovo slug, tramite ArticleSlugRedirect
        // registrato da Article::booted()) E retrocesso a bozza DOPO che
        // "Inserisci" ha già messo il link (con il VECCHIO slug) nel body
        // lato client, ma PRIMA del salvataggio.
        $target->update([
            'title' => 'Pannelli fotovoltaici di ultima generazione',
            'slug' => 'pannelli-fotovoltaici-ultima-generazione-'.uniqid(),
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->assertNotSame($oldTargetUrl, route('articolo', $target->slug));

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$oldTargetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
    }

    // 2g. Codex (PR #165, P2 round 4): se il vecchio slug liberato dal target rinominato
    // viene nel frattempo reclamato come slug ATTUALE di un ARTICOLO DIVERSO, quell'href
    // nel body punta DAVVERO all'altro articolo (la rotta pubblica risolve sempre prima lo
    // slug corrente — vedi ArticleController::show()) — non va tolto come se fosse ancora
    // il target diventato non sicuro, altrimenti si romperebbe un link valido ed estraneo.
    public function test_admin_update_does_not_strip_a_link_whose_old_slug_was_reclaimed_by_another_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalTargetSlug = $target->slug;
        $oldTargetUrl = route('articolo', $originalTargetSlug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$oldTargetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target viene rinominato (libera il suo vecchio slug, registrato
        // come ArticleSlugRedirect) e retrocesso a bozza — diventa non sicuro.
        $target->update([
            'title' => 'Pannelli fotovoltaici di ultima generazione',
            'slug' => 'pannelli-fotovoltaici-ultima-generazione-'.uniqid(),
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Un ALTRO articolo, del tutto estraneo a questo suggerimento,
        // reclama nel frattempo il vecchio slug ormai libero: l'href già nel
        // body ora punta davvero a questo articolo, non più al target.
        $otherArticle = $this->article(['user_id' => $editor->id, 'title' => 'Un altro articolo', 'slug' => $originalTargetSlug]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        // Il suggerimento resta comunque superato: il target originale non è
        // più sicuro, indipendentemente da chi possiede oggi il vecchio slug.
        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        // Ma il link nel body NON va toccato: risolve legittimamente
        // sull'altro articolo, non sul target diventato non sicuro.
        $freshBody = $source->fresh()->body;
        $this->assertStringContainsString('href="'.$oldTargetUrl.'"', $freshBody);
        $this->assertSame($otherArticle->slug, $originalTargetSlug);
    }

    // 2h. Codex (PR #165, P2 round 5): se il vecchio slug liberato dal target viene
    // reclamato da un articolo che NON è a sua volta pubblicamente/temporalmente
    // raggiungibile (bozza), quell'href non risolve comunque a nulla — va ripulito come
    // ogni altro slug storico del target diventato non sicuro, non lasciato nel body solo
    // perché "qualcuno" lo reclama nominalmente.
    public function test_admin_update_still_strips_a_link_whose_old_slug_was_reclaimed_by_an_unsafe_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalTargetSlug = $target->slug;
        $oldTargetUrl = route('articolo', $originalTargetSlug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$oldTargetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target viene rinominato e retrocesso a bozza — diventa non sicuro.
        $target->update([
            'title' => 'Pannelli fotovoltaici di ultima generazione',
            'slug' => 'pannelli-fotovoltaici-ultima-generazione-'.uniqid(),
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Un altro articolo reclama nominalmente il vecchio slug, ma è
        // anch'esso una bozza: Article::published() non lo troverebbe, e il
        // redirect storico punta ancora al vecchio target (non sicuro) —
        // l'href non risolverebbe comunque a nulla di raggiungibile.
        $otherArticle = $this->article([
            'user_id' => $editor->id,
            'title' => 'Un altro articolo, ancora in bozza',
            'slug' => $originalTargetSlug,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$oldTargetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
        $this->assertSame($otherArticle->slug, $originalTargetSlug);
    }

    // 2i. Codex (PR #165, P2 round 7): un link ESTERNO il cui path contiene per pura
    // coincidenza "/articolo/{slug del target diventato non sicuro}" non va MAI confuso
    // con il collegamento interno Kairus da ripulire — a differenza dei conteggi di sola
    // lettura, qui la rimozione modifica davvero il body: un falso positivo cancellerebbe
    // contenuto legittimo ed estraneo.
    public function test_admin_update_never_strips_an_external_link_that_coincidentally_matches_the_slug_path(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $externalUrl = 'https://esempio-esterno.test/articolo/'.$target->slug;
        $linkedBody = '<p>Approfondimento esterno: <a href="'.$externalUrl.'">pannelli solari di nuova generazione</a>, fonte indipendente.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target diventa non sicuro (retrocesso a bozza): markAccepted()
        // proverà a ripulire ogni link verso il suo slug.
        $target->update(['status' => 'draft', 'published_at' => null]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        // Il link esterno resta intatto: non è un collegamento interno
        // Kairus, solo un URL che ne condivide per coincidenza il path.
        $freshBody = $source->fresh()->body;
        $this->assertStringContainsString('href="'.$externalUrl.'"', $freshBody);
    }

    // 2j. Codex (PR #165, P2 round 8): stesso principio del caso 2i, ma con un link
    // INTERNO (stesso host, supera isSafeInternalHref()) verso una pagina diversa da
    // /articolo/{slug} che contiene comunque quella sottostringa in query string — il
    // path va confrontato per intero con la rotta articolo, non solo "contenere" lo slug.
    public function test_admin_update_never_strips_an_internal_link_to_a_different_route_that_contains_the_slug_in_the_query_string(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $unrelatedUrl = '/ricerca?q=/articolo/'.$target->slug;
        $linkedBody = '<p>Altri risultati: <a href="'.$unrelatedUrl.'">pannelli solari di nuova generazione</a> nella ricerca.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $target->update(['status' => 'draft', 'published_at' => null]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        // Il link verso /ricerca resta intatto: non è la rotta /articolo/{slug},
        // solo un URL interno che ne contiene il testo in query string.
        $freshBody = $source->fresh()->body;
        $this->assertStringContainsString('href="'.$unrelatedUrl.'"', $freshBody);
    }

    // 3. "Ignora" marca il suggerimento e una successiva analisi non lo ripropone
    public function test_ignore_marks_the_suggestion_and_it_is_not_re_proposed(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article([
            'user_id' => $editor->id,
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $ignoreResponse = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.ignore', [$source, $suggestion])
        );

        $ignoreResponse->assertOk();
        $this->assertSame(ArticleLinkSuggestion::STATUS_IGNORED, $suggestion->fresh()->status);

        $analyzeResponse = $this->actingAs($editor)->postJson(route('admin.articles.link-suggestions.analyze', $source));
        $analyzeResponse->assertOk();
        $analyzeResponse->assertJsonMissing(['id' => $suggestion->id]);
        $this->assertSame(1, ArticleLinkSuggestion::where('source_article_id', $source->id)->count());
    }

    // 4. Un collaboratore (author) non può analizzare/gestire i suggerimenti di un articolo altrui
    public function test_an_author_cannot_manage_suggestions_of_another_authors_article(): void
    {
        $owner = $this->author();
        $intruder = $this->author();

        $source = $this->article(['user_id' => $owner->id, 'status' => 'review', 'published_at' => null]);

        $response = $this->actingAs($intruder)->postJson(route('redazione.articles.link-suggestions.analyze', $source));

        $response->assertForbidden();
    }

    // 5. Un collaboratore può analizzare i propri articoli
    public function test_an_author_can_analyze_suggestions_for_their_own_article(): void
    {
        $owner = $this->author();

        $this->article(['title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article([
            'user_id' => $owner->id,
            'status' => 'review',
            'published_at' => null,
            'body' => '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>',
        ]);

        $response = $this->actingAs($owner)->postJson(route('redazione.articles.link-suggestions.analyze', $source));

        $response->assertOk();
    }

    // 5b. Un suggerimento non appartenente all'articolo indicato nella rotta viene rifiutato con 404
    //     (protegge da un utente che possiede l'articolo A ma prova ad agire su un suggerimento dell'articolo B)
    public function test_a_suggestion_belonging_to_a_different_article_is_rejected_with_404(): void
    {
        $editor = $this->editor();

        $articleA = $this->article(['user_id' => $editor->id]);
        $articleB = $this->article(['user_id' => $editor->id]);
        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);

        $suggestionOfB = ArticleLinkSuggestion::create([
            'source_article_id' => $articleB->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $insertResponse = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$articleA, $suggestionOfB]),
            ['body' => $articleA->body]
        );
        $insertResponse->assertNotFound();

        $ignoreResponse = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.ignore', [$articleA, $suggestionOfB])
        );
        $ignoreResponse->assertNotFound();

        // Il suggerimento non è stato toccato dai tentativi rifiutati.
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestionOfB->fresh()->status);
    }

    // 6. Se l'anchor non è più presente nel body inviato, l'inserimento fallisce e il suggerimento resta "proposed"
    public function test_insert_fails_gracefully_when_anchor_text_is_no_longer_present(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => '<p>Testo completamente diverso, senza la frase suggerita.</p>']
        );

        $response->assertStatus(422);
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);
    }

    // 7. Un suggerimento già gestito non può essere inserito/ignorato di nuovo
    public function test_an_already_reviewed_suggestion_cannot_be_actioned_again(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.ignore', [$source, $suggestion])
        );

        $response->assertStatus(409);
    }
}
