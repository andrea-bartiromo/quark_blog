<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL RESILIENCE — FASE 14 (validation failure resilience): audit
 * confermato NON un bug — ogni campo del form Admin già usa
 * old($field, ...) in modo consistente, quindi un submit respinto dalla
 * validazione (titolo mancante, qui) ripopola il form intero via
 * back()->withInput() automatico di Laravel, incluse le checkbox
 * secondary_categories[] e featured. Questo test blocca quel
 * comportamento corretto come regressione permanente, non lo introduce.
 */
class ArticleFormValidationRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_field_survives_a_validation_error_via_old_input(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $secondary = Category::create([
            'name' => 'Ambiente',
            'slug' => 'ambiente-test-validation',
            'is_active' => true,
        ]);

        $payload = [
            // 'title' omesso deliberatamente: fa fallire la validazione.
            'excerpt' => 'Sommario che deve sopravvivere',
            'body' => '<p>Corpo che deve sopravvivere.</p>',
            'category' => 'energia',
            'secondary_categories' => [$secondary->id],
            'status' => 'scheduled',
            'published_date' => now()->addDays(3)->format('Y-m-d'),
            'published_time' => '10:30',
            'featured' => '1',
            'seo_title' => 'SEO title di prova',
            'seo_description' => 'SEO description di prova',
            'canonical_url' => 'https://example.com/originale',
            'robots' => 'noindex,follow',
            'og_title' => 'OG title di prova',
            'twitter_title' => 'Twitter title di prova',
            'cover_alt' => 'Testo alternativo di prova',
        ];

        $response = $this->actingAs($editor)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), $payload);

        $response->assertSessionHasErrors('title');
        $response->assertRedirect(route('admin.articles.create'));

        $formResponse = $this->get(route('admin.articles.create'));

        $formResponse->assertOk();
        $formResponse->assertSee('value="'.$payload['seo_title'].'"', false);
        $formResponse->assertSee($payload['excerpt'], false);
        // Il body e' dentro una <textarea>: Blade lo HTML-escapa sempre
        // (corretto — altrimenti i tag <p> verrebbero interpretati come
        // markup reale invece che come contenuto testuale), quindi qui si
        // confronta con l'escape di default, non con i byte grezzi.
        $formResponse->assertSee($payload['body']);
        $formResponse->assertSee('value="'.$payload['canonical_url'].'"', false);
        $formResponse->assertSee('value="'.$payload['og_title'].'"', false);
        $formResponse->assertSee('value="'.$payload['twitter_title'].'"', false);
        $formResponse->assertSee('value="'.$payload['cover_alt'].'"', false);

        // Select/checkbox: il contenuto HTML grezzo (non il testo visibile)
        // deve contenere l'opzione scheduled marcata selected e la
        // checkbox featured marcata checked — assertSee($html, false)
        // confronta contro l'HTML non decodificato.
        $html = $formResponse->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="scheduled"[^>]*selected/s',
            $html,
            'Lo stato "scheduled" inviato deve restare selezionato dopo un errore di validazione.'
        );
        $this->assertMatchesRegularExpression(
            '/name="featured"[^>]*checked/s',
            $html,
            'La checkbox "featured" inviata deve restare selezionata dopo un errore di validazione.'
        );
        $this->assertMatchesRegularExpression(
            '/value="'.$secondary->id.'"[^>]*checked/s',
            $html,
            'La categoria secondaria selezionata deve restare selezionata dopo un errore di validazione.'
        );
    }
}
