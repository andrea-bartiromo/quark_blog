<?php

namespace App\Http\Controllers;

use App\Models\SpecialPage;
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
            'heroBackgroundImage' => $hero['background_image'] ?? 'turing-hero.webp',
            'heroPortraitImage' => $hero['portrait_image'] ?? null,
            'introBackgroundImage' => $intro['background_image'] ?? 'turing-intro.webp',
            'whyBackgroundImage' => $why['background_image'] ?? null,
            'whyPanelImage' => 'turing-legacy-panel.webp',
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
            'enigma' => 'turing/enigma.webp',
            'macchina-universale' => 'turing/universal-machine.webp',
            'test-turing' => 'turing/turing-test.webp',
            'ai-moderna' => 'turing/modern-ai.webp',
        ];
    }

    private function sectionBackgroundFallbacks(): array
    {
        return [
            'enigma' => 'turing-enigma-background.webp',
            'macchina-universale' => 'turing-universal-machine-background.webp',
            'test-turing' => 'turing-test-background.webp',
            'ai-moderna' => 'turing-ai-background.webp',
        ];
    }
}
