<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('categories')->updateOrInsert(
            ['slug' => 'intelligenza-artificiale'],
            [
                'name' => 'Intelligenza Artificiale',
                'description' => 'Categoria deterministica per i browser test.',
                'color' => '#2563eb',
                'sort_order' => 0,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $authorId = DB::table('users')->insertGetId([
            'name' => 'Browser Test Author',
            'email' => 'browser-tests@example.test',
            'password' => Hash::make('browser-tests'),
            'bio' => 'Autore deterministico usato esclusivamente dalla suite Playwright.',
            'role' => 'author',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $editorId = DB::table('users')->insertGetId([
            'name' => 'Browser Test Editor',
            'email' => 'browser-admin@example.test',
            'password' => Hash::make('browser-tests'),
            'bio' => 'Editor deterministico usato esclusivamente dai browser test admin.',
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        for ($index = 2; $index <= 10; $index++) {
            $slug = 'browser-category-'.$index;

            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => 'Browser Category '.$index,
                    'description' => 'Categoria deterministica per il carosello browser.',
                    'sort_order' => $index - 1,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('articles')->insert([
                'user_id' => $authorId,
                'title' => 'Browser carousel article '.$index,
                'slug' => 'browser-carousel-article-'.$index,
                'excerpt' => 'Fixture deterministica per il carosello categorie.',
                'body' => '<p>Contenuto browser deterministico.</p>',
                'category' => $slug,
                'cover_image' => null,
                'status' => 'published',
                'featured' => false,
                'read_minutes' => 1,
                'views' => 0,
                'published_at' => $now->copy()->subDays(30 + $index),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $publishedArticleId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Turing e il browser regression harness',
            'slug' => 'browser-turing-article',
            'excerpt' => 'Fixture deterministica per ricerca, archivio, autore e categoria.',
            'body' => '<h2>Macchine e pensiero</h2><p>Alan Turing guida questa fixture browser deterministica.</p><h3>Un contratto stabile</h3><p>Il contenuto resta locale e non dipende dalla produzione.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => null,
            'status' => 'published',
            'featured' => true,
            'read_minutes' => 2,
            'views' => 0,
            // Trust Layer V1 — fixture deterministica per il pannello
            // pubblico "Fonti primarie" (tests/browser/public-primary-sources.spec.js):
            // una riga URL (diventa link) e una riga di testo libero, per
            // coprire entrambi i casi nello stesso articolo già usato da
            // public-regression.spec.js.
            'primary_sources' => "https://example.org/turing-primary-source\nComunicato stampa di fixture, ottobre 2026",
            'published_at' => $now->copy()->subDays(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $scheduledArticleId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Articolo programmato da non mostrare',
            'slug' => 'browser-scheduled-secret',
            'excerpt' => 'Questa fixture non deve mai trapelare nei Percorsi pubblici.',
            'body' => '<p>Contenuto futuro.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => null,
            'status' => 'scheduled',
            'featured' => false,
            'read_minutes' => 1,
            'views' => 0,
            'published_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lastPublishedArticleId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Dalle macchine ai modelli moderni',
            'slug' => 'browser-path-last-article',
            'excerpt' => 'Seconda tappa pubblica del percorso browser.',
            'body' => '<h2>Una seconda lettura</h2><p>La navigazione salta il contenuto programmato e arriva qui.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => null,
            'status' => 'published',
            'featured' => false,
            'read_minutes' => 2,
            'views' => 0,
            'published_at' => $now->copy()->subDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $completeArticleId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Capitolo conclusivo browser',
            'slug' => 'browser-complete-path-article',
            'excerpt' => 'Fixture dedicata al Percorso concluso.',
            'body' => '<p>Questo articolo appartiene soltanto al Percorso concluso.</p>',
            'category' => 'intelligenza-artificiale',
            'cover_image' => null,
            'status' => 'published',
            'featured' => false,
            'read_minutes' => 1,
            'views' => 0,
            'published_at' => $now->copy()->subHours(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminRows = [];
        for ($i = 1; $i <= 26; $i++) {
            $adminRows[] = [
                'user_id' => $editorId,
                'title' => $i === 1
                    ? 'Titolo browser molto lungo per verificare che le azioni restino raggiungibili anche quando il contenuto editoriale supera ampiamente la lunghezza normale della tabella amministrativa'
                    : 'Bozza browser amministrativa '.$i,
                'slug' => 'browser-admin-draft-'.$i,
                'excerpt' => 'Fixture admin deterministica '.$i.'.',
                'body' => '<p>Contenuto admin browser '.$i.'.</p>',
                'category' => 'intelligenza-artificiale',
                'cover_image' => null,
                'status' => 'draft',
                'featured' => false,
                'read_minutes' => 1,
                'views' => $i,
                'published_at' => null,
                'created_at' => $now->copy()->subMinutes($i),
                'updated_at' => $now,
            ];
        }
        DB::table('articles')->insert($adminRows);

        $clusterId = DB::table('content_clusters')->insertGetId([
            'name' => 'IA spiegata',
            'slug' => 'ia-spiegata',
            'short_description' => 'Percorso deterministico per i browser test.',
            'description' => 'Una sequenza pubblica usata esclusivamente in CI.',
            'seo_title' => 'IA spiegata',
            'seo_description' => 'Percorso CI per verificare la superficie pubblica Percorsi.',
            'pillar_article_id' => $publishedArticleId,
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('article_content_cluster')->insert([
            [
                'article_id' => $publishedArticleId,
                'content_cluster_id' => $clusterId,
                'position' => 10,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'article_id' => $scheduledArticleId,
                'content_cluster_id' => $clusterId,
                'position' => 20,
                'is_primary' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'article_id' => $lastPublishedArticleId,
                'content_cluster_id' => $clusterId,
                'position' => 30,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $completeClusterId = DB::table('content_clusters')->insertGetId([
            'name' => 'Percorso completo CI',
            'slug' => 'percorso-completo-ci',
            'short_description' => 'Percorso concluso per il contratto browser.',
            'pillar_article_id' => $completeArticleId,
            'is_active' => true,
            'lifecycle_status' => 'complete',
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('article_content_cluster')->insert([
            'article_id' => $completeArticleId,
            'content_cluster_id' => $completeClusterId,
            'position' => 10,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('content_clusters')->insert([
            'name' => 'Percorso inattivo CI',
            'slug' => 'percorso-inattivo-ci',
            'is_active' => false,
            'lifecycle_status' => 'complete',
            'sort_order' => 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(1, 5) as $index) {
            DB::table('content_clusters')->insert([
                'name' => $index === 5
                    ? 'Un Percorso dal titolo volutamente molto lungo per verificare la robustezza della card'
                    : 'Percorso paginazione CI '.$index,
                'slug' => 'percorso-paginazione-ci-'.$index,
                'short_description' => 'Fixture pubblica deterministica per la paginazione dei Percorsi.',
                'is_active' => true,
                'lifecycle_status' => 'updating',
                'sort_order' => 100 + $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
