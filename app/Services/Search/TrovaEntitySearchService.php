<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrovaEntitySearchService
{
    public function __construct(private readonly SearchTokenizer $tokenizer) {}

    /**
     * Search the publication-safe non-Article surfaces currently available on main.
     * Article results remain owned by ArticleSearchService and are deliberately not
     * re-ranked here.
     *
     * @return array{categories: Collection<int, array<string, mixed>>, percorsi: Collection<int, array<string, mixed>>}
     */
    public function search(string $query): array
    {
        $tokens = array_map($this->normalize(...), $this->tokenizer->tokenize($query));

        if ($tokens === []) {
            return [
                'categories' => collect(),
                'percorsi' => collect(),
            ];
        }

        return [
            'categories' => $this->searchCategories($query, $tokens),
            'percorsi' => $this->searchPercorsi($query, $tokens),
        ];
    }

    /** @param list<string> $tokens */
    private function searchCategories(string $query, array $tokens): Collection
    {
        return Category::query()
            ->where(function ($categories) {
                $categories
                    ->whereHas('articles', fn ($articles) => $articles->published())
                    ->orWhereHas('secondaryArticles', fn ($articles) => $articles->published());
            })
            ->get(['id', 'name', 'slug', 'description'])
            ->map(fn (Category $category) => $this->result(
                type: 'category',
                id: $category->id,
                label: $category->name,
                slug: $category->slug,
                text: implode(' ', array_filter([$category->name, $category->description])),
                query: $query,
                tokens: $tokens,
            ))
            ->filter()
            ->sortBy(fn (array $result) => [$result['match_rank'], Str::lower($result['label']), $result['id']])
            ->values();
    }

    /** @param list<string> $tokens */
    private function searchPercorsi(string $query, array $tokens): Collection
    {
        return ContentCluster::query()
            ->active()
            ->whereHas('articles', fn ($articles) => $articles->published())
            ->get(['id', 'name', 'slug', 'short_description', 'description'])
            ->map(fn (ContentCluster $cluster) => $this->result(
                type: 'percorso',
                id: $cluster->id,
                label: $cluster->name,
                slug: $cluster->slug,
                text: implode(' ', array_filter([$cluster->name, $cluster->short_description, $cluster->description])),
                query: $query,
                tokens: $tokens,
            ))
            ->filter()
            ->sortBy(fn (array $result) => [$result['match_rank'], Str::lower($result['label']), $result['id']])
            ->values();
    }

    /**
     * @param list<string> $tokens
     * @return array<string, mixed>|null
     */
    private function result(
        string $type,
        int $id,
        string $label,
        string $slug,
        string $text,
        string $query,
        array $tokens,
    ): ?array {
        $normalizedLabel = $this->normalize($label);
        $normalizedQuery = $this->normalize($query);
        $normalizedText = $this->normalize($text);

        $matchClass = match (true) {
            $normalizedLabel === $normalizedQuery => 'EXACT',
            collect($tokens)->every(fn (string $token) => str_contains($normalizedText, $token)) => 'ALL_TOKENS',
            collect($tokens)->contains(fn (string $token) => str_contains($normalizedText, $token)) => 'ANY_TOKEN',
            default => null,
        };

        if ($matchClass === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'slug' => $slug,
            'match_class' => $matchClass,
            'match_rank' => match ($matchClass) {
                'EXACT' => 1,
                'ALL_TOKENS' => 2,
                default => 3,
            },
        ];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();
    }
}
