<?php

namespace Tests\Unit;

use App\Support\VersionedAsset;
use Tests\TestCase;

class VersionedAssetTest extends TestCase
{
    public function test_an_existing_file_is_versioned_with_its_real_mtime(): void
    {
        $url = VersionedAsset::url('css/style.css');

        $expectedVersion = filemtime(public_path('css/style.css'));

        $this->assertStringEndsWith('?v='.$expectedVersion, $url);
        $this->assertStringContainsString('/css/style.css', $url);
    }

    public function test_two_calls_for_the_same_untouched_file_produce_the_same_version(): void
    {
        $first = VersionedAsset::url('css/style.css');
        $second = VersionedAsset::url('css/style.css');

        $this->assertSame($first, $second);
    }

    public function test_a_missing_file_falls_back_to_a_fixed_version_instead_of_erroring(): void
    {
        $url = VersionedAsset::url('css/this-file-does-not-exist-anywhere.css');

        $this->assertStringEndsWith('?v=1', $url);
    }
}
