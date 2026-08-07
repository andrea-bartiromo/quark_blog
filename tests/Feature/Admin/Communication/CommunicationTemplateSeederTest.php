<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationTemplate;
use App\Models\User;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CommunicationTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_template_with_an_active_version(): void
    {
        (new CommunicationTemplateSeeder)->run();

        $template = CommunicationTemplate::where('name', 'Newsletter settimanale Kairus')->firstOrFail();

        $this->assertNotNull($template->active_version_id);
        $this->assertNotNull($template->activeVersion);
        $this->assertSame(1, $template->activeVersion->version_number);
    }

    public function test_running_it_twice_does_not_create_a_duplicate(): void
    {
        (new CommunicationTemplateSeeder)->run();
        (new CommunicationTemplateSeeder)->run();

        $this->assertSame(1, CommunicationTemplate::where('name', 'Newsletter settimanale Kairus')->count());
    }

    public function test_it_attributes_the_template_to_an_existing_editor_when_available(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        (new CommunicationTemplateSeeder)->run();

        $template = CommunicationTemplate::where('name', 'Newsletter settimanale Kairus')->firstOrFail();

        $this->assertSame($editor->id, $template->created_by);
    }

    public function test_the_database_seeder_never_calls_it_automatically(): void
    {
        $source = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringNotContainsString('CommunicationTemplateSeeder', $source);
    }
}
