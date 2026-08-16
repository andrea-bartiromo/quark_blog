<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use App\Services\PublicMediaSyncService;
use App\Services\StorageAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\Concerns\UsesIsolatedStoragePath;
use Tests\TestCase;

class StorageAuditServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;
    use UsesIsolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
        $this->setUpIsolatedStoragePath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        $this->tearDownIsolatedStoragePath();
        parent::tearDown();
    }

    private function service(): StorageAuditService
    {
        return new StorageAuditService(new PublicMediaSyncService);
    }

    private function mediaDir(): string
    {
        return public_path('assets/img');
    }

    private function putMediaFile(string $relativePath, string $content = 'x'): string
    {
        $path = $this->mediaDir().'/'.$relativePath;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return $path;
    }

    private function mediaRow(string $diskName, int $size = 1): Media
    {
        return Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => basename($diskName),
            'disk_name' => $diskName,
            'mime_type' => 'image/jpeg',
            'size' => $size,
        ]);
    }

    // ── Database ─────────────────────────────────────────────────────

    public function test_missing_database_file_is_reported_as_not_existing(): void
    {
        $report = $this->service()->audit();

        $this->assertFalse($report['database']['exists']);
        $this->assertSame(0, $report['database']['size_bytes']);
    }

    public function test_database_file_size_is_measured(): void
    {
        file_put_contents($this->isolatedDatabasePath.'/database.sqlite', str_repeat('a', 1234));

        $report = $this->service()->audit();

        $this->assertTrue($report['database']['exists']);
        $this->assertSame(1234, $report['database']['size_bytes']);
    }

    // ── Backup ───────────────────────────────────────────────────────

    public function test_missing_backup_directory_reports_zero_with_multiplier_one(): void
    {
        rmdir($this->isolatedStoragePath.'/backups');

        $report = $this->service()->audit();

        $this->assertSame(0, $report['backup']['size_bytes']);
        $this->assertSame(0, $report['backup']['count']);
        $this->assertSame(1, $report['backup']['multiplier']);
    }

    public function test_backup_files_are_counted_and_summed(): void
    {
        $dir = $this->isolatedStoragePath.'/backups';
        file_put_contents($dir.'/database-2026-01-01-000000.sqlite', str_repeat('a', 100));
        file_put_contents($dir.'/database-2026-01-02-000000.sqlite', str_repeat('a', 200));
        // File non conforme al pattern atteso: ignorato.
        file_put_contents($dir.'/note.txt', 'not a backup');

        $report = $this->service()->audit();

        $this->assertSame(300, $report['backup']['size_bytes']);
        $this->assertSame(2, $report['backup']['count']);
        $this->assertSame(3, $report['backup']['multiplier']);
    }

    // ── Media: base cases ────────────────────────────────────────────

    public function test_empty_media_directory_reports_zero_files(): void
    {
        $report = $this->service()->audit();

        $this->assertSame(0, $report['media']['total_count']);
        $this->assertSame(0, $report['media']['total_size_bytes']);
    }

    public function test_nonexistent_media_directory_does_not_crash(): void
    {
        $this->deleteDirRecursively($this->mediaDir());

        $report = $this->service()->audit();

        $this->assertSame(0, $report['media']['total_count']);
    }

    public function test_files_with_spaces_and_unicode_names_are_counted(): void
    {
        $this->putMediaFile('foto con spazi.jpg', str_repeat('a', 50));
        $this->putMediaFile('città-è-più-bella.png', str_repeat('a', 60));

        $report = $this->service()->audit();

        $this->assertSame(2, $report['media']['total_count']);
        $this->assertSame(110, $report['media']['total_size_bytes']);
    }

    public function test_subfolders_are_walked_recursively(): void
    {
        $this->putMediaFile('categories/a.jpg');
        $this->putMediaFile('articles/nested/deep/b.png');

        $report = $this->service()->audit();

        $this->assertSame(2, $report['media']['total_count']);
    }

    public function test_zero_byte_file_is_counted_with_zero_size(): void
    {
        $this->putMediaFile('empty.jpg', '');

        $report = $this->service()->audit();

        $this->assertSame(1, $report['media']['total_count']);
        $this->assertSame(0, $report['media']['total_size_bytes']);
    }

    // ── Formats ──────────────────────────────────────────────────────

    public function test_format_breakdown_groups_jpg_and_jpeg_together_case_insensitively(): void
    {
        $this->putMediaFile('a.jpg');
        $this->putMediaFile('b.JPG');
        $this->putMediaFile('c.jpeg');
        $this->putMediaFile('d.webp');
        $this->putMediaFile('e.PNG');
        $this->putMediaFile('f.gif');

        $report = $this->service()->audit();
        $breakdown = $report['media']['format_breakdown'];

        $this->assertSame(3, $breakdown['jpg']['count']);
        $this->assertSame(1, $breakdown['webp']['count']);
        $this->assertSame(1, $breakdown['png']['count']);
        $this->assertSame(1, $breakdown['gif']['count']);
        $this->assertSame(6, $report['media']['image_count']);
    }

    public function test_non_image_file_in_media_root_is_bucketed_as_other_and_does_not_crash(): void
    {
        $this->putMediaFile('readme.txt', 'not an image');

        $report = $this->service()->audit();

        $this->assertSame(1, $report['media']['total_count']);
        $this->assertSame(0, $report['media']['image_count']);
        $this->assertSame(1, $report['media']['format_breakdown']['other']['count']);
    }

    public function test_average_image_size_ignores_non_image_files(): void
    {
        $this->putMediaFile('a.jpg', str_repeat('a', 100));
        $this->putMediaFile('b.jpg', str_repeat('a', 300));
        $this->putMediaFile('notes.txt', str_repeat('a', 999999));

        $report = $this->service()->audit();

        $this->assertSame(200, $report['media']['average_image_size_bytes']);
    }

    public function test_top_heaviest_images_are_sorted_descending_and_exclude_non_images(): void
    {
        $this->putMediaFile('small.jpg', str_repeat('a', 10));
        $this->putMediaFile('big.jpg', str_repeat('a', 1000));
        $this->putMediaFile('medium.png', str_repeat('a', 500));
        $this->putMediaFile('huge.txt', str_repeat('a', 999999));

        $report = $this->service()->audit();
        $top = $report['media']['top_heaviest_images'];

        $this->assertSame('big.jpg', $top[0]['relative_path']);
        $this->assertSame('medium.png', $top[1]['relative_path']);
        $this->assertSame('small.jpg', $top[2]['relative_path']);
        $this->assertCount(3, $top);
    }

    public function test_top_heaviest_directories_are_aggregated_and_sorted(): void
    {
        $this->putMediaFile('categories/a.jpg', str_repeat('a', 10));
        $this->putMediaFile('categories/b.jpg', str_repeat('a', 10));
        $this->putMediaFile('articles/c.jpg', str_repeat('a', 5));

        $report = $this->service()->audit();
        $directories = $report['media']['top_heaviest_directories'];
        $keys = array_keys($directories);

        $this->assertSame('categories', $keys[0]);
        $this->assertSame(20, $directories['categories']);
        $this->assertSame(5, $directories['articles']);
    }

    // ── Orphans / missing files ──────────────────────────────────────

    public function test_file_without_media_record_is_an_orphan(): void
    {
        $this->putMediaFile('orphan.jpg');

        $report = $this->service()->audit();

        $this->assertSame(1, $report['media']['orphan_count']);
        $this->assertSame(['orphan.jpg'], $report['media']['orphan_files']);
    }

    public function test_media_record_without_file_is_reported_missing(): void
    {
        $this->mediaRow('ghost.jpg');

        $report = $this->service()->audit();

        $this->assertSame(1, $report['media']['missing_file_count']);
        $this->assertSame(['ghost.jpg'], $report['media']['missing_files']);
        $this->assertSame(0, $report['media']['orphan_count']);
    }

    public function test_matching_media_record_and_file_are_neither_orphan_nor_missing(): void
    {
        $this->putMediaFile('cover.jpg');
        $this->mediaRow('cover.jpg');

        $report = $this->service()->audit();

        $this->assertSame(0, $report['media']['orphan_count']);
        $this->assertSame(0, $report['media']['missing_file_count']);
        $this->assertSame(1, $report['media']['registered_in_db_count']);
        $this->assertSame(1, $report['media']['on_filesystem_count']);
    }

    // ── Symlinks ─────────────────────────────────────────────────────

    public function test_symlink_pointing_outside_media_root_is_not_followed(): void
    {
        $outside = sys_get_temp_dir().'/kairus-test-outside-'.uniqid('', true);
        mkdir($outside, 0775, true);
        file_put_contents($outside.'/secret.jpg', str_repeat('a', 12345));

        @symlink($outside, $this->mediaDir().'/escape-link');

        $report = $this->service()->audit();

        $this->assertSame(0, $report['media']['total_count']);

        $this->deleteDirRecursively($outside);
    }

    public function test_broken_symlink_is_skipped_without_error(): void
    {
        @symlink($this->mediaDir().'/does-not-exist.jpg', $this->mediaDir().'/broken-link.jpg');

        $report = $this->service()->audit();

        $this->assertSame(0, $report['media']['total_count']);
    }

    public function test_symlink_pointing_inside_media_root_does_not_cause_infinite_loop(): void
    {
        mkdir($this->mediaDir().'/real', 0775, true);
        file_put_contents($this->mediaDir().'/real/a.jpg', 'x');
        @symlink($this->mediaDir(), $this->mediaDir().'/real/self-link');

        $report = $this->service()->audit();

        // Un solo file reale: il ciclo di symlink non deve duplicare nulla
        // ne' entrare in loop infinito (il test stesso avrebbe timeout).
        $this->assertSame(1, $report['media']['total_count']);
    }

    // ── MEDIA_PUBLIC_ROOT ────────────────────────────────────────────

    public function test_unconfigured_public_root_is_reported_as_not_configured(): void
    {
        Config::set('media.public_root', null);

        $report = $this->service()->audit();

        $this->assertFalse($report['media']['public_root_sync']['configured']);
    }

    public function test_public_root_equal_to_app_root_is_reported_as_collapsed(): void
    {
        Config::set('media.public_root', $this->mediaDir());

        $report = $this->service()->audit();
        $sync = $report['media']['public_root_sync'];

        $this->assertTrue($sync['configured']);
        $this->assertTrue($sync['collapses_to_app_root']);
    }

    public function test_separate_public_root_reports_present_in_both_and_only_in_app_root(): void
    {
        $secondary = sys_get_temp_dir().'/kairus-test-secondary-'.uniqid('', true);
        mkdir($secondary, 0775, true);

        $this->putMediaFile('both.jpg');
        $this->putMediaFile('only-app.jpg');
        file_put_contents($secondary.'/both.jpg', 'x');

        Config::set('media.public_root', $secondary);

        $report = $this->service()->audit();
        $sync = $report['media']['public_root_sync'];

        $this->assertTrue($sync['configured']);
        $this->assertFalse($sync['collapses_to_app_root']);
        $this->assertSame(1, $sync['present_in_both_count']);
        $this->assertSame(1, $sync['present_only_in_app_root_count']);

        $this->deleteDirRecursively($secondary);
    }

    // ── Budget ───────────────────────────────────────────────────────

    public function test_budget_percentage_and_remaining_are_calculated(): void
    {
        Config::set('storage_audit.budget_bytes', 1000);
        $this->putMediaFile('a.jpg', str_repeat('a', 250));

        $report = $this->service()->audit();

        $this->assertSame(1000, $report['budget']['budget_bytes']);
        $this->assertSame(25.0, $report['budget']['used_percent']);
        $this->assertSame(750, $report['budget']['remaining_bytes']);
    }

    public function test_remaining_bytes_is_floored_at_zero_when_over_budget(): void
    {
        Config::set('storage_audit.budget_bytes', 10);
        $this->putMediaFile('a.jpg', str_repeat('a', 1000));

        $report = $this->service()->audit();

        $this->assertSame(0, $report['budget']['remaining_bytes']);
    }

    // ── Scale ────────────────────────────────────────────────────────

    public function test_handles_several_hundred_files_without_error(): void
    {
        for ($i = 0; $i < 300; $i++) {
            $this->putMediaFile("bulk/file-{$i}.jpg", 'x');
        }

        $report = $this->service()->audit();

        $this->assertSame(300, $report['media']['total_count']);
        $this->assertCount(10, $report['media']['top_heaviest_images']);
    }

    private function deleteDirRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
