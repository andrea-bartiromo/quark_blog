<?php

namespace Tests\Unit\EditorialSources;

use App\Services\EditorialSources\SourceReferenceNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * EDITORIAL TRUST (Missione 26) — normalizzazione URL/DOI.
 *
 * È il punto in cui un `javascript:` diventerebbe un href pubblico, quindi
 * i casi di rifiuto sono testati almeno quanto quelli di accettazione.
 */
class SourceReferenceNormalizerTest extends TestCase
{
    private SourceReferenceNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new SourceReferenceNormalizer;
    }

    public function test_accepts_an_absolute_https_url(): void
    {
        $this->assertSame(
            'https://www.esa.int/comunicato',
            $this->normalizer->normalizeUrl('  https://www.esa.int/comunicato  ')
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'javascript uppercase' => ['JavaScript:alert(1)'],
            'data scheme' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript scheme' => ['vbscript:msgbox(1)'],
            'file scheme' => ['file:///etc/passwd'],
            'blob scheme' => ['blob:https://example.com/uuid'],
            // Un browser ignora i caratteri di controllo dentro lo schema:
            // questa stringa ESEGUE se finisce in un href.
            'newline inside scheme' => ["java\nscript:alert(1)"],
            'tab inside scheme' => ["java\tscript:alert(1)"],
            'null byte inside scheme' => ["java\0script:alert(1)"],
            'protocol relative' => ['//evil.example.com/pagina'],
            'relative path' => ['/fonte-interna'],
            'plain http rejected on write' => ['http://www.esa.int/comunicato'],
            'https without host' => ['https://'],
            'empty' => [''],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_urls_that_are_not_absolute_https(string $value): void
    {
        $this->assertNull($this->normalizer->normalizeUrl($value));
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_urls_are_never_renderable_as_links(string $value): void
    {
        // http è l'unica eccezione voluta fra i valori sopra: è rifiutato in
        // scrittura ma resta linkabile in lettura, per non trasformare una
        // eventuale riga legacy importata in un link muto.
        if (str_starts_with($value, 'http://')) {
            $this->assertTrue($this->normalizer->isRenderableUrl($value));

            return;
        }

        $this->assertFalse($this->normalizer->isRenderableUrl($value));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function doiVariants(): array
    {
        return [
            'bare' => ['10.1038/s41586-024-07123-4', '10.1038/s41586-024-07123-4'],
            'doi prefix' => ['doi:10.1038/s41586-024-07123-4', '10.1038/s41586-024-07123-4'],
            'doi prefix uppercase' => ['DOI:10.1038/s41586-024-07123-4', '10.1038/s41586-024-07123-4'],
            'resolver https' => ['https://doi.org/10.1038/s41586-024-07123-4', '10.1038/s41586-024-07123-4'],
            'resolver dx http' => ['http://dx.doi.org/10.1038/s41586-024-07123-4', '10.1038/s41586-024-07123-4'],
            'surrounding whitespace' => ["  10.1038/s41586-024-07123-4\n", '10.1038/s41586-024-07123-4'],
        ];
    }

    #[DataProvider('doiVariants')]
    public function test_normalizes_every_accepted_doi_form_to_the_bare_identifier(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalizeDoi($raw));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidDois(): array
    {
        return [
            'missing registrant prefix' => ['11.1038/abcd'],
            'too few registrant digits' => ['10.123/abcd'],
            'no suffix' => ['10.1038/'],
            'no slash' => ['10.1038'],
            'internal space' => ['10.1038/s41586 07123'],
            'javascript disguised as doi' => ['javascript:10.1038/x'],
            'plain url' => ['https://www.nature.com/articles/x'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidDois')]
    public function test_rejects_values_that_are_not_dois(string $value): void
    {
        $this->assertNull($this->normalizer->normalizeDoi($value));
    }

    public function test_doi_url_encodes_only_what_would_break_the_link(): void
    {
        // Lo slash gerarchico interno resta uno slash; un carattere che
        // altrimenti inizierebbe una query string viene codificato.
        $this->assertSame(
            'https://doi.org/10.1038/a/b',
            $this->normalizer->doiUrl('10.1038/a/b')
        );

        $this->assertSame(
            'https://doi.org/10.1038/a%3Fb%23c',
            $this->normalizer->doiUrl('10.1038/a?b#c')
        );
    }

    public function test_duplicate_key_ignores_differences_that_do_not_change_the_destination(): void
    {
        $first = $this->normalizer->duplicateKey('https://WWW.Esa.int/comunicato/', null);
        $second = $this->normalizer->duplicateKey('https://www.esa.int/comunicato', null);

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
    }

    public function test_duplicate_key_prefers_the_doi_so_the_same_study_matches_across_different_urls(): void
    {
        $viaResolver = $this->normalizer->duplicateKey('https://a.example/uno', 'https://doi.org/10.1038/x-1');
        $viaBare = $this->normalizer->duplicateKey('https://b.example/due', '10.1038/x-1');

        $this->assertSame($viaResolver, $viaBare);
    }

    public function test_two_sources_without_any_machine_reference_are_never_treated_as_duplicates(): void
    {
        $this->assertNull($this->normalizer->duplicateKey(null, null));
        $this->assertNull($this->normalizer->duplicateKey('javascript:alert(1)', 'non-un-doi'));
    }

    public function test_query_string_difference_keeps_two_urls_distinct(): void
    {
        $this->assertNotSame(
            $this->normalizer->duplicateKey('https://esempio.it/p?id=1', null),
            $this->normalizer->duplicateKey('https://esempio.it/p?id=2', null)
        );
    }
}
