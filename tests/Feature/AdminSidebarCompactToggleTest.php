<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre la missione "Admin Sidebar UX & Navigation Hardening": il
 * pulsante di collasso/espansione della sidebar («, in cima alla
 * navigazione desktop) sembrava non produrre alcun effetto visibile
 * nell'uso reale.
 *
 * Causa reale (verificata con un browser reale headless, non assunta):
 * il JavaScript e il CSS della modalità compatta funzionano
 * correttamente — la classe .admin-sidebar-compact viene applicata a
 * <body>, persiste su localStorage, sopravvive alla navigazione tra
 * pagine. Il problema è che admin.css non aveva alcun cache-busting: un
 * browser che aveva già scaricato admin.css PRIMA dell'introduzione
 * della sidebar comprimibile (commit e037c04) può continuare a servirlo
 * dalla cache per giorni, ricevendo comunque il body con la classe
 * corretta (il JS gira) ma senza le regole CSS che le danno un effetto
 * visibile — il controllo "sembra" non fare nulla.
 *
 * Questo file testa il contratto HTML/CSS lato server verificabile da
 * PHPUnit (nessuna esecuzione JavaScript qui, i test Feature di Laravel
 * non eseguono un browser). Il comportamento dinamico effettivo —
 * click, toggle della classe, persistenza tra navigazioni, ripristino
 * dello stato dei gruppi all'uscita dalla modalità compatta — è stato
 * verificato manualmente con Playwright/Chromium durante l'indagine di
 * questa missione (screenshot e log conservati nel report finale), non
 * automatizzato in CI: il progetto non ha un tool di browser testing
 * (Dusk o simili) — aggiungerne uno è stato deliberatamente escluso,
 * fuori scope per un fix mirato.
 */
class AdminSidebarCompactToggleTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    // ── Root cause: cache-busting su admin.css ──────────────────────

    public function test_admin_layout_versions_admin_css_with_the_files_actual_mtime(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

        $expectedVersion = filemtime(public_path('css/admin.css'));

        $response->assertOk();
        $response->assertSee('css/admin.css?v='.$expectedVersion, false);
    }

    public function test_redazione_layout_versions_the_same_admin_css_with_the_files_actual_mtime(): void
    {
        $collaborator = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($collaborator)->get(route('redazione.dashboard'));

        $expectedVersion = filemtime(public_path('css/admin.css'));

        $response->assertOk();
        $response->assertSee('css/admin.css?v='.$expectedVersion, false);
    }

    public function test_admin_css_version_changes_when_the_file_is_modified(): void
    {
        // Prova diretta che il meccanismo è dinamico (filemtime), non un
        // numero fisso dimenticato nel template: se il file cambia, la
        // versione servita cambia con lui, senza bisogno di ricordarsi
        // di incrementare a mano un contatore.
        $path = public_path('css/admin.css');
        $originalContents = file_get_contents($path);
        $originalMtime = filemtime($path);

        try {
            touch($path, $originalMtime + 3600);
            clearstatcache(true, $path);

            $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

            $response->assertSee('css/admin.css?v='.($originalMtime + 3600), false);
            $response->assertDontSee('css/admin.css?v='.$originalMtime, false);
        } finally {
            touch($path, $originalMtime);
            file_put_contents($path, $originalContents);
            clearstatcache(true, $path);
        }
    }

    // ── Compact-mode group separator (CSS structural contract) ───────

    public function test_admin_css_visually_separates_nav_groups_in_compact_mode(): void
    {
        // Prima del fix, tutte le etichette di gruppo erano nascoste in
        // modalità compatta e ogni gruppo veniva forzato aperto, cosi'
        // le icone di gruppi diversi finivano in un'unica colonna
        // indistinguibile. Verifica che esista una regola CSS che separa
        // visivamente un gruppo dal successivo quando .admin-sidebar-compact
        // è attivo.
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.admin-sidebar-compact\s+\.admin-nav__group\s*\{[^}]*border-top/s',
            $css,
            'admin.css deve separare visivamente i gruppi in modalità compatta.'
        );
    }

    // ── Tooltip nativo sulle icone (compact mode discoverability) ────

    public function test_nav_link_icons_carry_a_native_tooltip_matching_their_label(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('<span class="icon" aria-hidden="true" title="Dashboard">', false);
        $response->assertSee('<span class="icon" aria-hidden="true" title="Articoli">', false);
        $response->assertSee('<span class="icon" aria-hidden="true" title="Profilo">', false);
    }

    public function test_logout_button_carries_a_native_tooltip(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('title="Esci"', false);
    }

    public function test_nav_link_active_state_markup_is_unaffected_by_the_tooltip_addition(): void
    {
        // Regression guard esplicito per il finding scoperto durante
        // questa missione: il tooltip deve stare sull'icona (span
        // interno), MAI sul tag <a>, altrimenti l'esatta sequenza di
        // attributi attesa da AdminNavigationTest::assertNavLinkActive()
        // (href poi class="active" poi aria-current="page" poi >, senza
        // nulla in mezzo) si romperebbe. Stessa normalizzazione degli
        // spazi bianchi di quell'helper, perché il tag è reso su più
        // righe nel template.
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));
        $response->assertOk();

        $normalized = preg_replace('/\s+/', ' ', $response->getContent());

        $this->assertStringContainsString(
            '<a href="'.route('admin.dashboard').'" class="active" aria-current="page" >',
            $normalized
        );
    }

    // ── Contratto JS: ripristino dello stato dei gruppi (verificato a mano) ──

    public function test_compact_toggle_script_snapshots_and_restores_group_open_state(): void
    {
        // Bug scoperto durante l'indagine (non il sintomo originale
        // segnalato, ma un problema reale collegato allo stesso
        // controllo): uscire dalla modalità compatta lasciava TUTTI i
        // gruppi aperti, anche quelli che l'utente non aveva mai aperto,
        // perché lo script forzava ogni gruppo aperto entrando in
        // modalità compatta ma non li richiudeva mai uscendo. Verificato
        // dinamicamente con Playwright durante questa missione (vedi
        // report finale); qui si verifica solo che il contratto — la
        // variabile di snapshot e il branch di ripristino — sia presente
        // nel markup effettivamente servito, cosi' una futura modifica
        // che la rimuovesse per errore farebbe fallire questo test.
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('groupsOpenBeforeCompact', false);
        $response->assertSee('group.open = groupsOpenBeforeCompact[index]', false);
    }

    public function test_compact_toggle_is_still_present_with_its_accessible_attributes(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('data-admin-sidebar-compact-toggle', false);
        $response->assertSee('aria-pressed="false"', false);
    }
}
