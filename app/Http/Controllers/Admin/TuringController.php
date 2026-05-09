<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialPage;
use Illuminate\Http\Request;

class TuringController extends Controller
{
    public function edit()
    {
        $page = SpecialPage::firstOrCreate(
            ['slug' => 'turing'],
            [
                'title' => 'Alan Turing',
                'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
                'is_active' => true,
                'content' => []
            ]
        );

        return view('admin.turing', compact('page'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|max:150',
            'description' => 'nullable|max:500',
            'is_active' => 'nullable|boolean',

            'hero_kicker' => 'nullable|max:120',
            'hero_title' => 'required|max:150',
            'hero_lead' => 'required|max:900',
            'hero_primary_label' => 'nullable|max:80',
            'hero_secondary_label' => 'nullable|max:80',
            'hero_portrait_title' => 'nullable|max:150',
            'hero_portrait_text' => 'nullable|max:220',

            'intro_kicker' => 'nullable|max:120',
            'intro_title' => 'required|max:180',
            'intro_text' => 'required|max:900',

            'why_kicker' => 'nullable|max:120',
            'why_title' => 'required|max:180',
            'why_text' => 'required|max:1000',

            'final_kicker' => 'nullable|max:120',
            'final_title' => 'required|max:180',
            'final_text' => 'required|max:500',

            'cards' => 'nullable|array',
            'cards.*.label' => 'nullable|max:120',
            'cards.*.title' => 'nullable|max:150',
            'cards.*.text' => 'nullable|max:500',
            'cards.*.url' => 'nullable|max:255',
            'cards.*.style' => 'nullable|in:enigma,ai,legacy',

            'why_items' => 'nullable|array',
            'why_items.*.title' => 'nullable|max:100',
            'why_items.*.text' => 'nullable|max:200',

            'timeline' => 'nullable|array',
            'timeline.*.year' => 'nullable|max:30',
            'timeline.*.title' => 'nullable|max:150',
            'timeline.*.text' => 'nullable|max:600',
        ]);

        $cards = collect($request->input('cards', []))
            ->filter(fn ($item) => filled($item['title'] ?? null))
            ->values()
            ->all();

        $whyItems = collect($request->input('why_items', []))
            ->filter(fn ($item) => filled($item['title'] ?? null))
            ->values()
            ->all();

        $timeline = collect($request->input('timeline', []))
            ->filter(fn ($item) => filled($item['year'] ?? null) || filled($item['title'] ?? null))
            ->values()
            ->all();

        $page = SpecialPage::where('slug', 'turing')->firstOrFail();

        $page->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'content' => [
                'hero' => [
                    'kicker' => $data['hero_kicker'] ?? null,
                    'title' => $data['hero_title'],
                    'lead' => $data['hero_lead'],
                    'primary_label' => $data['hero_primary_label'] ?? 'Esplora Enigma',
                    'secondary_label' => $data['hero_secondary_label'] ?? 'Vai all’IA moderna',
                    'portrait_title' => $data['hero_portrait_title'] ?? null,
                    'portrait_text' => $data['hero_portrait_text'] ?? null,
                ],
                'intro' => [
                    'kicker' => $data['intro_kicker'] ?? null,
                    'title' => $data['intro_title'],
                    'text' => $data['intro_text'],
                ],
                'cards' => $cards,
                'why' => [
                    'kicker' => $data['why_kicker'] ?? null,
                    'title' => $data['why_title'],
                    'text' => $data['why_text'],
                    'items' => $whyItems,
                ],
                'timeline' => $timeline,
                'final' => [
                    'kicker' => $data['final_kicker'] ?? null,
                    'title' => $data['final_title'],
                    'text' => $data['final_text'],
                ],
            ],
        ]);

        return redirect()->route('admin.turing')->with('success', 'Speciale Turing aggiornato.');
    }
}
