<?php

namespace App\Http\Controllers;

use App\Models\SpecialPage;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TuringPageController extends Controller
{
    public function index(): View
    {
        $page = SpecialPage::where('slug', 'turing')->first();
        $content = ($page && $page->is_active) ? ($page->content ?? []) : [];
        $hero = $content['hero'] ?? [];
        $intro = $content['intro'] ?? [];
        $why = $content['why'] ?? [];
        $final = $content['final'] ?? [];

        return view('turing.index', [
            'page' => $page,
            'content' => $content,
            'hero' => $hero,
            'intro' => $intro,
            'cards' => collect($content['cards'] ?? []),
            'editorialBlocks' => collect($content['editorial_blocks'] ?? []),
            'why' => $why,
            'whyItems' => collect($why['items'] ?? []),
            'timeline' => collect($content['timeline'] ?? []),
            'final' => $final,
            'sectionImageFallbacks' => $this->sectionImageFallbacks(),
            'sectionBackgroundFallbacks' => $this->sectionBackgroundFallbacks(),
            'heroBackgroundImage' => $hero['background_image'] ?? 'turing-hero-bg.jpg',
            'heroPortraitImage' => $hero['portrait_image'] ?? null,
            'introBackgroundImage' => $intro['background_image'] ?? 'turing-intro-bg.jpg',
            'whyBackgroundImage' => $why['background_image'] ?? null,
            'whyPanelImage' => 'turing-legacy-panel.jpg',
            'finalBackgroundImage' => $final['background_image'] ?? null,
            'terminalLines' => collect($hero['terminal_lines'] ?? [
                'ENIGMA SIGNAL FOUND',
                'MACHINE INTELLIGENCE: ACTIVE',
                'QUESTION: CAN MACHINES THINK?',
                'STATUS: STILL OPEN',
            ]),
        ]);
    }

    private function sectionImageFallbacks(): array
    {
        return [
            'enigma' => 'turing-enigma-panel.jpg',
            'macchina-universale' => 'turing-universal-machine-panel.jpg',
            'test-turing' => 'turing-test-panel.jpg',
            'ai-moderna' => 'turing-ai-panel.jpg',
        ];
    }

    private function sectionBackgroundFallbacks(): array
    {
        return [
            'enigma' => 'turing-enigma-bg.jpg',
            'macchina-universale' => 'turing-universal-machine-bg.jpg',
            'test-turing' => 'turing-test-bg.jpg',
            'ai-moderna' => 'turing-ai-bg.jpg',
        ];
    }
}
