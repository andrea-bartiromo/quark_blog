<?php

namespace Tests\Unit\Editorial;

use App\Models\Article;
use App\Services\Editorial\EditorialCalendarEntry;
use App\Services\Editorial\EditorialCalendarMatchingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EditorialCalendarMatchingServiceTest extends TestCase
{
    private function service(): EditorialCalendarMatchingService
    {
        return new EditorialCalendarMatchingService;
    }

    private function article(int $id, string $title): Article
    {
        return (new Article)->forceFill(['id' => $id, 'title' => $title]);
    }

    private function entry(string $title, int $position = 1): EditorialCalendarEntry
    {
        return new EditorialCalendarEntry(
            position: $position,
            date: Carbon::parse('2026-08-28'),
            title: $title,
            filone: null,
            status: null,
            section: null,
            lineNumber: 1,
            rawLine: "28/08/2026 — {$title}",
        );
    }

    /** @param  list<Article>  $articles */
    private function pool(array $articles): Collection
    {
        return collect($articles);
    }

    // ── Match esatto ──────────────────────────────────────────────────

    public function test_an_identical_title_is_an_exact_match(): void
    {
        $pool = $this->pool([$this->article(1, 'Perché il cielo è nero?')]);

        $match = $this->service()->matchEntry($this->entry('Perché il cielo è nero?'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_EXACT, $match->matchType);
        $this->assertSame(1, $match->article->id);
        $this->assertTrue($match->isSafeToAutoLink());
    }

    public function test_two_articles_with_the_exact_same_title_are_ambiguous(): void
    {
        $pool = $this->pool([
            $this->article(1, 'Titolo duplicato'),
            $this->article(2, 'Titolo duplicato'),
        ]);

        $match = $this->service()->matchEntry($this->entry('Titolo duplicato'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_AMBIGUOUS, $match->matchType);
        $this->assertNull($match->article);
        $this->assertCount(2, $match->candidates);
        $this->assertFalse($match->isSafeToAutoLink());
    }

    // ── Match normalizzato ────────────────────────────────────────────

    public function test_case_difference_is_a_normalized_match(): void
    {
        $pool = $this->pool([$this->article(1, 'perché il cielo è nero?')]);

        $match = $this->service()->matchEntry($this->entry('Perché il cielo è nero?'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NORMALIZED, $match->matchType);
        $this->assertTrue($match->isSafeToAutoLink());
    }

    public function test_curly_apostrophe_vs_straight_apostrophe_is_a_normalized_match(): void
    {
        $pool = $this->pool([$this->article(1, "L'intelligenza artificiale e il futuro dell'uomo")]);

        $match = $this->service()->matchEntry(
            $this->entry("L\u{2019}intelligenza artificiale e il futuro dell\u{2019}uomo"),
            $pool,
            collect()
        );

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NORMALIZED, $match->matchType);
    }

    public function test_trailing_punctuation_difference_is_a_normalized_match(): void
    {
        $pool = $this->pool([$this->article(1, 'Perché il cielo è nero')]);

        $match = $this->service()->matchEntry($this->entry('Perché il cielo è nero?'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NORMALIZED, $match->matchType);
    }

    public function test_extra_internal_whitespace_is_a_normalized_match(): void
    {
        $pool = $this->pool([$this->article(1, 'Titolo con spazi normali')]);

        $match = $this->service()->matchEntry($this->entry('Titolo  con   spazi normali'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NORMALIZED, $match->matchType);
    }

    public function test_two_articles_with_the_same_normalized_title_are_ambiguous(): void
    {
        $pool = $this->pool([
            $this->article(1, 'Titolo Duplicato'),
            $this->article(2, 'titolo duplicato'),
        ]);

        $match = $this->service()->matchEntry($this->entry('TITOLO DUPLICATO'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_AMBIGUOUS, $match->matchType);
        $this->assertFalse($match->isSafeToAutoLink());
    }

    // ── Match ambiguo (titoli vicini, mai applicato in automatico) ───

    public function test_a_slightly_reworded_title_is_ambiguous_not_auto_applied(): void
    {
        $pool = $this->pool([$this->article(1, 'GPT-5 e il futuro del lavoro in Italia')]);

        $match = $this->service()->matchEntry(
            $this->entry('GPT-5 e il futuro del lavoro: quali professioni sopravvivono'),
            $pool,
            collect()
        );

        $this->assertNotSame(EditorialCalendarMatchingService::MATCH_EXACT, $match->matchType);
        $this->assertNotSame(EditorialCalendarMatchingService::MATCH_NORMALIZED, $match->matchType);
        $this->assertFalse($match->isSafeToAutoLink());
    }

    public function test_ambiguous_match_never_reports_a_single_authoritative_article(): void
    {
        $pool = $this->pool([$this->article(1, 'Un titolo abbastanza simile ma diverso')]);

        // Similarità artificialmente alta ma non identica dopo normalizzazione.
        $match = $this->service()->matchEntry(
            $this->entry('Un titolo abbastanza simile ma differente'),
            $pool,
            collect()
        );

        if ($match->matchType === EditorialCalendarMatchingService::MATCH_AMBIGUOUS) {
            $this->assertNull($match->article);
            $this->assertFalse($match->isSafeToAutoLink());
        } else {
            $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $match->matchType);
        }
    }

    public function test_a_handful_of_shared_words_is_never_enough_for_a_match_on_its_own(): void
    {
        // Requisito esplicito della missione: mai collegare per poche
        // parole condivise, anche se sono parole "importanti".
        $pool = $this->pool([$this->article(1, 'Il futuro del lavoro secondo gli economisti italiani')]);

        $match = $this->service()->matchEntry(
            $this->entry('Il futuro della sanità pubblica in Europa'),
            $pool,
            collect()
        );

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $match->matchType);
    }

    // ── Nessun match ──────────────────────────────────────────────────

    public function test_a_completely_unrelated_title_is_no_match(): void
    {
        $pool = $this->pool([$this->article(1, 'Un argomento completamente diverso')]);

        $match = $this->service()->matchEntry($this->entry('Perché il cielo è nero?'), $pool, collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $match->matchType);
        $this->assertNull($match->article);
        $this->assertFalse($match->isSafeToAutoLink());
    }

    public function test_an_empty_article_pool_is_always_no_match(): void
    {
        $match = $this->service()->matchEntry($this->entry('Qualunque titolo'), $this->pool([]), collect());

        $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $match->matchType);
    }

    // ── Stato di collegamento ──────────────────────────────────────────

    public function test_already_linked_flag_is_true_when_the_matched_article_is_linked(): void
    {
        $pool = $this->pool([$this->article(1, 'Titolo collegato')]);

        $match = $this->service()->matchEntry($this->entry('Titolo collegato'), $pool, collect([1, 2, 3]));

        $this->assertTrue($match->alreadyLinkedToProject);
    }

    public function test_already_linked_flag_is_false_when_the_matched_article_is_not_linked(): void
    {
        $pool = $this->pool([$this->article(1, 'Titolo non collegato')]);

        $match = $this->service()->matchEntry($this->entry('Titolo non collegato'), $pool, collect([99]));

        $this->assertFalse($match->alreadyLinkedToProject);
    }

    public function test_already_linked_flag_is_false_when_there_is_no_matched_article(): void
    {
        $match = $this->service()->matchEntry($this->entry('Titolo senza match'), $this->pool([]), collect([1]));

        $this->assertFalse($match->alreadyLinkedToProject);
    }

    // ── matchAll ─────────────────────────────────────────────────────

    public function test_match_all_matches_every_entry_independently(): void
    {
        $pool = $this->pool([
            $this->article(1, 'Titolo A'),
            $this->article(2, 'Titolo B'),
        ]);

        $entries = [$this->entry('Titolo A', 1), $this->entry('Titolo Z', 2)];

        $matches = $this->service()->matchAll($entries, $pool, collect());

        $this->assertCount(2, $matches);
        $this->assertSame(EditorialCalendarMatchingService::MATCH_EXACT, $matches[0]->matchType);
        $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $matches[1]->matchType);
    }

    /**
     * Regressione Codex #1 (P1): due voci con lo stesso titolo che
     * risolverebbero, prese singolarmente, sullo stesso unico articolo
     * devono diventare entrambe ambigue in matchAll() — mai due match
     * "sicuri" per lo stesso articolo, che romperebbero il vincolo di
     * unicità del collegamento alla prima applicazione automatica.
     */
    public function test_match_all_demotes_two_entries_that_would_resolve_to_the_same_article(): void
    {
        $pool = $this->pool([$this->article(1, 'Titolo ripetuto')]);
        $entries = [$this->entry('Titolo ripetuto', 1), $this->entry('Titolo ripetuto', 2)];

        $matches = $this->service()->matchAll($entries, $pool, collect());

        $this->assertCount(2, $matches);
        foreach ($matches as $match) {
            $this->assertSame(EditorialCalendarMatchingService::MATCH_AMBIGUOUS, $match->matchType);
            $this->assertNull($match->article);
            $this->assertFalse($match->isSafeToAutoLink());
        }
    }

    public function test_match_all_does_not_demote_entries_resolving_to_different_articles(): void
    {
        $pool = $this->pool([
            $this->article(1, 'Titolo A'),
            $this->article(2, 'Titolo B'),
        ]);
        $entries = [$this->entry('Titolo A', 1), $this->entry('Titolo B', 2)];

        $matches = $this->service()->matchAll($entries, $pool, collect());

        $this->assertTrue($matches[0]->isSafeToAutoLink());
        $this->assertTrue($matches[1]->isSafeToAutoLink());
    }

    // ── normalizeTitle() ─────────────────────────────────────────────

    public function test_normalize_title_is_idempotent(): void
    {
        $service = $this->service();
        $normalized = $service->normalizeTitle("L'Intelligenza Artificiale — Spiegata Bene?");

        $this->assertSame($normalized, $service->normalizeTitle($normalized));
    }
}
