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

        DB::table('articles')->insert([
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
            'published_at' => $now->copy()->subDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
