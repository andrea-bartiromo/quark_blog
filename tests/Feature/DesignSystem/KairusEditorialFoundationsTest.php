<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Missione 15 — Kairus Editorial Foundations V1.
 *
 * Certifica le fondamenta condivise (CSS isolato + componenti Blade) senza
 * che nessuna pagina pubblica esistente le monti ancora: ogni componente è
 * renderizzato qui direttamente via Blade::render(), non attraverso una
 * rotta applicativa reale.
 */
class KairusEditorialFoundationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /chi-siamo è una pagina statica, senza dipendenze da dati seed, che
     * usa layouts.app → layouts.partials.head — lo stesso partial in cui
     * editorial-system.css è stato aggiunto (Missione 03).
     */
    public function test_editorial_css_is_included_in_the_public_layout(): void
    {
        $response = $this->get('/chi-siamo');

        $response->assertOk();
        $response->assertSee('css/editorial-system.css', false);
    }

    public function test_every_component_renders_with_minimal_props(): void
    {
        $renders = [
            'page-header' => '<x-kairus.page-header title="Titolo di prova" />',
            'section-heading' => '<x-kairus.section-heading title="Titolo di prova" />',
            'article-meta' => '<x-kairus.article-meta />',
            'image-frame' => '<x-kairus.image-frame><img src="/placeholder.jpg" alt=""></x-kairus.image-frame>',
            'article-card' => '<x-kairus.article-card href="/articolo" title="Titolo di prova" />',
            'path-card' => '<x-kairus.path-card href="/percorso" title="Titolo di prova" />',
            'path-step' => '<x-kairus.path-step number="1" label="Tappa 1" title="Titolo di prova" href="/percorso/1" />',
            'trust-panel' => '<x-kairus.trust-panel />',
            'empty-state' => '<x-kairus.empty-state title="Titolo di prova" />',
            'form-shell' => '<x-kairus.form-shell title="Titolo di prova"><x-slot:form></x-slot:form></x-kairus.form-shell>',
        ];

        foreach ($renders as $name => $template) {
            $html = Blade::render($template);

            $this->assertIsString($html, "Il componente {$name} non ha renderizzato una stringa.");
        }
    }

    public function test_page_header_produces_exactly_one_h1(): void
    {
        $html = Blade::render(
            '<x-kairus.page-header eyebrow="Rubrica" title="Onde gravitazionali" lead="Un\'introduzione al fenomeno." tone="sage" />'
        );

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Onde gravitazionali', $html);
    }

    public function test_page_header_title_and_lead_are_never_truncated_by_css_line_clamp(): void
    {
        $html = Blade::render('<x-kairus.page-header title="Titolo" lead="Testo lungo." />');

        $this->assertStringNotContainsString('line-clamp', $html);
        $this->assertStringNotContainsString('text-overflow', $html);
    }

    public function test_article_meta_omits_fields_that_were_not_passed(): void
    {
        $html = Blade::render('<x-kairus.article-meta :author="$author" />', [
            'author' => 'Redazione',
        ]);

        $this->assertStringContainsString('Redazione', $html);
        $this->assertStringNotContainsString('<time', $html);
        $this->assertStringNotContainsString('minuto di lettura', $html);
        $this->assertStringNotContainsString('minuti di lettura', $html);
    }

    public function test_article_meta_renders_nothing_when_no_data_is_passed(): void
    {
        $html = trim(Blade::render('<x-kairus.article-meta />'));

        $this->assertSame('', $html);
    }

    public function test_article_card_has_exactly_one_primary_interactive_destination(): void
    {
        $html = Blade::render(
            '<x-kairus.article-card href="/articolo/onde-gravitazionali" title="Onde gravitazionali" excerpt="Un fenomeno osservato di nuovo." category-label="Fisica" />'
        );

        $this->assertSame(1, substr_count($html, '<a '));
        $this->assertStringContainsString('href="/articolo/onde-gravitazionali"', $html);
    }

    public function test_article_card_title_is_never_truncated_with_line_clamp(): void
    {
        $html = Blade::render('<x-kairus.article-card href="/a" title="Titolo" />');

        $this->assertStringNotContainsString('line-clamp', $html);
    }

    public function test_article_card_excerpt_can_be_omitted(): void
    {
        $html = Blade::render('<x-kairus.article-card href="/a" title="Titolo" />');

        $this->assertStringNotContainsString('kairus-article-card__excerpt', $html);
    }

    public function test_image_frame_adds_no_loading_attribute(): void
    {
        $html = Blade::render(
            '<x-kairus.image-frame ratio="hero" caption="Didascalia" credit="Credito"><img src="/foto.jpg" alt="Descrizione" loading="lazy"></x-kairus.image-frame>'
        );

        // L'unico "loading=" ammesso è quello scritto dal chiamante
        // sull'immagine reale (nel fixture qui sopra): il componente stesso
        // non deve aggiungerne un secondo né uno proprio.
        $this->assertSame(1, substr_count($html, 'loading='));
    }

    public function test_empty_state_renders_no_action_without_the_action_slot(): void
    {
        $html = Blade::render('<x-kairus.empty-state title="Nessun risultato" message="Prova un\'altra ricerca." icon="search" />');

        $this->assertStringNotContainsString('kairus-empty-state__action', $html);
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_empty_state_renders_the_action_slot_when_provided(): void
    {
        $html = Blade::render(
            '<x-kairus.empty-state title="Nessun risultato"><x-slot:action><a href="/notizie">Torna alle notizie</a></x-slot:action></x-kairus.empty-state>'
        );

        $this->assertStringContainsString('kairus-empty-state__action', $html);
        $this->assertStringContainsString('Torna alle notizie', $html);
    }

    public function test_trust_panel_renders_only_the_slots_that_were_passed(): void
    {
        $html = Blade::render(
            '<x-kairus.trust-panel><x-slot:sources>Fonte primaria citata.</x-slot:sources></x-kairus.trust-panel>'
        );

        $this->assertStringContainsString('Fonte primaria citata.', $html);
        $this->assertStringNotContainsString('Rettifiche', $html);
        $this->assertStringNotContainsString('Autore', $html);
    }

    public function test_trust_panel_renders_nothing_without_any_slot(): void
    {
        $html = trim(Blade::render('<x-kairus.trust-panel />'));

        $this->assertSame('', $html);
    }

    public function test_form_shell_never_renders_its_own_form_tag(): void
    {
        $html = Blade::render(
            '<x-kairus.form-shell title="Iscriviti"><x-slot:form><form method="POST" action="/newsletter"><input type="email" name="email"></form></x-slot:form></x-kairus.form-shell>'
        );

        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertStringContainsString('action="/newsletter"', $html);
    }

    /**
     * Le classi/variabili del sistema sono deliberatamente prefissate
     * "kairus-" (minuscolo, per requisito delle Missioni 02/14): non sono
     * ciò che questo test cerca. Cerca invece "Kairus" con la maiuscola —
     * la forma con cui il nome comparirebbe come testo, mai come prefisso
     * di classe — nell'HTML realmente reso da ogni componente con props
     * minime, dove nessun valore fornito dal test stesso contiene quella
     * parola.
     */
    public function test_no_component_renders_hardcoded_kairus_identity_text(): void
    {
        $renders = [
            'page-header' => '<x-kairus.page-header eyebrow="Rubrica" title="Titolo" lead="Testo." />',
            'section-heading' => '<x-kairus.section-heading eyebrow="Rubrica" title="Titolo" description="Testo." />',
            'article-meta' => '<x-kairus.article-meta :author="$author" category-label="Categoria" />',
            'image-frame' => '<x-kairus.image-frame caption="Didascalia" credit="Credito"><img src="/x.jpg" alt=""></x-kairus.image-frame>',
            'article-card' => '<x-kairus.article-card href="/a" title="Titolo" excerpt="Testo." category-label="Categoria" />',
            'path-card' => '<x-kairus.path-card href="/p" title="Titolo" description="Testo." cta="Continua" />',
            'path-step' => '<x-kairus.path-step number="1" label="Tappa" category-label="Categoria" title="Titolo" description="Testo." href="/p/1" state="current" />',
            'trust-panel' => '<x-kairus.trust-panel><x-slot:sources>Fonte.</x-slot:sources><x-slot:updated>Data.</x-slot:updated><x-slot:corrections>Nessuna.</x-slot:corrections><x-slot:author>Autore.</x-slot:author></x-kairus.trust-panel>',
            'empty-state' => '<x-kairus.empty-state title="Titolo" message="Testo." icon="error" />',
            'form-shell' => '<x-kairus.form-shell title="Titolo" lead="Testo." status="success"><x-slot:form></x-slot:form><x-slot:aside>Nota.</x-slot:aside></x-kairus.form-shell>',
        ];

        foreach ($renders as $name => $template) {
            $html = Blade::render($template, ['author' => 'Redazione']);

            $this->assertStringNotContainsString(
                'Kairus',
                $html,
                "Il componente {$name} renderizza il testo \"Kairus\" hardcoded."
            );
        }
    }
}
