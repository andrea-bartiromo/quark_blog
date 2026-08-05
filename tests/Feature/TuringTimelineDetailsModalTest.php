<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TuringTimelineDetailsModalTest extends TestCase
{
    use RefreshDatabase;

    // Questi test esercitano /turing e i capitoli /turing/* assumendo
    // che siano pubblici (contenuto renderizzato, non un redirect):
    // stato futuro dietro config('turing.chapters_public'), attivato qui
    // esplicitamente. Il default di produzione (false, landing "In
    // arrivo" + redirect) e' coperto da TuringReleaseGateTest.
    protected function setUp(): void
    {
        parent::setUp();

        config(['turing.chapters_public' => true]);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function renderTimeline(array $events, string $id = 'timeline'): string
    {
        return Blade::render(
            '<x-special.timeline :events="$events" :id="$id" />',
            ['events' => $events, 'id' => $id]
        );
    }

    // 1. Un evento con 'details' genera il trigger della modale.
    public function test_event_with_details_renders_a_modal_trigger(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con approfondimento', 'text' => 'Testo breve.', 'details' => 'Testo lungo di approfondimento.'],
        ]);

        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);
        $this->assertStringContainsString('Approfondisci', $html);
    }

    // 2. La modale contiene titolo e approfondimento.
    public function test_modal_contains_the_event_title_and_details(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con approfondimento', 'text' => 'Testo breve.', 'details' => 'Testo lungo di approfondimento davvero dettagliato.'],
        ]);

        $this->assertStringContainsString('id="timeline-event-0-title"', $html);
        $this->assertStringContainsString('Evento con approfondimento', $html);
        $this->assertStringContainsString('Testo lungo di approfondimento davvero dettagliato.', $html);
    }

    // 3. Gli ID di trigger e modale corrispondono.
    public function test_trigger_and_modal_ids_match(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento', 'text' => 'Testo.', 'details' => 'Approfondimento.'],
        ], 'timeline-chapter-2');

        $this->assertStringContainsString('data-sp-modal-target="timeline-chapter-2-event-0"', $html);
        $this->assertStringContainsString('id="timeline-chapter-2-event-0"', $html);
    }

    // 4. Due eventi con titolo e anno identici non generano ID duplicati.
    public function test_two_events_with_identical_title_and_year_do_not_collide(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento ripetuto', 'text' => 'Primo.', 'details' => 'Primo approfondimento.'],
            ['year' => '1950', 'title' => 'Evento ripetuto', 'text' => 'Secondo.', 'details' => 'Secondo approfondimento.'],
        ]);

        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);
        $this->assertStringContainsString('data-sp-modal-target="timeline-event-1"', $html);
        $this->assertSame(1, substr_count($html, 'id="timeline-event-0"'));
        $this->assertSame(1, substr_count($html, 'id="timeline-event-1"'));
        $this->assertStringContainsString('Primo approfondimento.', $html);
        $this->assertStringContainsString('Secondo approfondimento.', $html);
    }

    // 5. Un evento senza 'details' continua a essere renderizzato senza modale.
    public function test_event_without_details_renders_exactly_as_before(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1936', 'title' => 'Evento senza approfondimento', 'text' => 'Testo breve visibile.'],
        ]);

        $this->assertStringContainsString('Testo breve visibile.', $html);
        $this->assertStringNotContainsString('data-sp-modal-target', $html);
        $this->assertStringNotContainsString('Approfondisci', $html);
        $this->assertStringNotContainsString('sp-modal', $html);
    }

    // 6. I link url/link_url esistenti restano validi, anche insieme a 'details'.
    public function test_existing_url_is_preserved_and_not_nested_inside_the_trigger_button(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con link', 'text' => 'Testo.', 'url' => '/turing/intelligence', 'details' => 'Approfondimento.'],
        ]);

        $this->assertStringContainsString('href="/turing/intelligence"', $html);
        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);

        // Nessun elemento interattivo annidato: verificato sul DOM reale,
        // non su un pattern testuale (un <a> chiuso seguito da un <button>
        // altrove nella pagina non e' una violazione; un <button> dentro un
        // <a> ancora aperto lo sarebbe).
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(
            0,
            $xpath->query('//button[ancestor::a]')->length,
            'Un <button> non deve mai essere annidato dentro un <a>.'
        );
        $this->assertSame(
            0,
            $xpath->query('//a[.//button]')->length,
            'Un <a> non deve mai contenere un <button>.'
        );
    }

    public function test_event_with_url_but_no_details_keeps_the_whole_card_as_a_link(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con link', 'text' => 'Testo.', 'url' => '/turing/intelligence'],
        ]);

        $this->assertMatchesRegularExpression('/<a[^>]*class="sp-timeline__card sp-timeline__card--link"[^>]*href="\/turing\/intelligence"/', $html);
        $this->assertStringNotContainsString('data-sp-modal-target', $html);
    }

    // 7. La Timeline predefinita a capitoli espone le modali.
    public function test_default_chapter_based_timeline_exposes_modals_on_the_hub_page(): void
    {
        $response = $this->get(route('turing'));

        $response->assertOk();
        $response->assertSee('timeline-chapter-1-event-0', false);
        $response->assertSee('data-sp-modal-target="timeline-chapter-1-event-0"', false);
        $response->assertSee('role="dialog"', false);
    }

    // 8. L'override CMS piatto supporta il nuovo campo.
    public function test_flat_cms_timeline_override_supports_the_details_field(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'is_active' => true,
            'content' => [
                'timeline' => [
                    [
                        'year' => '1999',
                        'title' => 'Evento personalizzato dal CMS',
                        'text' => 'Testo breve personalizzato.',
                        'details' => 'Approfondimento personalizzato dal CMS.',
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('turing'));

        $response->assertOk();
        $response->assertSee('Evento personalizzato dal CMS', false);
        $response->assertSee('data-sp-modal-target="timeline-event-0"', false);
        $response->assertSee('Approfondimento personalizzato dal CMS.', false);
        // Un override piatto disattiva i capitoli (comportamento invariato).
        $response->assertDontSee('timeline-chapter-opener-1', false);
    }

    // 9. Il CMS salva e ricarica 'details'.
    public function test_admin_saves_and_reloads_timeline_event_details(): void
    {
        $page = SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'is_active' => true,
            'content' => [
                'timeline' => [
                    ['year' => '1999', 'title' => 'Evento personalizzato', 'text' => 'Testo breve.'],
                ],
            ],
        ]);

        $editor = $this->editor();

        $payload = [
            'title' => 'Alan Turing',
            'hero_title' => 'Alan Turing',
            'hero_lead' => 'Lead di prova.',
            'intro_title' => 'Introduzione di prova',
            'intro_text' => 'Testo introduzione di prova.',
            'why_title' => 'Perché conta ancora',
            'why_text' => 'Testo perché conta ancora.',
            'final_title' => 'Prossima lettura',
            'final_text' => 'Testo finale di prova.',
            'is_active' => '1',
            'timeline' => [
                ['year' => '1999', 'title' => 'Evento personalizzato', 'text' => 'Testo breve.', 'details' => 'Approfondimento salvato dal CMS.'],
            ],
        ];

        $this->actingAs($editor)
            ->post(route('admin.turing.update'), $payload)
            ->assertRedirect(route('admin.turing'));

        $page->refresh();
        $this->assertSame('Approfondimento salvato dal CMS.', $page->content['timeline'][0]['details']);

        $this->actingAs($editor)
            ->get(route('admin.turing'))
            ->assertOk()
            ->assertSee('Approfondimento salvato dal CMS.', false);
    }

    // 12. Tutte le pagine pubbliche Turing continuano a renderizzare.
    public function test_all_public_turing_pages_still_render(): void
    {
        $this->get(route('turing'))->assertOk();
        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
        $this->get(route('turing.intelligence'))->assertOk();
    }

    public function test_events_without_details_key_at_all_do_not_error(): void
    {
        // Compatibilità con record esistenti privi del nuovo campo.
        $html = $this->renderTimeline([
            ['year' => '1936', 'title' => 'Evento legacy', 'text' => 'Testo.'],
        ]);

        $this->assertStringContainsString('Evento legacy', $html);
        $this->assertStringNotContainsString('data-sp-modal-target', $html);
    }

    // ---- Campi aggiunti in questa fase: curiosity, documents, related_links ----

    public function test_curiosity_alone_without_details_still_opens_a_modal(): void
    {
        // Un evento puo' avere una modale anche senza 'details': la sola
        // curiosita' (o documents/related_links, vedi test successivi) basta.
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con sola curiosità', 'text' => 'Testo breve.', 'curiosity' => 'Un aneddoto isolato.'],
        ]);

        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);
        $this->assertStringContainsString('sp-timeline__modal-curiosity-label', $html);
        $this->assertStringContainsString('Un aneddoto isolato.', $html);
        // Nessun blocco 'details' vuoto renderizzato insieme.
        $this->assertStringNotContainsString('sp-timeline__modal-details', $html);
    }

    public function test_documents_field_renders_a_link_list_in_the_modal(): void
    {
        $html = $this->renderTimeline([
            [
                'year' => '1936',
                'title' => 'Evento con documenti',
                'text' => 'Testo breve.',
                'documents' => [
                    ['label' => 'Fonte primaria di esempio', 'url' => 'https://example.org/fonte-primaria'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);
        $this->assertStringContainsString('sp-timeline__modal-documents', $html);
        $this->assertStringContainsString('Documenti e fonti', $html);
        $this->assertStringContainsString('href="https://example.org/fonte-primaria"', $html);
        $this->assertStringContainsString('Fonte primaria di esempio', $html);
        // I link a documenti esterni si aprono in una nuova scheda, senza
        // esporre la pagina di origine tramite window.opener.
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_documents_with_a_missing_url_or_label_are_silently_skipped(): void
    {
        $html = $this->renderTimeline([
            [
                'year' => '1936',
                'title' => 'Evento con documento incompleto',
                'text' => 'Testo breve.',
                'documents' => [
                    ['label' => 'Senza url'],
                    ['url' => 'https://example.org/senza-label'],
                ],
            ],
        ]);

        // Nessun documento valido: niente modale, niente sezione documenti.
        $this->assertStringNotContainsString('data-sp-modal-target', $html);
        $this->assertStringNotContainsString('sp-timeline__modal-documents', $html);
    }

    public function test_related_links_field_renders_internal_navigation_in_the_modal(): void
    {
        $html = $this->renderTimeline([
            [
                'year' => '1945',
                'title' => 'Evento con collegamenti correlati',
                'text' => 'Testo breve.',
                'related_links' => [
                    ['label' => 'Scopri l’eredità di Turing', 'url' => '/turing/legacy'],
                ],
            ],
        ]);

        $this->assertStringContainsString('sp-timeline__modal-related', $html);
        $this->assertStringContainsString('Continua nello Speciale', $html);
        $this->assertStringContainsString('href="/turing/legacy"', $html);
        $this->assertStringContainsString('Scopri l’eredità di Turing', $html);
    }

    public function test_all_new_fields_can_coexist_with_details_in_the_same_modal(): void
    {
        $html = $this->renderTimeline([
            [
                'year' => '1950',
                'title' => 'Evento completo',
                'text' => 'Testo breve.',
                'details' => 'Approfondimento esteso.',
                'curiosity' => 'Una curiosità.',
                'documents' => [['label' => 'Documento', 'url' => 'https://example.org/doc']],
                'related_links' => [['label' => 'Altro capitolo', 'url' => '/turing/legacy']],
            ],
        ]);

        $this->assertStringContainsString('sp-timeline__modal-details', $html);
        $this->assertStringContainsString('Approfondimento esteso.', $html);
        $this->assertStringContainsString('sp-timeline__modal-curiosity-label', $html);
        $this->assertStringContainsString('Una curiosità.', $html);
        $this->assertStringContainsString('sp-timeline__modal-documents', $html);
        $this->assertStringContainsString('sp-timeline__modal-related', $html);
        // Un solo trigger/modale per evento, non uno per campo popolato.
        $this->assertSame(1, substr_count($html, 'data-sp-modal-target="timeline-event-0"'));
    }

    public function test_card_with_modal_is_marked_for_the_stretched_click_area(): void
    {
        $html = $this->renderTimeline([
            ['year' => '1950', 'title' => 'Evento con modale', 'text' => 'Testo.', 'details' => 'Approfondimento.'],
        ]);

        $this->assertStringContainsString('sp-timeline__card--has-modal', $html);
    }

    public function test_event_link_and_modal_trigger_can_coexist_without_nested_interactive_elements(): void
    {
        $html = $this->renderTimeline([
            [
                'year' => '1950',
                'title' => 'Evento con link e modale',
                'text' => 'Testo.',
                'url' => '/turing/intelligence',
                'link_label' => 'Vai a Intelligence',
                'curiosity' => 'Una curiosità.',
            ],
        ]);

        $this->assertStringContainsString('href="/turing/intelligence"', $html);
        $this->assertStringContainsString('Vai a Intelligence', $html);
        $this->assertStringContainsString('data-sp-modal-target="timeline-event-0"', $html);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//button[ancestor::a]')->length);
        $this->assertSame(0, $xpath->query('//a[.//button]')->length);
    }
}
