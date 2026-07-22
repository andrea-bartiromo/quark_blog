<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaDiskNameUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'filename' => 'foto.jpg',
            'disk_name' => 'foto-abc123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12345,
        ], $overrides);
    }

    public function test_migration_adds_a_unique_index_on_disk_name(): void
    {
        $indexes = collect(Schema::getIndexes('media'));

        $diskNameIsUnique = $indexes->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['disk_name']
        );

        $this->assertTrue($diskNameIsUnique);
    }

    public function test_two_records_with_different_disk_names_can_be_created(): void
    {
        $user = $this->user();

        $first = Media::create($this->payload($user, ['disk_name' => 'foto-uno.jpg']));
        $second = Media::create($this->payload($user, ['disk_name' => 'foto-due.jpg']));

        $this->assertDatabaseHas('media', ['id' => $first->id, 'disk_name' => 'foto-uno.jpg']);
        $this->assertDatabaseHas('media', ['id' => $second->id, 'disk_name' => 'foto-due.jpg']);
    }

    public function test_creating_a_second_record_with_the_same_disk_name_fails(): void
    {
        $user = $this->user();

        Media::create($this->payload($user, ['disk_name' => 'foto-duplicata.jpg']));

        $this->expectException(QueryException::class);

        Media::create($this->payload($user, ['disk_name' => 'foto-duplicata.jpg']));
    }
}
