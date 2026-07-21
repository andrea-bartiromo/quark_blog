<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemapIndex(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
        $xml .= '  <sitemap><loc>'.url('/sitemap.xml').'</loc></sitemap>'.PHP_EOL;
        $xml .= '  <sitemap><loc>'.url('/news-sitemap.xml').'</loc></sitemap>'.PHP_EOL;
        $xml .= '</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function sitemap(): Response
    {
        $articles = Article::published()->get(['slug', 'category', 'updated_at']);
        $categories = array_keys(Category::options());
        $base = config('app.url');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach ($this->staticSitemapPages() as [$path, $priority, $freq]) {
            $xml .= "  <url><loc>{$base}{$path}</loc><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>".PHP_EOL;
        }

        foreach ($categories as $slug) {
            $xml .= "  <url><loc>{$base}/categoria/{$slug}</loc><changefreq>daily</changefreq><priority>0.8</priority></url>".PHP_EOL;
        }

        foreach ($articles as $article) {
            $xml .= "  <url><loc>{$base}/articolo/{$article->slug}</loc><lastmod>{$article->updated_at->toDateString()}</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>".PHP_EOL;
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function feed(): Response
    {
        $articles = Article::published()->with('author')->limit(20)->get();
        $base = config('app.url');
        $siteName = config('laboratorio.name');
        $now = now()->toRfc2822String();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">'.PHP_EOL;
        $xml .= '<channel>'.PHP_EOL."  <title>{$siteName}</title>".PHP_EOL."  <link>{$base}</link>".PHP_EOL;
        $xml .= '  <description>La scienza spiegata come si deve</description>'.PHP_EOL.'  <language>it-IT</language>'.PHP_EOL;
        $xml .= "  <lastBuildDate>{$now}</lastBuildDate>".PHP_EOL;
        $xml .= '  <atom:link href="'.$base.'/feed.xml" rel="self" type="application/rss+xml"/>'.PHP_EOL;
        $xml .= "  <image><url>{$base}/assets/icons/logo.png</url><title>{$siteName}</title><link>{$base}</link></image>".PHP_EOL.PHP_EOL;

        foreach ($articles as $article) {
            $title = htmlspecialchars($article->title, ENT_XML1);
            $excerpt = htmlspecialchars($article->excerpt ?? '', ENT_XML1);
            $url = $base.'/articolo/'.$article->slug;
            $date = $article->published_at->toRfc2822String();
            $author = htmlspecialchars($article->author->name, ENT_XML1);
            $cat = htmlspecialchars(Category::options(false)[$article->category] ?? $article->category, ENT_XML1);
            $body = htmlspecialchars('<p>'.nl2br(strip_tags($article->body)).'</p>', ENT_XML1);

            $xml .= '  <item>'.PHP_EOL."    <title>{$title}</title>".PHP_EOL."    <link>{$url}</link>".PHP_EOL."    <guid isPermaLink=\"true\">{$url}</guid>".PHP_EOL;
            $xml .= "    <description>{$excerpt}</description>".PHP_EOL."    <pubDate>{$date}</pubDate>".PHP_EOL;
            $xml .= "    <dc:creator>{$author}</dc:creator>".PHP_EOL."    <category>{$cat}</category>".PHP_EOL;
            $xml .= "    <content:encoded>{$body}</content:encoded>".PHP_EOL.'  </item>'.PHP_EOL;
        }

        $xml .= "</channel>\n</rss>";

        return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function newsSitemap(): Response
    {
        $articles = Article::where('status', 'published')
            ->where('published_at', '>=', now()->subDays(2))
            ->orderByDesc('published_at')
            ->limit(1000)
            ->get(['title', 'slug', 'category', 'published_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.PHP_EOL;
        $xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'.PHP_EOL;
        $cats = Category::options(false);

        foreach ($articles as $article) {
            $url = route('articolo', $article->slug);
            $title = htmlspecialchars($article->title, ENT_XML1);
            $pub = $article->published_at->format('c');
            $genre = htmlspecialchars($cats[$article->category] ?? $article->category, ENT_XML1);

            $xml .= "  <url><loc>{$url}</loc><news:news>";
            $xml .= '<news:publication><news:name>Quark</news:name><news:language>it</news:language></news:publication>';
            $xml .= "<news:publication_date>{$pub}</news:publication_date><news:title>{$title}</news:title>";
            $xml .= "<news:genres>{$genre}</news:genres></news:news></url>".PHP_EOL;
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function staticSitemapPages(): array
    {
        return [
            ['/', '1.0', 'daily'],
            ['/notizie', '0.9', 'daily'],
            ['/la-redazione', '0.6', 'monthly'],
            ['/chi-siamo', '0.5', 'monthly'],
            ['/pubblicita', '0.4', 'monthly'],
            ['/contatti', '0.4', 'monthly'],
        ];
    }
}
