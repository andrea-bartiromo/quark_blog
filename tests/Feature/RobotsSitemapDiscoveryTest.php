<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotsSitemapDiscoveryTest extends TestCase
{
    public function test_private_paths_are_disallowed_for_googlebot_and_generic_crawlers(): void
    {
        $groups = $this->robotsGroups();
        $privatePaths = ['/admin/', '/redazione/', '/storage/', '/api/'];

        foreach (['googlebot', '*'] as $agent) {
            $rules = $this->rulesForAgent($groups, $agent);

            foreach ($privatePaths as $path) {
                $this->assertContains('Disallow: '.$path, $rules, "{$agent} must receive the private-path policy directly.");
            }
        }
    }

    public function test_search_is_crawlable_in_robots_but_remains_noindex_follow(): void
    {
        $robotsTxt = file_get_contents(public_path('robots.txt'));

        $this->assertStringNotContainsString('Disallow: /ricerca', $robotsTxt);

        $this->get(route('ricerca'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_robots_advertises_the_complete_sitemap_index(): void
    {
        $robotsTxt = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://kairus.it/sitemap-index.xml', $robotsTxt);
    }

    public function test_sitemap_index_contains_normal_and_news_sitemaps_once_with_https_urls(): void
    {
        config(['app.url' => 'https://kairus.it']);

        $xml = $this->get('https://kairus.it/sitemap-index.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->getContent();

        $this->assertSame(1, substr_count($xml, '<loc>https://kairus.it/sitemap.xml</loc>'));
        $this->assertSame(1, substr_count($xml, '<loc>https://kairus.it/news-sitemap.xml</loc>'));
        $this->assertStringNotContainsString('<loc>http://kairus.it/', $xml);
    }

    /**
     * @return array<int, array{agents: array<int, string>, rules: array<int, string>}>
     */
    private function robotsGroups(): array
    {
        $lines = preg_split('/\R/', file_get_contents(public_path('robots.txt'))) ?: [];
        $groups = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? '');

            if ($line === '' || str_starts_with($line, '#') || str_starts_with(strtolower($line), 'sitemap:')) {
                continue;
            }

            if (str_starts_with(strtolower($line), 'user-agent:')) {
                $agent = strtolower(trim(substr($line, strlen('User-agent:'))));

                if ($current === null || $groups[$current]['rules'] !== []) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($groups);
                }

                $groups[$current]['agents'][] = $agent;
                continue;
            }

            if ($current !== null) {
                $groups[$current]['rules'][] = $line;
            }
        }

        return $groups;
    }

    /**
     * @param array<int, array{agents: array<int, string>, rules: array<int, string>}> $groups
     * @return array<int, string>
     */
    private function rulesForAgent(array $groups, string $agent): array
    {
        foreach ($groups as $group) {
            if (in_array($agent, $group['agents'], true)) {
                return $group['rules'];
            }
        }

        return [];
    }
}
