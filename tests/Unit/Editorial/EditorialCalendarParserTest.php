<?php

namespace Tests\Unit\Editorial;

use App\Services\Editorial\EditorialCalendarParser;
use Tests\TestCase;

class EditorialCalendarParserTest extends TestCase
{
    private function parser(): EditorialCalendarParser
    {
        return new EditorialCalendarParser;
    }

    // ── Formato base ──────────────────────────────────────────────────

    public function test_a_well_formed_line_is_parsed_into_a_single_entry(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Perché il cielo è nero?');

        $this->assertCount(1, $result->entries);
        $this->assertCount(0, $result->errors);
        $entry = $result->entries[0];
        $this->assertSame(1, $entry->position);
        $this->assertSame('2026-08-28', $entry->date->toDateString());
        $this->assertSame('Perché il cielo è nero?', $entry->title);
        $this->assertNull($entry->filone);
        $this->assertNull($entry->status);
    }

    public function test_multiple_entries_are_positioned_in_document_order(): void
    {
        $result = $this->parser()->parse(
            "28/08/2026 — Primo titolo\n29/08/2026 — Secondo titolo\n30/08/2026 — Terzo titolo\n"
        );

        $this->assertCount(3, $result->entries);
        $this->assertSame([1, 2, 3], array_map(fn ($e) => $e->position, $result->entries));
        $this->assertSame(['Primo titolo', 'Secondo titolo', 'Terzo titolo'], array_map(fn ($e) => $e->title, $result->entries));
    }

    // ── Tolleranza formato data ──────────────────────────────────────

    public function test_date_separators_slash_dash_and_dot_are_all_accepted(): void
    {
        $result = $this->parser()->parse("28/08/2026 — A\n28-08-2026 — B\n28.08.2026 — C\n");

        $this->assertCount(3, $result->entries);
        foreach ($result->entries as $entry) {
            $this->assertSame('2026-08-28', $entry->date->toDateString());
        }
    }

    public function test_non_zero_padded_dates_are_accepted(): void
    {
        $result = $this->parser()->parse('8/8/2026 — Titolo con data non imbottita');

        $this->assertCount(1, $result->entries);
        $this->assertSame('2026-08-08', $result->entries[0]->date->toDateString());
    }

    public function test_a_two_digit_year_is_interpreted_as_20xx(): void
    {
        $result = $this->parser()->parse('28/08/26 — Titolo con anno a due cifre');

        $this->assertCount(1, $result->entries);
        $this->assertSame('2026-08-28', $result->entries[0]->date->toDateString());
    }

    public function test_an_impossible_date_never_silently_rolls_over_and_produces_an_error(): void
    {
        $result = $this->parser()->parse('30/02/2026 — Data impossibile');

        $this->assertCount(0, $result->entries);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Data non valida', $result->errors[0]->reason);
    }

    public function test_an_out_of_range_month_produces_an_error(): void
    {
        $result = $this->parser()->parse('15/13/2026 — Mese fuori intervallo');

        $this->assertCount(0, $result->entries);
        $this->assertCount(1, $result->errors);
    }

    // ── Separatore data→titolo ───────────────────────────────────────

    public function test_em_dash_en_dash_double_hyphen_and_spaced_hyphen_separators_are_all_accepted(): void
    {
        $result = $this->parser()->parse("28/08/2026 — A\n28/08/2026 – B\n28/08/2026 -- C\n28/08/2026 - D\n");

        $this->assertCount(4, $result->entries);
        $this->assertSame(['A', 'B', 'C', 'D'], array_map(fn ($e) => $e->title, $result->entries));
    }

    public function test_a_date_followed_only_by_a_separator_produces_a_missing_title_error(): void
    {
        $result = $this->parser()->parse('28/08/2026 — ');

        $this->assertCount(0, $result->entries);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Titolo mancante', $result->errors[0]->reason);
    }

    public function test_a_date_with_no_title_at_all_produces_a_missing_title_error(): void
    {
        $result = $this->parser()->parse('28/08/2026');

        $this->assertCount(0, $result->entries);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Titolo mancante', $result->errors[0]->reason);
    }

    // ── Titoli con trattini interni (mai spezzati) ────────────────────

    public function test_a_single_hyphen_inside_the_title_is_never_treated_as_a_filone_separator(): void
    {
        $result = $this->parser()->parse('28/08/2026 — GPT-5 e il futuro del lavoro');

        $this->assertCount(1, $result->entries);
        $this->assertSame('GPT-5 e il futuro del lavoro', $result->entries[0]->title);
        $this->assertNull($result->entries[0]->filone);
    }

    // ── Filone (secondo segmento) ─────────────────────────────────────

