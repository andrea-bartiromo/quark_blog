<?php

namespace Tests\Unit\InternalLinking;

use App\Models\Article;
use App\Models\User;
use App\Services\InternalLinking\InternalLinkTemporalEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Internal Linking V2.1 — copertura dedicata della policy pura e
 * deterministica isTargetSafeForSource() (nessuna scrittura, nessuna
 * chiamata a servizi esterni): ogni caso della missione "target
 * programmati temporalmente sicuri" a livello di sola regola, indipendente
 * dal motore dei suggerimenti e dall'audit (che la riusano entrambi, vedi
 * ArticleLinkSuggestionServiceTest e InternalLinkAuditCommandTest per la
 * stessa regola esercitata end-to-end).
 */
class InternalLinkTemporalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private InternalLinkTemporalEligibility $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new InternalLinkTemporalEligibility;
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid('', true),
            'excerpt' => 'Un breve sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    // 1. source scheduled 19/08, target scheduled 12/08 => eleggibile
    public function test_a_later_scheduled_source_can_target_an_earlier_scheduled_article(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-12 15:30:00']);

        $this->assertTrue($this->policy->isTargetSafeForSource($source, $target));
    }

    /**
     * Caso reale equivalente alla missione (#13/#14 "Test di Turing"), con
     * factory/testo generico invece di ID hardcoded.
     */
    public function test_the_real_world_turing_articles_scenario(): void
    {
        $earlierArticle = $this->article([
            'title' => 'Il Test di Turing spiegato davvero: può una macchina pensare?',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-12 15:30:00',
        ]);

        $laterArticle = $this->article([
            'title' => 'Il Test di Turing ha ancora senso nel 2026? Limiti, critiche e nuove prospettive.',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-19 15:30:00',
        ]);

        // #14 (19/08) -> #13 (12/08) DEVE poter essere considerato sicuro.
        $this->assertTrue($this->policy->isTargetSafeForSource($laterArticle, $earlierArticle));

        // Viceversa, #13 (12/08) -> #14 (19/08) NON deve essere proposto.
        $this->assertFalse($this->policy->isTargetSafeForSource($earlierArticle, $laterArticle));
    }

    // 2. source scheduled 12/08, target scheduled 19/08 => escluso
    public function test_an_earlier_scheduled_source_cannot_target_a_later_scheduled_article(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-12 15:30:00']);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    // 3. stesso istante esatto => escluso (mai dipendere dall'ordine di esecuzione dello scheduler)
    public function test_the_exact_same_scheduled_instant_is_never_eligible(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    // 4. stesso giorno, orario realmente precedente => eleggibile (confronto reale, non sul solo giorno)
    public function test_an_earlier_time_on_the_same_day_is_eligible(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 14:00:00']);

        $this->assertTrue($this->policy->isTargetSafeForSource($source, $target));
    }

    /**
     * Contropartita del caso precedente: un orario realmente SUCCESSIVO
     * nello stesso giorno non deve mai risultare eleggibile — prova che il
     * confronto è su un istante reale (data+ora), non su un semplice
     * confronto di solo giorno.
     */
    public function test_a_later_time_on_the_same_day_is_never_eligible(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 16:00:00']);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    // 5. source published, target scheduled => escluso
    public function test_a_published_source_can_never_target_a_scheduled_article(): void
    {
        $source = $this->article(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()]);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addWeek()]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    // 6. source draft, target scheduled precedente => escluso
    public function test_a_draft_source_can_never_target_a_scheduled_article(): void
    {
        $source = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    public function test_a_review_source_can_never_target_a_scheduled_article(): void
    {
        $source = $this->article(['status' => Article::STATUS_REVIEW, 'published_at' => null]);
        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $target));
    }

    // 7. target published => comportamento V2 invariato, sempre eleggibile
    public function test_a_published_target_is_always_eligible_regardless_of_source_status(): void
    {
        $target = $this->article(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subDay()]);

        foreach ([Article::STATUS_DRAFT, Article::STATUS_REVIEW, Article::STATUS_SCHEDULED, Article::STATUS_PUBLISHED] as $sourceStatus) {
            $source = $this->article([
                'status' => $sourceStatus,
                'published_at' => in_array($sourceStatus, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true) ? null : now()->addDay(),
            ]);

            $this->assertTrue(
                $this->policy->isTargetSafeForSource($source, $target),
                "Un target già pubblicato deve restare sempre eleggibile con source status={$sourceStatus}."
            );
        }
    }

    // 8. target draft/review => mai eleggibile, indipendentemente dalla source
    public function test_a_draft_or_review_target_is_never_eligible_even_from_a_scheduled_source(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addMonth()]);

        $draftTarget = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $reviewTarget = $this->article(['status' => Article::STATUS_REVIEW, 'published_at' => null]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source, $draftTarget));
        $this->assertFalse($this->policy->isTargetSafeForSource($source, $reviewTarget));
    }

    /**
     * Difensivo: un target 'scheduled' con published_at nullo (stato
     * incoerente, mai raggiungibile tramite il form — vedi
     * Article::booted() — ma non impossibile a livello di dati) non deve
     * mai risultare eleggibile.
     */
    public function test_a_scheduled_target_with_a_null_published_at_is_never_eligible(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addMonth()]);

        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        $target->forceFill(['published_at' => null])->saveQuietly();

        $this->assertFalse($this->policy->isTargetSafeForSource($source->fresh(), $target->fresh()));
    }

    /**
     * Stesso ragionamento sul lato source: una source 'scheduled' con
     * published_at nullo (stato incoerente) non deve mai concedere
     * l'eccezione.
     */
    public function test_a_scheduled_source_with_a_null_published_at_never_grants_the_exception(): void
    {
        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        $source->forceFill(['published_at' => null])->saveQuietly();

        $target = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->subDay()]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source->fresh(), $target));
    }

    /**
     * Il confronto deve avvenire su un istante reale (Carbon ->lt()), mai
     * su una stringa o su una data senza ora — usa lo stesso metodo
     * dell'app per convertire un orario redazionale (Europe/Rome) in
     * published_at (Article::scheduledAtFromEditorialInput(), l'unico
     * punto del codice che esegue questa conversione): due orari
     * redazionali sullo stesso giorno restano confrontati correttamente
     * come istanti UTC reali, non come stringhe.
     */
    public function test_the_comparison_uses_real_editorial_timezone_aware_instants(): void
    {
        $sourceInstant = Article::scheduledAtFromEditorialInput('2026-08-19', '15:30');
        $laterOnTheSameDay = Article::scheduledAtFromEditorialInput('2026-08-19', '16:00');
        $earlierOnTheSameDay = Article::scheduledAtFromEditorialInput('2026-08-19', '14:00');

        $source = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => $sourceInstant]);
        $laterTarget = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => $laterOnTheSameDay]);
        $earlierTarget = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => $earlierOnTheSameDay]);

        $this->assertFalse($this->policy->isTargetSafeForSource($source->fresh(), $laterTarget->fresh()));
        $this->assertTrue($this->policy->isTargetSafeForSource($source->fresh(), $earlierTarget->fresh()));
    }

    public function test_is_publicly_visible_is_true_only_for_published_with_a_past_or_present_published_at(): void
    {
        $published = $this->article(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subMinute()]);
        $publishedFuture = $this->article(['status' => Article::STATUS_PUBLISHED, 'published_at' => now()->addDay()]);
        $scheduled = $this->article(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        $draft = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $this->assertTrue($this->policy->isPubliclyVisible($published));
        $this->assertFalse($this->policy->isPubliclyVisible($publishedFuture));
        $this->assertFalse($this->policy->isPubliclyVisible($scheduled));
        $this->assertFalse($this->policy->isPubliclyVisible($draft));
    }
}
