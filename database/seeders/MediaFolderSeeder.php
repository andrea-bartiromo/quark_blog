<?php

namespace Database\Seeders;

use App\Models\MediaFolder;
use App\Services\MediaFolderService;
use Illuminate\Database\Seeder;

class MediaFolderSeeder extends Seeder
{
    public function run(MediaFolderService $service): void
    {
        $definitions = [
            ['name' => 'Da classificare', 'slug' => '_da-classificare', 'path' => '_da-classificare', 'is_protected' => true, 'sort_order' => 10],
            ['name' => 'Articoli', 'slug' => 'articles', 'path' => 'articles', 'is_protected' => true, 'sort_order' => 20],
            ['name' => 'Categorie', 'slug' => 'categories', 'path' => 'categories', 'is_protected' => true, 'sort_order' => 30],
            ['name' => 'Hero', 'slug' => 'heroes', 'path' => 'heroes', 'is_protected' => true, 'sort_order' => 40],
            ['name' => 'Turing', 'slug' => 'turing', 'path' => 'turing', 'is_protected' => true, 'sort_order' => 50],
            ['name' => 'AI e diritto', 'slug' => 'ai-law', 'path' => 'articles/ai-law', 'parent' => 'articles', 'sort_order' => 10],
            ['name' => 'Copertine', 'slug' => 'covers', 'path' => 'articles/covers', 'parent' => 'articles', 'sort_order' => 20],
            ['name' => 'Spazio', 'slug' => 'space', 'path' => 'articles/space', 'parent' => 'articles', 'sort_order' => 30],
            ['name' => 'Tecnologia e società', 'slug' => 'technology-society', 'path' => 'articles/technology-society', 'parent' => 'articles', 'sort_order' => 40],
            ['name' => 'Background', 'slug' => 'backgrounds', 'path' => 'turing/backgrounds', 'parent' => 'turing', 'sort_order' => 10],
            ['name' => 'Bletchley Park', 'slug' => 'bletchley', 'path' => 'turing/bletchley', 'parent' => 'turing', 'sort_order' => 20],
            ['name' => 'Bombe', 'slug' => 'bombe', 'path' => 'turing/bombe', 'parent' => 'turing', 'sort_order' => 30],
            ['name' => 'Documenti', 'slug' => 'documents', 'path' => 'turing/documents', 'parent' => 'turing', 'sort_order' => 40],
            ['name' => 'Enigma', 'slug' => 'enigma', 'path' => 'turing/enigma', 'parent' => 'turing', 'sort_order' => 50],
            ['name' => 'Hero', 'slug' => 'hero', 'path' => 'turing/hero', 'parent' => 'turing', 'sort_order' => 60],
            ['name' => 'IA moderna', 'slug' => 'modern-ai', 'path' => 'turing/modern-ai', 'parent' => 'turing', 'sort_order' => 70],
            ['name' => 'Persone', 'slug' => 'people', 'path' => 'turing/people', 'parent' => 'turing', 'sort_order' => 80],
            ['name' => 'Ritratti', 'slug' => 'portraits', 'path' => 'turing/portraits', 'parent' => 'turing', 'sort_order' => 90],
            ['name' => 'Test di Turing', 'slug' => 'turing-test', 'path' => 'turing/turing-test', 'parent' => 'turing', 'sort_order' => 100],
            ['name' => 'Macchina universale', 'slug' => 'universal-machine', 'path' => 'turing/universal-machine', 'parent' => 'turing', 'sort_order' => 110],
        ];

        foreach ($definitions as $definition) {
            $parent = isset($definition['parent'])
                ? MediaFolder::where('path', $definition['parent'])->firstOrFail()
                : null;

            unset($definition['parent']);
            $service->upsertDefinition($definition, $parent);
        }
    }
}
