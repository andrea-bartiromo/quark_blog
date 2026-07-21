@extends('layouts.admin')

@section('title', 'Speciale Turing')

@section('content')
@php
    $content = $page->content ?? [];

    $hero = $content['hero'] ?? [];
    $homeTeaser = $content['home_teaser'] ?? [];
    $intro = $content['intro'] ?? [];
    $cards = $content['cards'] ?? [];
    $editorialBlocks = $content['editorial_blocks'] ?? [];
    $why = $content['why'] ?? [];
    $final = $content['final'] ?? [];
    $timeline = $content['timeline'] ?? [];
    $internalLinks = $content['internal_links'] ?? [];
    $decorativeImages = $content['decorative_images'] ?? [];

    if (empty($cards)) {
        $cards = [
            [
                'label' => '01 · Bletchley Park',
                'title' => 'La guerra di Enigma',
                'text' => 'Bombe, rotori, messaggi cifrati e una guerra combattuta anche con probabilità e logica.',
                'url' => '/turing/enigma',
                'style' => 'enigma',
                'image' => '',
            ],
            [
                'label' => '02 · Macchine intelligenti',
                'title' => 'Dal Test di Turing agli LLM',
                'text' => 'La domanda “le macchine possono pensare?” riletta nell’epoca dell’IA generativa.',
                'url' => '/turing/ia',
                'style' => 'ai',
                'image' => '',
            ],
            [
                'label' => '03 · Eredità',
                'title' => 'Il genio inquieto',
                'text' => 'La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.',
                'url' => '',
                'style' => 'legacy',
                'image' => '',
            ],
        ];
    }

    if (empty($editorialBlocks)) {
        $editorialBlocks = [
            [
                'enabled' => true,
                'key' => 'enigma',
                'title' => 'Il blocco Enigma',
                'kicker' => 'Enigma',
                'layout' => 'image_left',
                'text' => '',
                'image' => '',
                'background_image' => '',
                'link_label' => '',
                'link_url' => '#enigma',
            ],
            [
                'enabled' => true,
                'key' => 'macchina-universale',
                'title' => 'La macchina universale',
                'kicker' => 'Computazione',
                'layout' => 'image_right',
                'text' => '',
                'image' => '',
                'background_image' => '',
                'link_label' => 'Scopri la macchina universale',
                'link_url' => '/turing/computation',
            ],
            [
                'enabled' => true,
                'key' => 'test-turing',
                'title' => 'Il Test di Turing',
                'kicker' => 'Intelligenza',
                'layout' => 'dark_card',
                'text' => '',
                'image' => '',
                'background_image' => '',
                'link_label' => 'Leggi la domanda',
                'link_url' => '/turing/intelligence',
            ],
            [
                'enabled' => true,
                'key' => 'ai-moderna',
                'title' => 'Da Turing all’intelligenza artificiale contemporanea',
                'kicker' => 'AI moderna',
                'layout' => 'feature_grid',
                'text' => '',
                'image' => '',
                'background_image' => '',
                'link_label' => '',
                'link_url' => '#ai-moderna',
            ],
        ];
    }

    $renderHidden = function (string $name, mixed $value) use (&$renderHidden): string {
        if (is_array($value)) {
            $html = '';

            foreach ($value as $key => $child) {
                $html .= $renderHidden($name . '[' . $key . ']', $child);
            }

            return $html;
        }

        return '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
    };

    $nestedUploadName = fn (string $name, string $suffix): string => preg_replace(
        '/\[([^\]]+)\]$/',
        '[' . $suffix . ']',
        $name
    );

    $imageField = function (
        string $name,
        ?string $value = '',
        string $label = 'Immagine'
    ) use ($nestedUploadName): string {
        $value = (string) $value;
        $asset = $value ? asset('assets/img/' . $value) : null;
        $isNested = str_contains($name, '[');
        $lastKey = $isNested ? trim(substr(strrchr($name, '['), 1), ']') : null;

        $uploadName = $isNested
            ? $nestedUploadName($name, $lastKey . '_upload')
            : $name . '_upload';

        $removeName = $isNested
            ? $nestedUploadName($name, $lastKey . '_remove')
            : $name . '_remove';

        $html = '<div class="form-group turing-media-field">';
        $html .= '<label class="form-label">' . e($label) . '</label>';

        if ($asset) {
            $html .= '<div class="turing-current-image">';
            $html .= '<img src="' . e($asset) . '" alt="">';
            $html .= '<div>';
            $html .= '<strong>Attuale</strong>';
            $html .= '<small>' . e($value) . '</small>';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="' . e($removeName) . '" value="1"> ';
            $html .= 'Rimuovi';
            $html .= '</label>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<input type="file" name="' . e($uploadName) . '" ';
        $html .= 'accept="image/jpeg,image/png,image/webp" class="turing-file-input">';

        $html .= '<input class="form-input" name="' . e($name) . '" ';
        $html .= 'value="' . e($value) . '" maxlength="500" ';
        $html .= 'placeholder="oppure nome file dalla libreria media">';

        $html .= '<a href="' . route('admin.media') . '" ';
        $html .= 'target="_blank" class="turing-media-link">';
        $html .= 'Apri libreria media →';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    };
@endphp

<style>
    .turing-lite-shell {
        display: grid;
        grid-template-columns: 220px minmax(0, 1fr);
        gap: 1.25rem;
        align-items: start;
    }

    .turing-lite-nav {
        position: sticky;
        top: 1rem;
        padding: .75rem;
        border: 1px solid var(--admin-border);
        border-radius: 18px;
        background: #fff;
        box-shadow: var(--admin-shadow);
    }

    .turing-lite-nav a {
        display: block;
        padding: .78rem .9rem;
        border-radius: 12px;
        color: #334155;
        font-weight: 850;
        text-decoration: none;
    }

    .turing-lite-nav a:hover {
        background: #ecfeff;
        color: #0f766e;
    }

    .turing-lite-card {
        margin-bottom: 1rem;
        padding: 1.25rem;
        border: 1px solid var(--admin-border);
        border-radius: 20px;
        background: #fff;
        box-shadow: var(--admin-shadow);
    }

    .turing-lite-card h2 {
        margin: 0 0 .35rem;
        font-size: 1.15rem;
    }

    .turing-lite-hint {
        margin: 0 0 1.15rem;
        color: var(--admin-muted);
        line-height: 1.7;
    }

    .turing-lite-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .turing-current-image {
        display: grid;
        grid-template-columns: 130px 1fr;
        gap: .75rem;
        align-items: center;
        margin-bottom: .6rem;
        padding: .65rem;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #f8fafc;
    }

    .turing-current-image img {
        width: 130px;
        height: 82px;
        border-radius: 10px;
        object-fit: cover;
    }

    .turing-current-image small {
        display: block;
        margin: .15rem 0 .35rem;
        color: #64748b;
        word-break: break-all;
    }

    .turing-current-image label {
        color: #b91c1c;
        font-size: .78rem;
        font-weight: 800;
    }

    .turing-file-input {
        display: block;
        width: 100%;
        margin-bottom: .55rem;
        padding: .75rem;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
    }

    .turing-media-link {
        display: inline-block;
        margin-top: .35rem;
        color: #0f766e;
        font-size: .78rem;
        font-weight: 800;
        text-decoration: none;
    }

    .turing-savebar {
        position: sticky;
        bottom: 1rem;
        z-index: 10;
        display: flex;
        justify-content: flex-end;
        gap: .75rem;
        padding: 1rem;
        border: 1px solid var(--admin-border);
        border-radius: 16px;
        background: rgba(255, 255, 255, .96);
        box-shadow: var(--admin-shadow);
    }

    .turing-lite-note {
        margin-bottom: 1rem;
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #f8fafc;
        color: #64748b;
        line-height: 1.7;
    }

    @media (max-width: 980px) {
        .turing-lite-shell {
            grid-template-columns: 1fr;
        }

        .turing-lite-nav {
            position: static;
        }

        .turing-lite-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-topbar">
    <div>
        <h1 class="admin-page-title">Speciale Turing</h1>
        <p style="margin: .35rem 0 0; color: var(--admin-muted); font-size: .9rem;">
            Dashboard essenziale: testi principali e immagini.
            Il longform resta nel codice.
        </p>
    </div>

    <a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">
        Apri /turing
    </a>
</div>

@if($errors->any())
    <div class="admin-alert admin-alert--danger">
        <strong>Controlla i campi:</strong>
        <ul style="margin: .5rem 0 0 1.1rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form
    method="POST"
    action="{{ route('admin.turing.update') }}"
    enctype="multipart/form-data"
>
    @csrf

    <div class="turing-lite-shell">
        <nav class="turing-lite-nav" aria-label="Sezioni dashboard Turing">
            <a href="#home">Homepage</a>
            <a href="#hero">Hero</a>
            <a href="#intro">Introduzione</a>
            <a href="#features">Enigma / IA</a>
            <a href="#settings">Impostazioni</a>
        </nav>

        <div>
            <section id="home" class="turing-lite-card">
                <h2>Box Turing in homepage</h2>
                <p class="turing-lite-hint">
                    Titolo, testo e immagine del box mostrato nella homepage.
                </p>

                <div class="form-group">
                    <label class="form-label">Titolo</label>
                    <input
                        class="form-input"
                        name="home_teaser_title"
                        value="{{ old('home_teaser_title', $homeTeaser['title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.') }}"
                        maxlength="180"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Descrizione</label>
                    <textarea
                        class="form-textarea"
                        name="home_teaser_text"
                        maxlength="700"
                    >{{ old('home_teaser_text', $homeTeaser['text'] ?? '') }}</textarea>
                </div>

                {!! $imageField(
                    'home_teaser_background_image',
                    old('home_teaser_background_image', $homeTeaser['background_image'] ?? ''),
                    'Immagine box homepage'
                ) !!}

                <input
                    type="hidden"
                    name="home_teaser_kicker"
                    value="{{ $homeTeaser['kicker'] ?? 'Special Project' }}"
                >
                <input
                    type="hidden"
                    name="home_teaser_cta_label"
                    value="{{ $homeTeaser['cta_label'] ?? 'Entra nella Turing Experience' }}"
                >
                <input
                    type="hidden"
                    name="home_teaser_terminal_title"
                    value="{{ $homeTeaser['terminal_title'] ?? 'TURING ARCHIVE' }}"
                >

                @foreach(($homeTeaser['terminal_lines'] ?? [
                    'ENIGMA SIGNAL FOUND',
                    'MACHINE INTELLIGENCE: ACTIVE',
                    'QUESTION: CAN MACHINES THINK?',
                    'STATUS: STILL OPEN',
                ]) as $line)
                    <input
                        type="hidden"
                        name="home_teaser_terminal_lines[]"
                        value="{{ $line }}"
                    >
                @endforeach
            </section>

            <section id="hero" class="turing-lite-card">
                <h2>Hero pagina /turing</h2>
                <p class="turing-lite-hint">
                    Testo principale e immagini della hero. Le CTA restano fisse
                    nel codice.
                </p>

                <div class="form-group">
                    <label class="form-label">Titolo</label>
                    <input
                        class="form-input"
                        name="hero_title"
                        value="{{ old('hero_title', $hero['title'] ?? 'Alan Turing') }}"
                        maxlength="150"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Lead</label>
                    <textarea
                        class="form-textarea"
                        name="hero_lead"
                        maxlength="900"
                    >{{ old('hero_lead', $hero['lead'] ?? '') }}</textarea>
                </div>

                <div class="turing-lite-grid">
                    {!! $imageField(
                        'hero_background_image',
                        old('hero_background_image', $hero['background_image'] ?? ''),
                        'Sfondo hero /turing'
                    ) !!}

                    {!! $imageField(
                        'hero_portrait_image',
                        old('hero_portrait_image', $hero['portrait_image'] ?? ''),
                        'Portrait laterale'
                    ) !!}
                </div>

                <input type="hidden" name="hero_kicker" value="{{ $hero['kicker'] ?? 'Quark Special Project' }}">
                <input type="hidden" name="hero_primary_label" value="{{ $hero['primary_label'] ?? 'Esplora Enigma' }}">
                <input type="hidden" name="hero_secondary_label" value="{{ $hero['secondary_label'] ?? 'Vai all’IA moderna' }}">
                <input type="hidden" name="hero_portrait_initials" value="{{ $hero['portrait_initials'] ?? 'AT' }}">
                <input type="hidden" name="hero_portrait_years" value="{{ $hero['portrait_years'] ?? '1912 / 1954' }}">
                <input type="hidden" name="hero_portrait_title" value="{{ $hero['portrait_title'] ?? 'Alan Mathison Turing' }}">
                <input type="hidden" name="hero_portrait_text" value="{{ $hero['portrait_text'] ?? '1912–1954 · Matematico, logico, pioniere dell’informatica' }}">
                <input type="hidden" name="hero_terminal_title" value="{{ $hero['terminal_title'] ?? 'TURING ARCHIVE' }}">

                @foreach(($hero['terminal_lines'] ?? [
                    'ENIGMA SIGNAL FOUND',
                    'MACHINE INTELLIGENCE: ACTIVE',
                    'QUESTION: CAN MACHINES THINK?',
                    'STATUS: STILL OPEN',
                ]) as $line)
                    <input
                        type="hidden"
                        name="hero_terminal_lines[]"
                        value="{{ $line }}"
                    >
                @endforeach
            </section>

            <section id="intro" class="turing-lite-card">
                <h2>Introduzione /turing</h2>
                <p class="turing-lite-hint">
                    Sezione “Il filo rosso / Dalla crittografia alla coscienza
                    artificiale”.
                </p>

                <div class="form-group">
                    <label class="form-label">Titolo</label>
                    <input
                        class="form-input"
                        name="intro_title"
                        value="{{ old('intro_title', $intro['title'] ?? 'Dalla crittografia alla coscienza artificiale') }}"
                        maxlength="180"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Testo</label>
                    <textarea
                        class="form-textarea"
                        name="intro_text"
                        maxlength="900"
                    >{{ old('intro_text', $intro['text'] ?? '') }}</textarea>
                </div>

                {!! $imageField(
                    'intro_background_image',
                    old('intro_background_image', $intro['background_image'] ?? ''),
                    'Sfondo introduzione'
                ) !!}

                <input
                    type="hidden"
                    name="intro_kicker"
                    value="{{ $intro['kicker'] ?? 'Il filo rosso' }}"
                >
            </section>

            <section id="features" class="turing-lite-card">
                <h2>Immagini Enigma / IA</h2>
                <p class="turing-lite-hint">
                    Solo le immagini principali delle pagine longform. Testi e layout
                    restano nel codice.
                </p>

                @foreach($editorialBlocks as $i => $block)
                    @if(in_array($block['key'] ?? '', ['enigma', 'ai-moderna']))
                        <div class="turing-lite-note">
                            <strong>
                                {{ ($block['key'] ?? '') === 'enigma' ? 'Pagina Enigma' : 'Pagina IA moderna' }}
                            </strong>

                            <div class="turing-lite-grid" style="margin-top: .8rem;">
                                {!! $imageField(
                                    "editorial_blocks[$i][image]",
                                    $block['image'] ?? '',
                                    'Immagine principale'
                                ) !!}

                                {!! $imageField(
                                    "editorial_blocks[$i][background_image]",
                                    $block['background_image'] ?? '',
                                    'Sfondo pagina'
                                ) !!}
                            </div>
                        </div>

                        <input type="hidden" name="editorial_blocks[{{ $i }}][enabled]" value="{{ !empty($block['enabled']) ? '1' : '0' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][key]" value="{{ $block['key'] ?? '' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][layout]" value="{{ $block['layout'] ?? 'image_left' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][kicker]" value="{{ $block['kicker'] ?? '' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][title]" value="{{ $block['title'] ?? '' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][text]" value="{{ $block['text'] ?? '' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][link_label]" value="{{ $block['link_label'] ?? '' }}">
                        <input type="hidden" name="editorial_blocks[{{ $i }}][link_url]" value="{{ $block['link_url'] ?? '' }}">
                    @else
                        {!! $renderHidden("editorial_blocks[$i]", $block) !!}
                    @endif
                @endforeach
            </section>

            <section id="settings" class="turing-lite-card">
                <h2>Impostazioni pagina</h2>

                <div class="form-group">
                    <label class="form-label">Titolo CMS</label>
                    <input
                        class="form-input"
                        name="title"
                        value="{{ old('title', $page->title) }}"
                        maxlength="150"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Descrizione SEO</label>
                    <textarea
                        class="form-textarea"
                        name="description"
                        maxlength="500"
                    >{{ old('description', $page->description) }}</textarea>
                </div>

                <label style="display: flex; gap: .65rem; align-items: center; padding: 1rem; border: 1px solid var(--admin-border); border-radius: 12px; background: #f9fafb;">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $page->is_active))
                    >
                    <span>
                        <strong>Pagina attiva</strong>
                        <small style="display: block; color: var(--admin-muted);">
                            Se disattivata, il frontend mostra i fallback.
                        </small>
                    </span>
                </label>
            </section>

            <div style="display: none;">
                {!! $renderHidden('cards', $cards) !!}

                <input type="hidden" name="why_kicker" value="{{ $why['kicker'] ?? 'Perché conta ancora' }}">
                <input type="hidden" name="why_title" value="{{ $why['title'] ?? 'Ogni volta che parliamo di algoritmo, torniamo a Turing.' }}">
                <input type="hidden" name="why_text" value="{{ $why['text'] ?? 'La sua intuizione più potente non fu soltanto costruire macchine, ma immaginare un linguaggio universale per descrivere il calcolo.' }}">
                <input type="hidden" name="why_background_image" value="{{ $why['background_image'] ?? '' }}">

                {!! $renderHidden('why_items', $why['items'] ?? []) !!}

                <input type="hidden" name="final_kicker" value="{{ $final['kicker'] ?? 'Prossima lettura' }}">
                <input type="hidden" name="final_title" value="{{ $final['title'] ?? 'Scegli da dove iniziare' }}">
                <input type="hidden" name="final_text" value="{{ $final['text'] ?? 'Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?' }}">
                <input type="hidden" name="final_background_image" value="{{ $final['background_image'] ?? '' }}">

                {!! $renderHidden('timeline', $timeline) !!}
                {!! $renderHidden('internal_links', $internalLinks) !!}
                {!! $renderHidden('decorative_images', $decorativeImages) !!}
            </div>

            <div class="turing-savebar">
                <a href="{{ route('turing') }}" target="_blank" class="btn btn--secondary">
                    Anteprima
                </a>

                <button type="submit" class="btn btn--primary">
                    Salva speciale Turing
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
