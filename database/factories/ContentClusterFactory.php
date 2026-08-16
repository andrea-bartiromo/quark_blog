<?php

namespace Database\Factories;

use App\Models\ContentCluster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentCluster> */
class ContentClusterFactory extends Factory
{
    protected $model = ContentCluster::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'short_description' => null,
            'description' => null,
            'cover_image' => null,
            'seo_title' => null,
            'seo_description' => null,
            'is_active' => false,
            'sort_order' => 0,
        ];
    }
}