    public function test_an_em_dash_separated_second_segment_is_extracted_as_filone(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo principale — Intelligenza Artificiale');

        $entry = $result->entries[0];
        $this->assertSame('Titolo principale', $entry->title);
        $this->assertSame('Intelligenza Artificiale', $entry->filone);
    }

    public function test_no_filone_segment_leaves_filone_null(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Solo il titolo');

        $this->assertNull($result->entries[0]->filone);
    }

    // ── Stato tra parentesi ────────────────────────────────────────

    public function test_a_trailing_bracketed_status_is_extracted_verbatim(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo pubblicato [pubblicato]');

        $entry = $result->entries[0];
        $this->assertSame('Titolo pubblicato', $entry->title);
        $this->assertSame('pubblicato', $entry->status);
    }

    public function test_a_trailing_parenthesized_status_is_extracted_verbatim(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo in bozza (bozza)');

        $entry = $result->entries[0];
        $this->assertSame('Titolo in bozza', $entry->title);
        $this->assertSame('bozza', $entry->status);
    }

    public function test_an_unrecognized_status_text_is_still_extracted_verbatim_never_discarded(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo [stato mai visto prima]');

        $this->assertSame('stato mai visto prima', $result->entries[0]->status);
    }

    public function test_status_and_filone_together_are_both_extracted_correctly(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo — Filone [pubblicato]');

        $entry = $result->entries[0];
        $this->assertSame('Titolo', $entry->title);
        $this->assertSame('Filone', $entry->filone);
        $this->assertSame('pubblicato', $entry->status);
    }

    // ── Sezioni (intestazioni) ─────────────────────────────────────

    public function test_a_markdown_heading_sets_the_section_for_subsequent_entries(): void
    {
        $result = $this->parser()->parse("## Agosto 2026\n28/08/2026 — Titolo di agosto\n");

        $this->assertSame('Agosto 2026', $result->entries[0]->section);
    }

    public function test_a_bold_only_line_sets_the_section_for_subsequent_entries(): void
    {
        $result = $this->parser()->parse("**Settembre 2026**\n01/09/2026 — Titolo di settembre\n");

        $this->assertSame('Settembre 2026', $result->entries[0]->section);
    }

    public function test_a_new_heading_replaces_the_current_section(): void
    {
        $result = $this->parser()->parse(
            "## Agosto 2026\n28/08/2026 — A\n## Settembre 2026\n01/09/2026 — B\n"
        );

        $this->assertSame('Agosto 2026', $result->entries[0]->section);
        $this->assertSame('Settembre 2026', $result->entries[1]->section);
    }

    public function test_an_entry_before_any_heading_has_a_null_section(): void
    {
        $result = $this->parser()->parse('28/08/2026 — Titolo senza sezione');

        $this->assertNull($result->entries[0]->section);
    }

    // ── Marcatori di lista ────────────────────────────────────────────

    public function test_a_leading_dash_list_marker_is_stripped(): void
    {
        $result = $this->parser()->parse('- 28/08/2026 — Titolo in lista puntata');

        $this->assertCount(1, $result->entries);
        $this->assertSame('Titolo in lista puntata', $result->entries[0]->title);
    }

    public function test_a_leading_numbered_list_marker_is_stripped(): void
    {
        $result = $this->parser()->parse('12. 28/08/2026 — Titolo in lista numerata');

        $this->assertCount(1, $result->entries);
        $this->assertSame('Titolo in lista numerata', $result->entries[0]->title);
    }

    // ── Prosa e righe ignorate ─────────────────────────────────────

    public function test_prose_lines_not_starting_with_a_date_are_silently_ignored(): void
    {
        $result = $this->parser()->parse("Questo è un paragrafo introduttivo senza alcuna data.\n28/08/2026 — Titolo valido\n");

        $this->assertCount(1, $result->entries);
        $this->assertCount(0, $result->errors);
    }

    public function test_blank_lines_and_horizontal_rules_are_ignored(): void
    {
        $result = $this->parser()->parse("28/08/2026 — Titolo A\n\n---\n\n29/08/2026 — Titolo B\n");

        $this->assertCount(2, $result->entries);
    }

    public function test_an_empty_document_produces_an_empty_result(): void
    {
        $result = $this->parser()->parse('');

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($result->hasErrors());
    }

    // ── Errori e voci valide nello stesso documento ───────────────────

    public function test_valid_entries_and_parse_errors_can_coexist_in_the_same_document(): void
    {
        $result = $this->parser()->parse(
            "28/08/2026 — Titolo valido\n32/13/2026 — Data non valida\n30/08/2026\n01/09/2026 — Un altro titolo valido\n"
        );

        $this->assertCount(2, $result->entries);
        $this->assertCount(2, $result->errors);
        $this->assertTrue($result->hasErrors());
    }
}
