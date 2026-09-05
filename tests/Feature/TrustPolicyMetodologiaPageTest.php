<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust Layer V1 — pagina statica pubblica "Metodologia" (B-19/B-22).
 * Nessuna migration, nessun dato dinamico: verifica solo che la pagina
 * risponda, sia correttamente instradata/indicizzata e collegata dal
 * footer, senza duplicare il contenuto di "Correzioni" (già esistente
 * come rettifiche.blade.php, non toccata da questa missione).
 */
class TrustPolicyMetodologiaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_metodologia_page_is_reachable_and_has_seo_basics(): void
    {
        $response = $this->get('/metodologia');

        $response->assertOk();
        $response->assertSee('<title>Metodologia — Kairus</title>', false);
        $response->assertSee('<link rel="canonical" href="'.route('metodologia').'">', false);
        $response->assertSee('name="description"', false);
    }

    public function test_metodologia_page_has_a_single_h1_and_links_to_correzioni(): void
    {
        $response = $this->get('/metodologia');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        $response->assertSee('href="'.route('rettifiche').'"', false);
        $response->assertSee('href="'.route('chi-siamo').'"', false);
    }

    public function test_metodologia_is_linked_from_the_footer(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.url('/metodologia').'"', false);
    }

    public function test_metodologia_is_included_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<loc>'.url('/metodologia').'</loc>', false);
    }

    public function test_metodologia_does_not_duplicate_the_correzioni_policy_page(): void
    {
        // Le due pagine restano distinte: rettifiche.blade.php e'
        // preesistente e non toccata da questa missione, "Metodologia"
        // rimanda ad essa invece di ripeterne il contenuto.
        $metodologia = $this->get('/metodologia')->getContent();
        $rettifiche = $this->get('/rettifiche')->getContent();

        $this->assertNotSame($metodologia, $rettifiche);
    }
}
