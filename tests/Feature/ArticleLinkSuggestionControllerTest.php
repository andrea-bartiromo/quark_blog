<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    // Codex (PR #165, round 13): se il target viene eliminato tra il caricamento della
    // pagina di modifica e il click su "Inserisci", target_article_id (nullOnDelete(),
    // round 12) lascia il suggerimento 'proposed' con targetArticle null — deve restituire
    // 409 come ogni altro suggerimento non più utilizzabile, non un errore 500.
    public function test_insert_rejects_a_suggestion_whose_target_was_deleted(): void
    {
        $editor = $this->editor();

        $source = $this->article(['user_id' => $editor->id]);
        $target = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'articolo di prova',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $target->delete();

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => (string) $source->body]
        );

        $response->assertStatus(409);
        $this->assertSame($source->body, $source->fresh()->body);
        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);
    }

    // Codex (PR #165, round 14): target_slug è lo snapshot preso all'ultima "Analizza" —
    // se il target viene rinominato dopo, ma prima del click su "Inserisci", l'href
    // costruito qui usa (correttamente) lo slug ATTUALE, ma senza questo fix lo snapshot
    // sulla riga sarebbe rimasto il vecchio slug, disallineato da ciò che è realmente nel
    // body. "Inserisci" deve riallineare lo snapshot allo slug appena usato per l'href.
    public function test_insert_refreshes_the_target_slug_snapshot_to_the_slug_actually_used(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari', 'slug' => 'slug-vecchio']);
        $sourceBody = '<p>Vedi anche pannelli solari, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id, 'body' => $sourceBody]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => 'slug-vecchio',
            'anchor_text' => 'pannelli solari',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $target->update(['slug' => 'slug-nuovo']);

        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => $sourceBody]
        );

        $response->assertOk();
        $this->assertStringContainsString(route('articolo', 'slug-nuovo'), $response->json('body'));
        $this->assertSame('slug-nuovo', $suggestion->fresh()->target_slug);
    }

    // Codex (PR #165, round 17): se "Inserisci" FALLISCE (l'anchor non è più presente nel
    // body perché già avvolta da un link precedente verso il vecchio slug), lo snapshot
    // target_slug non deve comunque essere aggiornato al nuovo slug — il link realmente
    // ancora presente nel body punta al vecchio slug, non al nuovo.
    public function test_insert_never_updates_the_target_slug_snapshot_when_insertion_fails(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari', 'slug' => 'slug-vecchio']);
        $alreadyLinkedBody = '<p>Vedi anche <a href="'.route('articolo', 'slug-vecchio').'">pannelli solari</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id, 'body' => $alreadyLinkedBody]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => 'slug-vecchio',
            'anchor_text' => 'pannelli solari',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $target->update(['slug' => 'slug-nuovo']);

        // Un secondo click su "Inserisci" con lo stesso body (già linkato lato
        // client verso il vecchio slug): l'anchor è già dentro un <a>, non è più
        // "testo libero" da avvolgere — insert() fallisce (422).
        $response = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => $alreadyLinkedBody]
        );

        $response->assertStatus(422);
        $this->assertSame('slug-vecchio', $suggestion->fresh()->target_slug);
    }

    // Stesso principio del test precedente, verificato end-to-end: il link realmente
    // inserito (slug nuovo) deve poter essere ripulito dal body quando il target viene
    // eliminato dopo, non solo lo slug ormai obsoleto dell'analisi originaria.
    public function test_admin_update_strips_a_link_inserted_after_a_rename_when_the_target_is_later_deleted(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari', 'slug' => 'slug-vecchio']);
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => 'slug-vecchio',
            'anchor_text' => 'pannelli solari',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        $target->update(['slug' => 'slug-nuovo']);

        $insertResponse = $this->actingAs($editor)->postJson(
            route('admin.articles.link-suggestions.insert', [$source, $suggestion]),
            ['body' => '<p>Vedi anche pannelli solari, molto richiesti.</p>']
        );
        $insertResponse->assertOk();
        $linkedBody = $insertResponse->json('body');
        $targetUrl = route('articolo', 'slug-nuovo');
        $this->assertStringContainsString('href="'.$targetUrl.'"', $linkedBody);

        $target->delete();

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'published',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari', $freshBody);
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

    // Codex (PR #165, round 15): stesso principio dei due test precedenti (slug storico
    // reclamato), ma per il ramo "target eliminato" introdotto al round 12 — se un altro
    // articolo, temporalmente sicuro, reclama nel frattempo lo slug del target ormai
    // cancellato, il link nel body ora risolve legittimamente su quel nuovo articolo e non
    // va rimosso incondizionatamente solo perché target_article_id è null.
    public function test_admin_update_does_not_strip_a_link_whose_deleted_targets_slug_was_reclaimed_by_a_safe_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalTargetSlug = $target->slug;
        $targetUrl = route('articolo', $originalTargetSlug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $originalTargetSlug,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $target->delete();

        // Un altro articolo, sicuro (pubblicato), reclama lo slug ormai libero:
        // l'href già nel body ora risolve davvero su di lui.
        $otherArticle = $this->article(['user_id' => $editor->id, 'title' => 'Un altro articolo', 'slug' => $originalTargetSlug]);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'draft',
        ]);

        $response->assertRedirect(route('admin.articles'));

        // Il link nel body NON va toccato: risolve legittimamente sull'altro articolo.
        $freshBody = $source->fresh()->body;
        $this->assertStringContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertSame($otherArticle->slug, $originalTargetSlug);
    }

    // Stesso scenario, ma il reclamante non è temporalmente sicuro (bozza) — l'href non
    // risolve comunque a nulla di raggiungibile e va ripulito come prima del round 15.
    public function test_admin_update_still_strips_a_link_whose_deleted_targets_slug_was_reclaimed_by_an_unsafe_article(): void
    {
        $editor = $this->editor();

        $target = $this->article(['user_id' => $editor->id, 'title' => 'Pannelli solari di nuova generazione']);
        $originalTargetSlug = $target->slug;
        $targetUrl = route('articolo', $originalTargetSlug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';
        $source = $this->article(['user_id' => $editor->id]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $originalTargetSlug,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $target->delete();

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
        ]);

        $response->assertRedirect(route('admin.articles'));

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
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

    // 2l-bis. Codex (PR #165, P1 round 10): un suggerimento GIA' 'accepted' (in un
    // salvataggio precedente) va rivalutato a OGNI salvataggio successivo, non solo
    // quelli appena applicati — altrimenti un target diventato non sicuro DOPO
    // l'accettazione lascia un link ormai rotto nel body finché la source stessa non
    // viene pubblicata dallo scheduler con quel link morto dentro.
    public function test_admin_update_supersedes_and_strips_a_previously_accepted_link_whose_target_became_unsafe(): void
    {
        $editor = $this->editor();

        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
            'status' => 'scheduled',
            'published_at' => now()->addDays(5),
        ]);

        $targetUrl = route('articolo', $target->slug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';

        $source = $this->article([
            'user_id' => $editor->id,
            'status' => 'scheduled',
            'published_at' => now()->addDays(10),
            'body' => $linkedBody,
        ]);

        // Il suggerimento è già 'accepted' da un salvataggio PRECEDENTE: il
        // link è già fisicamente nel body, non arriva da applied_link_suggestions.
        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
            'reviewed_at' => now(),
            'reviewed_by' => $editor->id,
        ]);

        // Il target viene riprogrammato DOPO la source: non è più sicuro.
        $target->update(['published_at' => now()->addDays(20)]);

        // Un salvataggio successivo, anche senza applicare nessun nuovo
        // suggerimento (nessun applied_link_suggestions in questa richiesta).
        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'scheduled',
            'published_date' => now()->addDays(10)->format('Y-m-d'),
            'published_time' => now()->addDays(10)->format('H:i'),
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
    }

    // 2j-bis. Codex (PR #165, round 12): se il target viene eliminato tra il click su
    // "Inserisci" (il link è già fisicamente nel body inviato dal form) e il salvataggio
    // della source, target_article_id (nullOnDelete(), non più cascadeOnDelete()) lascia
    // sopravvivere la riga del suggerimento — markAccepted() deve poter comunque ripulire
    // il link dal body usando lo snapshot target_slug, non più la relazione targetArticle.
    public function test_admin_update_strips_a_link_whose_target_was_deleted_before_save(): void
    {
        $editor = $this->editor();

        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
        ]);

        $targetUrl = route('articolo', $target->slug);
        $linkedBody = '<p>Tra le soluzioni più diffuse ci sono i <a href="'.$targetUrl.'">pannelli solari di nuova generazione</a>, molto richiesti.</p>';

        $source = $this->article(['user_id' => $editor->id]);

        // Simula lo stato dopo un click su "Inserisci": il suggerimento è
        // 'proposed', con lo snapshot target_slug già valorizzato come lo
        // popolerebbe ArticleLinkSuggestionService::analyzeForSource().
        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'target_slug' => $target->slug,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $target->delete();

        // La riga sopravvive (nullOnDelete), solo il riferimento è azzerato.
        $this->assertNotNull($suggestion->fresh());
        $this->assertNull($suggestion->fresh()->target_article_id);

        $response = $this->actingAs($editor)->put(route('admin.articles.update', $source), [
            'title' => $source->title,
            'body' => $linkedBody,
            'category' => $source->category,
            'status' => 'published',
            'applied_link_suggestions' => [$suggestion->id],
        ]);

        $response->assertRedirect(route('admin.articles'));

        $this->assertSame(ArticleLinkSuggestion::STATUS_SUPERSEDED, $suggestion->fresh()->status);

        $freshBody = $source->fresh()->body;
        $this->assertStringNotContainsString('href="'.$targetUrl.'"', $freshBody);
        $this->assertStringContainsString('pannelli solari di nuova generazione', $freshBody);
    }

    // 2k. Codex (PR #165, P2 round 9): il salvataggio dell'articolo e la revalidazione/
    // pulizia dei suggerimenti applicati (markAccepted()) devono avvenire come un'unica
    // transazione — se markAccepted() fallisce, anche le modifiche già scritte da
    // $article->update($data) (es. il body con il link non più sicuro) devono annullarsi,
    // non restare persistite a metà.
    public function test_admin_update_rolls_back_the_article_update_if_mark_accepted_fails(): void
    {
        $editor = $this->editor();

        $source = $this->article(['user_id' => $editor->id, 'title' => 'Titolo originale']);

        $this->mock(ArticleLinkSuggestionService::class, function ($mock) {
            $mock->shouldReceive('markAccepted')->andThrow(new RuntimeException('guasto simulato'));
        });

        try {
            $this->actingAs($editor)->put(route('admin.articles.update', $source), [
                'title' => 'Titolo che non deve mai essere salvato',
                'body' => $source->body,
                'category' => $source->category,
                'status' => 'draft',
            ]);
        } catch (RuntimeException $exception) {
            $this->assertSame('guasto simulato', $exception->getMessage());
        }

        $this->assertSame('Titolo originale', $source->fresh()->title);
    }

    // 2l. Codex (PR #165, P2 round 9): riaprire il form di modifica non deve mostrare un
    // suggerimento 'proposed' il cui target è diventato non sicuro da quando fu proposto,
    // senza che una nuova "Analizza"/"Inserisci" lo abbia già rivalutato — altrimenti il
    // pannello mostrerebbe l'etichetta "sarà pubblico prima di questo articolo" quando non
    // è più vero.
    public function test_admin_edit_does_not_show_a_proposed_suggestion_whose_target_became_unsafe(): void
    {
        $editor = $this->editor();

        $target = $this->article([
            'user_id' => $editor->id,
            'title' => 'Pannelli solari di nuova generazione',
            'status' => 'scheduled',
            'published_at' => now()->addDays(5),
        ]);

        $source = $this->article([
            'user_id' => $editor->id,
            'status' => 'scheduled',
            'published_at' => now()->addDays(10),
        ]);

        $suggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'pannelli solari di nuova generazione',
            'reason' => 'motivo',
            'confidence_score' => 60,
        ]);

        // Il target viene riprogrammato DOPO la source (non più sicuro), ma
        // nessuna "Analizza"/"Inserisci" ha ancora rivalutato il
        // suggerimento: in DB resta 'proposed'.
        $target->update(['published_at' => now()->addDays(20)]);
        $this->assertSame(ArticleLinkSuggestion::STATUS_PROPOSED, $suggestion->fresh()->status);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $source));

        $response->assertOk();
        $linkSuggestions = $response->viewData('linkSuggestions');

        $this->assertFalse(
            $linkSuggestions->contains('id', $suggestion->id),
            'Il pannello non deve mostrare un suggerimento il cui target non è più temporalmente sicuro, anche se lo stato in DB è ancora "proposed".'
        );
    }

    // 2m. Codex (PR #165, P2 round 10): il filtro di sicurezza temporale va applicato
    // PRIMA del limite MAX_PROPOSED_RESULTS, non dopo — altrimenti, se le righe con
    // punteggio più alto sono proprio quelle diventate non sicure, il pannello risulta
    // vuoto anche quando esistono altri suggerimenti sicuri appena sotto in classifica.
    public function test_admin_edit_still_shows_safe_suggestions_below_the_display_limit(): void
    {
        $editor = $this->editor();

        $source = $this->article(['user_id' => $editor->id]);

        // I MAX_PROPOSED_RESULTS suggerimenti a punteggio più alto diventano
        // tutti non sicuri (target retrocesso a bozza) DOPO essere stati proposti.
        for ($i = 0; $i < ArticleLinkSuggestion::MAX_PROPOSED_RESULTS; $i++) {
            $unsafeTarget = $this->article(['user_id' => $editor->id, 'title' => 'Target non sicuro '.$i]);

            ArticleLinkSuggestion::create([
                'source_article_id' => $source->id,
                'target_article_id' => $unsafeTarget->id,
                'anchor_text' => 'target non sicuro '.$i,
                'reason' => 'motivo',
                'confidence_score' => 90 - $i,
            ]);

            $unsafeTarget->update(['status' => 'draft', 'published_at' => null]);
        }

        // Un suggerimento a punteggio più basso, sotto i primi
        // MAX_PROPOSED_RESULTS, ma il cui target è (ed è sempre stato) sicuro.
        $safeTarget = $this->article(['user_id' => $editor->id, 'title' => 'Target sicuro']);

        $safeSuggestion = ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $safeTarget->id,
            'anchor_text' => 'target sicuro',
            'reason' => 'motivo',
            'confidence_score' => 40,
        ]);

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $source));

        $response->assertOk();
        $linkSuggestions = $response->viewData('linkSuggestions');

        $this->assertTrue(
            $linkSuggestions->contains('id', $safeSuggestion->id),
            'Un suggerimento sicuro sotto i primi MAX_PROPOSED_RESULTS deve comunque comparire quando quelli a punteggio più alto sono stati esclusi perché non più sicuri.'
        );
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
