<?php

namespace Tests\Unit;

use App\Models\Article;
use Tests\TestCase;

class ArticleReadMinutesTest extends TestCase
{
    // 1. Formula: 200 parole/minuto, arrotondato all'intero più vicino
    public function test_calculates_minutes_from_word_count_at_200_words_per_minute(): void
    {
        $body = str_repeat('parola ', 400); // 400 parole -> 2 min esatti

        $this->assertSame(2, Article::calculateReadMinutes($body));
    }

    // 2. Arrotondamento all'intero più vicino, non per difetto/eccesso sistematico
    public function test_rounds_to_the_nearest_minute(): void
    {
        $justUnderHalf = str_repeat('parola ', 220); // 220/200 = 1.1 -> 1
        $justOverHalf = str_repeat('parola ', 260); // 260/200 = 1.3 -> 1
        $roundsUp = str_repeat('parola ', 310); // 310/200 = 1.55 -> 2

        $this->assertSame(1, Article::calculateReadMinutes($justUnderHalf));
        $this->assertSame(1, Article::calculateReadMinutes($justOverHalf));
        $this->assertSame(2, Article::calculateReadMinutes($roundsUp));
    }

    // 3. Minimo 1 minuto, mai 0, anche per un testo brevissimo
    public function test_minimum_is_one_minute_even_for_very_short_text(): void
    {
        $this->assertSame(1, Article::calculateReadMinutes('Poche parole.'));
        $this->assertSame(1, Article::calculateReadMinutes(''));
        $this->assertSame(1, Article::calculateReadMinutes(null));
    }

    // 4. I tag HTML non contano come testo leggibile
    public function test_html_tags_are_stripped_before_counting_words(): void
    {
        $plainBody = str_repeat('parola ', 400);
        $htmlBody = '<p>'.str_repeat('<strong>parola</strong> ', 400).'</p>';

        $this->assertSame(
            Article::calculateReadMinutes($plainBody),
            Article::calculateReadMinutes($htmlBody)
        );
    }
}
