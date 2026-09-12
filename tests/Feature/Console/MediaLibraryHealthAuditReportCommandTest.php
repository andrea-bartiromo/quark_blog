<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Cantiere 26 (programma 100-cantieri Kairus): media:health-audit è di
 * sola lettura — non è un gate di rilascio.
 */
class MediaLibraryHealthAuditReportCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    public function test_reports_no_problems_by_default(): void
    {
        $this->artisan('media:health-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun problema rilevato');
    }

    public function test_fails_when_a_media_record_is_missing_its_file(): void
    {
        Media::create([
            'user_id' => User::factory()->create()->id,
            'filename' => 'fantasma.png',
            'disk_name' => 'fantasma.png',
            'mime_type' => 'image/png',
            'size' => 1000,
        ]);

        $this->artisan('media:health-audit')->assertExitCode(1);
    }

    public function test_json_output_is_produced_successfully(): void
    {
        $this->artisan('media:health-audit', ['--json' => true])->assertExitCode(0);
    }
}
