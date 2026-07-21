<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Banner sidebar test',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => '1',
            'priority' => 10,
            'banner_image' => 'banner-test.jpg',
            'banner_url' => 'https://example.org/promo',
            'banner_alt' => 'Promo di prova',
        ], $overrides);
    }

    public function test_migration_creates_the_ads_table(): void
    {
        $this->assertTrue(Schema::hasTable('ads'));

        $columns = Schema::getColumnListing('ads');
        foreach ([
            'id', 'name', 'position', 'type', 'active', 'priority',
            'adsense_publisher_id', 'adsense_slot_id', 'adsense_format',
            'banner_image', 'banner_url', 'banner_alt', 'html_code', 'notes',
            'created_at', 'updated_at',
        ] as $expected) {
            $this->assertContains($expected, $columns);
        }
    }

    public function test_ad_model_saves_and_rereads_a_valid_record(): void
    {
        $ad = Ad::create([
            'name' => 'Annuncio di prova',
            'position' => 'articolo-top',
            'type' => 'html',
            'active' => true,
            'priority' => 5,
            'html_code' => '<div>ads</div>',
        ]);

        $fresh = Ad::find($ad->id);

        $this->assertNotNull($fresh);
        $this->assertSame('Annuncio di prova', $fresh->name);
        $this->assertSame('articolo-top', $fresh->position);
        $this->assertSame('html', $fresh->type);
        $this->assertSame('<div>ads</div>', $fresh->html_code);
    }

    public function test_nullable_fields_are_accepted(): void
    {
        $ad = Ad::create([
            'name' => 'Solo campi minimi',
            'position' => 'footer',
            'type' => 'adsense',
            'active' => false,
        ]);

        $fresh = Ad::find($ad->id);

        $this->assertNull($fresh->adsense_publisher_id);
        $this->assertNull($fresh->adsense_slot_id);
        $this->assertNull($fresh->adsense_format);
        $this->assertNull($fresh->banner_image);
        $this->assertNull($fresh->banner_url);
        $this->assertNull($fresh->banner_alt);
        $this->assertNull($fresh->html_code);
        $this->assertNull($fresh->notes);
    }

    public function test_active_and_priority_casts_work(): void
    {
        $ad = Ad::create([
            'name' => 'Cast test',
            'position' => 'lista',
            'type' => 'banner',
            'active' => '1',
            'priority' => '42',
            'banner_image' => 'x.jpg',
        ]);

        $fresh = Ad::find($ad->id);

        $this->assertIsBool($fresh->active);
        $this->assertTrue($fresh->active);
        $this->assertIsInt($fresh->priority);
        $this->assertSame(42, $fresh->priority);
    }

    public function test_admin_ads_page_does_not_fail_for_missing_table(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.ads'));

        $response->assertOk();
    }

    public function test_admin_can_create_an_ad(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.ads.store'), $this->validPayload());

        $response->assertRedirect(route('admin.ads'));
        $this->assertDatabaseHas('ads', [
            'name' => 'Banner sidebar test',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => true,
            'priority' => 10,
        ]);
    }

    public function test_admin_can_update_an_ad(): void
    {
        $editor = $this->editor();
        $ad = Ad::create([
            'name' => 'Originale',
            'position' => 'sidebar',
            'type' => 'banner',
            'active' => false,
            'priority' => 1,
            'banner_image' => 'orig.jpg',
        ]);

        $response = $this->actingAs($editor)->put(route('admin.ads.update', $ad), $this->validPayload([
            'name' => 'Annuncio aggiornato',
            'priority' => 20,
        ]));

        $response->assertRedirect(route('admin.ads'));

        $ad->refresh();
        $this->assertSame('Annuncio aggiornato', $ad->name);
        $this->assertSame(20, $ad->priority);
        $this->assertTrue($ad->active);
    }

    public function test_admin_can_delete_an_ad(): void
    {
        $editor = $this->editor();
        $ad = Ad::create([
            'name' => 'Da eliminare',
            'position' => 'footer',
            'type' => 'html',
            'active' => true,
            'html_code' => '<div></div>',
        ]);

        $response = $this->actingAs($editor)->delete(route('admin.ads.destroy', $ad));

        $response->assertRedirect(route('admin.ads'));
        $this->assertDatabaseMissing('ads', ['id' => $ad->id]);
    }

    public function test_validation_rejects_an_invalid_position(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.ads.store'), $this->validPayload([
            'position' => 'posizione-inesistente',
        ]));

        $response->assertSessionHasErrors('position');
        $this->assertDatabaseMissing('ads', ['name' => 'Banner sidebar test']);
    }
}
