<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use App\Services\DataExport\DashboardExportPackageBuilder;
use App\Services\DataExport\ExportWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

class DashboardDataExportTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'from' => '2026-08-01',
            'to' => '2026-08-30',
            'timezone' => 'Europe/Rome',
            'format' => 'json',
            'sections' => ['dashboard-summary'],
        ], $overrides);
    }

    public function test_only_an_administrator_can_export_and_the_dashboard_action_is_role_scoped(): void
    {
        $admin = $this->user('admin');
        $editor = $this->user('editor');
        $author = $this->user('author');

        $this->actingAs($admin)->get(route('admin.editorial-operations'))
            ->assertOk()->assertSee('Esporta dati per analisi');
        $this->actingAs($editor)->get(route('admin.editorial-operations'))
            ->assertOk()->assertDontSee('Esporta dati per analisi');

        $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload())->assertOk();
        $this->actingAs($editor)->post(route('admin.editorial-operations.export'), $this->payload())->assertForbidden();
        $this->actingAs($author)->post(route('admin.editorial-operations.export'), $this->payload())->assertForbidden();
    }

    public function test_invalid_excessive_and_multi_section_csv_requests_are_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload(['to' => '2026-07-01']))
            ->assertSessionHasErrors('to');
        $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload(['from' => '2024-01-01']))
            ->assertSessionHasErrors('to');
        $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'format' => 'csv',
            'sections' => ['content-health', 'second-read'],
        ]))->assertSessionHasErrors('sections');
    }

    public function test_json_is_versioned_distinguishes_zero_from_unavailable_and_performs_no_external_action(): void
    {
        Http::fake();
        Queue::fake();
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'sections' => ['dashboard-summary', 'second-read', 'newsletter-summary'],
        ]))->assertOk();
        $document = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0.0', $document['manifest']['schema_version']);
        $this->assertSame(0, $document['datasets']['second-read']['rows'][0]['sample_size']);
        $this->assertNull($document['datasets']['second-read']['rows'][0]['second_read_rate']);
        $this->assertSame('insufficient_data', $document['datasets']['second-read']['status']);
        $this->assertArrayHasKey('social-calendar', $document['manifest']['unavailable_sections']);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_csv_is_utf8_parseable_preserves_unicode_and_neutralizes_formulas_newlines_and_secrets(): void
    {
        $admin = $this->user('admin');
        Article::create([
            'user_id' => $admin->id,
            'title' => "=SOMMA(1;1) È Unicode <b>HTML</b> user@example.test token=abc123\nseconda riga",
            'slug' => 'formula-export',
            'body' => '<p>Corpo.</p>',
            'excerpt' => '',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subDay(),
        ]);

        $csv = $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'format' => 'csv',
            'sections' => ['content-health'],
        ]))->assertOk()->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=SOMMA", $csv);
        $this->assertStringContainsString('È Unicode HTML', $csv);
        $this->assertStringContainsString('seconda riga', $csv);
        $this->assertStringNotContainsString('user@example.test', $csv);
        $this->assertStringNotContainsString('abc123', $csv);

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        fclose($stream);
        $this->assertCount(count($headers), $row);
    }

    public function test_newsletter_export_is_aggregate_and_never_contains_email_or_tokens(): void
    {
        $admin = $this->user('admin');
        foreach (range(1, 5) as $index) {
            Newsletter::create([
                'email' => 'private'.$index.'@example.test',
                'confirmed' => true,
                'token' => 'confirmation-secret-'.$index,
                'unsubscribe_token' => 'unsubscribe-secret-'.$index,
                'source' => 'article',
            ]);
        }

        $json = $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'sections' => ['newsletter-summary'],
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('private1@example.test', $json);
        $this->assertStringNotContainsString('confirmation-secret', $json);
        $this->assertStringNotContainsString('unsubscribe-secret', $json);
        $this->assertStringContainsString('"new_signups": 5', $json);
    }

    public function test_small_newsletter_segments_are_explicitly_suppressed(): void
    {
        $admin = $this->user('admin');
        Newsletter::create([
            'email' => 'single-private@example.test',
            'confirmed' => true,
            'token' => 'single-secret',
            'source' => 'sidebar',
        ]);

        $document = json_decode($this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'sections' => ['newsletter-summary'],
        ]))->assertOk()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $row = $document['datasets']['newsletter-summary']['rows'][0];

        $this->assertSame('insufficient_data', $row['status']);
        $this->assertNull($row['new_signups']);
        $this->assertNull($row['confirmed']);
    }

    public function test_zip_contains_only_expected_files_with_valid_manifest_checksums_and_is_deleted_after_send(): void
    {
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->post(route('admin.editorial-operations.export'), $this->payload([
            'format' => 'zip',
            'sections' => ['dashboard-summary', 'second-read'],
        ]));

        $response->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->sort()->values()->all();
        $this->assertSame(['dashboard-summary.json', 'data-quality.json', 'manifest.json', 'second-read.csv'], $names);
        $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['checksums_sha256'] as $file => $checksum) {
            $this->assertSame($checksum, hash('sha256', $zip->getFromName($file)));
        }
        $zip->close();

        $response->baseResponse->sendContent();
        $this->assertFileDoesNotExist($path);
    }

    public function test_temporary_zip_is_removed_when_composition_fails(): void
    {
        $directory = storage_path('app/tmp/dashboard-exports');
        $before = glob($directory.'/*.zip') ?: [];
        $window = new ExportWindow(
            CarbonImmutable::parse('2026-08-01', 'Europe/Rome'),
            CarbonImmutable::parse('2026-08-30', 'Europe/Rome'),
            'Europe/Rome',
        );

        try {
            app(DashboardExportPackageBuilder::class)->zip([
                'broken' => ['id' => 'broken', 'version' => '1.0.0', 'status' => 'available', 'rows' => []],
            ], $window, ['broken']);
            $this->fail('La composizione invalida doveva fallire.');
        } catch (\Throwable) {
            $this->assertSame($before, glob($directory.'/*.zip') ?: []);
        }
    }
}
