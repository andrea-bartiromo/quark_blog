<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BackupRestoreTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->utc()->startOfSecond();

        $authorId = DB::table('users')->insertGetId([
            'name' => 'Backup Restore Autore È',
            'email' => 'backup-restore@example.test',
            'password' => Hash::make('not-a-production-secret'),
            'role' => 'author',
            'bio' => 'Fixture effimera CI per verifica dump e restore.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('categories')->insert([
            'name' => 'Backup Restore CI',
            'slug' => 'backup-restore-ci',
            'description' => 'Categoria effimera con Unicode: caffè, perché.',
            'color' => '#123456',
            'sort_order' => 999,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sourceId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Sorgente backup — perché funziona',
            'slug' => 'backup-restore-source',
            'excerpt' => null,
            'body' => '<p>Contenuto UTF-8: città, caffè, perché.</p>',
            'category' => 'backup-restore-ci',
            'cover_image' => null,
            'status' => 'published',
            'featured' => false,
            'read_minutes' => 1,
            'views' => 7,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $targetId = DB::table('articles')->insertGetId([
            'user_id' => $authorId,
            'title' => 'Target backup restore',
            'slug' => 'backup-restore-target',
            'excerpt' => 'Target deterministico.',
            'body' => '<p>Target.</p>',
            'category' => 'backup-restore-ci',
            'cover_image' => null,
            'status' => 'published',
            'featured' => false,
            'read_minutes' => 1,
            'views' => 3,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('article_link_suggestions')->insert([
            'source_article_id' => $sourceId,
            'target_article_id' => $targetId,
            'target_slug' => 'backup-restore-target',
            'anchor_text' => 'target restore',
            'context_excerpt' => null,
            'reason' => 'Fixture restore CI',
            'confidence_score' => 91,
            'status' => 'proposed',
            'reviewed_at' => null,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
