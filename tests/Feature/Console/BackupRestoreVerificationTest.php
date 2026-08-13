<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupRestoreVerificationTest extends TestCase
{
    public function test_restored_mariadb_contains_representative_schema_and_data(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('articles'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('article_link_suggestions'));

        $author = DB::table('users')->where('email', 'backup-restore@example.test')->first();
        $this->assertNotNull($author);
        $this->assertSame('Backup Restore Autore È', $author->name);

        $source = DB::table('articles')->where('slug', 'backup-restore-source')->first();
        $target = DB::table('articles')->where('slug', 'backup-restore-target')->first();
        $this->assertNotNull($source);
        $this->assertNotNull($target);
        $this->assertSame($author->id, $source->user_id);
        $this->assertNull($source->excerpt);
        $this->assertStringContainsString('città, caffè, perché', $source->body);
        $this->assertNotNull($source->published_at);

        $suggestion = DB::table('article_link_suggestions')
            ->where('source_article_id', $source->id)
            ->where('target_article_id', $target->id)
            ->first();
        $this->assertNotNull($suggestion);
        $this->assertSame('backup-restore-target', $suggestion->target_slug);
        $this->assertNull($suggestion->context_excerpt);
        $this->assertNull($suggestion->reviewed_at);
        $this->assertNull($suggestion->reviewed_by);

        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'article_link_suggestions' AND REFERENCED_TABLE_NAME IS NOT NULL");
        $this->assertNotEmpty($foreignKeys);
    }
}
