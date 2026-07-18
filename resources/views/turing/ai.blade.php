@extends('layouts.app')

@php
    $page = \App\Models\SpecialPage::where('slug', 'turing')->first();
    $content = ($page && $page->is_active) ? ($page->content ?? []) : [];
    $blocks = collect($content['editorial_blocks'] ?? []);
    $aiBlock = $blocks->firstWhere('key', 'ai-moderna') ?? [];

    $img = function (?string $value): ?string {
        if (empty($value)) {
            return null;
        }

        return str_starts_with($value, 'http') || str_starts_with($value, '/')
            ? $value
            : asset('assets/img/' . $value);
    };

    $bg = fn (?string $value): string => $img($value)
        ? "background-image:url('" . $img($value) . "')"
        : '';

    $heroImage = $aiBlock['background_image']
        ?? $aiBlock['image']
        ?? 'turing-ai-background.webp';

    $panelImage = $aiBlock['image']
        ?? $aiBlock['background_image']
        ?? 'turing-ai-panel.webp';
@endphp

@section('title', 'Turing e l’IA moderna — Quark')
@section(
    'description',
    'Dal Test di Turing ai modelli linguistici contemporanei: come l’idea di intelligenza artificiale è diventata il centro del presente.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">

    <style>
        .ai-page {
            overflow: hidden;
            background: #020617;
            color: #e2e8f0;
        }

        .ai-hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 82vh;
            isolation: isolate;
            background: #020617;
            background-size: cover;
            background-position: center;
        }

        .ai-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(circle at top left, rgba(34, 211, 238, .22), transparent 35%),
                linear-gradient(90deg, rgba(2, 6, 23, .9), rgba(2, 6, 23, .66), rgba(2, 6, 23, .2));
        }

        .ai-hero .container {
            position: relative;
            z-index: 1;
        }

        .ai-grid,
        .ai-split {
            display: grid;
            gap: 3rem;
            align-items: center;
        }

        .ai-grid {
            grid-template-columns: minmax(0, 1fr) 390px;
            padding-block: 5rem;
        }

        .ai-split {
            grid-template-columns: 1fr 1fr;
        }

        .ai-kicker {
            display: inline-flex;
            margin-bottom: 1rem;
            color: #67e8f9;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .ai-hero h1,
        .ai-section-head h2,
        .ai-copy h2,
        .ai-final-card h2,
        .ai-big-title {
            font-family: var(--font-display);
            letter-spacing: -.055em;
        }

        .ai-hero h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(3rem, 7vw, 6.4rem);
            line-height: .9;
        }

        .ai-lead {
            max-width: 760px;
            margin-top: 1.5rem;
            color: rgba(226, 232, 240, .84);
            font-size: clamp(1rem, 2vw, 1.18rem);
            line-height: 1.95;
        }

        .ai-tags,
        .ai-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
        }

        .ai-tags {
            margin-top: 2rem;
        }

        .ai-tags span {
            padding: .6rem .9rem;
            border: 1px solid rgba(103, 232, 249, .18);
            border-radius: 999px;
            background: rgba(15, 23, 42, .62);
            backdrop-filter: blur(12px);
            color: #cffafe;
            font-size: .78rem;
            font-weight: 800;
        }

        .ai-panel {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 34px;
            background: rgba(15, 23, 42, .76);
            box-shadow: 0 30px 90px rgba(0, 0, 0, .36);
        }

        .ai-panel__image,
        .ai-visual {
            background-size: cover;
            background-position: center;
        }

        .ai-panel__image {
            min-height: 360px;
            background-color: #0f172a;
        }

        .ai-panel__body {
            padding: 1.3rem;
        }

        .ai-panel strong {
            display: block;
            color: #fff;
            font-size: 1.05rem;
        }

        .ai-panel p {
            margin: .55rem 0 0;
            color: #94a3b8;
            line-height: 1.8;
        }

        .ai-section {
            position: relative;
            padding-block: 5rem;
        }

        .ai-section--light {
            background: #f8fafc;
            color: #0f172a;
        }

        .ai-section-head {
            max-width: 920px;
            margin: 0 auto 2.8rem;
            text-align: center;
        }

        .ai-section-head h2,
        .ai-copy h2,
        .ai-big-title {
            margin: .7rem 0 0;
            font-size: clamp(2.2rem, 5vw, 4.4rem);
            line-height: 1;
        }

        .ai-section-head p {
            max-width: 760px;
            margin: 1rem auto 0;
            color: #64748b;
            line-height: 1.95;
        }

        .ai-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.2rem;
        }

        .ai-card {
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 28px;
            background: rgba(15, 23, 42, .86);
            box-shadow: 0 18px 60px rgba(0, 0, 0, .24);
        }

        .ai-section--light .ai-card {
            border-color: rgba(15, 23, 42, .08);
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        }

        .ai-card span {
            display: inline-flex;
            margin-bottom: .75rem;
            color: #67e8f9;
            font-size: .74rem;
            font-weight: 900;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .ai-section--light .ai-card span,
        .ai-light-kicker {
            color: #0f766e;
        }

        .ai-card h3 {
            margin: 0 0 .75rem;
            font-size: 1.32rem;
        }

        .ai-card p {
            color: #94a3b8;
            line-height: 1.85;
        }

        .ai-section--light .ai-card p,
        .ai-light-text {
            color: #475569;
        }

        .ai-copy h2 {
            margin-bottom: 1rem;
            line-height: .95;
        }

        .ai-copy p {
            color: #cbd5e1;
            font-size: 1.04rem;
            line-height: 1.95;
        }

        .ai-light-copy {
            color: #0f172a;
        }

        .ai-light-copy h2,
        .ai-big-title {
            color: #0f172a;
        }

        .ai-visual {
            position: relative;
            min-height: 540px;
            overflow: hidden;
            border-radius: 34px;
            background-color: #0f172a;
            box-shadow: 0 30px 90px rgba(0, 0, 0, .35);
        }

        .ai-visual::after {
            content: "NEURAL SYSTEM / LANGUAGE MODEL";
            position: absolute;
            right: 1.2rem;
            bottom: 1.2rem;
            left: 1.2rem;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 18px;
            background: rgba(2, 6, 23, .72);
            backdrop-filter: blur(12px);
            color: #67e8f9;
            font-size: .78rem;
            font-weight: 900;
            letter-spacing: .18em;
            text-align: center;
        }

        .ai-flow {
            display: grid;
            gap: 1rem;
            max-width: 980px;
            margin: 0 auto;
        }

        .ai-step {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1.2rem;
            padding: 1.45rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 26px;
            background: rgba(255, 255, 255, .04);
            backdrop-filter: blur(14px);
        }

        .ai-step strong {
            color: #67e8f9;
            font-size: 1.18rem;
        }

        .ai-step h3 {
            margin: 0 0 .35rem;
            color: #fff;
        }

        .ai-step p {
            margin: 0;
            color: #94a3b8;
            line-height: 1.85;
        }

        .ai-terminal {
            padding: 1.5rem;
            border: 1px solid rgba(103, 232, 249, .16);
            border-radius: 30px;
            background: #010409;
            box-shadow: 0 30px 90px rgba(0, 0, 0, .42);
            color: #86efac;
            font-family: monospace;
            line-height: 1.85;
        }

        .ai-final {
            background: linear-gradient(180deg, #020617, #0f172a);
        }

        .ai-final-card {
            max-width: 980px;
            margin: 0 auto;
            padding: 4rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 36px;
            background: linear-gradient(135deg, rgba(8, 145, 178, .16), rgba(15, 23, 42, .88));
            box-shadow: 0 30px 100px rgba(0, 0, 0, .36);
            text-align: center;
        }

        .ai-final-card h2 {
            margin: .6rem 0 1rem;
            color: #fff;
            font-size: clamp(2.3rem, 5vw, 4.2rem);
            line-height: 1;
        }

        .ai-final-card p {
            max-width: 760px;
            margin: 0 auto;
            color: #cbd5e1;
            line-height: 1.95;
        }

        .ai-actions {
            justify-content: center;
            margin-top: 2rem;
        }

        .ai-actions a {
            padding: 1rem 1.35rem;
            border-radius: 18px;
            font-weight: 900;
            text-decoration: none;
        }

        .ai-actions a:first-child {
            background: #67e8f9;
            color: #001018;
        }

        .ai-actions a:last-child {
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .06);
            color: #fff;
        }

        @media (max-width: 920px) {
            .ai-grid,
            .ai-split {
                grid-template-columns: 1fr;
            }

            .ai-cards {
                grid-template-columns: 1fr;
            }

            .ai-step {
                grid-template-columns: 1fr;
            }

            .ai-panel__image,
            .ai-visual {
                min-height: 320px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="ai-page">
        <section class="ai-hero" style="{{ $bg($heroImage) }}">
            <div class="container container--wide">
                <div class="ai-grid">
                    <div>
                        <p class="ai-kicker">
                            {{ $aiBlock['kicker'] ?? 'AI moderna / Linguaggio' }}
                        </p>

                        <h1>{{ $aiBlock['title'] ?? 'Le macchine possono pensare?' }}</h1>

                        <p class="ai-lead">
                            {{ $aiBlock['text'] ?: 'Nel 1950 Alan Turing pose una domanda destinata a cambiare il futuro della tecnologia. Oggi, nell’epoca dei modelli linguistici, delle reti neurali e dell’intelligenza artificiale generativa, quella domanda è più viva che mai.' }}
                        </p>

                        <div class="ai-tags">
                            <span>LLM</span>
                            <span>Reti neurali</span>
                            <span>ChatGPT</span>
                            <span>Generative AI</span>
                        </div>
                    </div>

                    <aside class="ai-panel">
                        <div
                            class="ai-panel__image"
                            style="{{ $bg($panelImage) }}"
                        ></div>

                        <div class="ai-panel__body">
                            <strong>Dalla macchina universale al linguaggio artificiale</strong>
                            <p>
                                La traiettoria che parte da Turing oggi attraversa
                                modelli generativi, automazione cognitiva e sistemi
                                capaci di produrre testo, immagini e codice.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="ai-section">
            <div class="container container--wide">
                <div class="ai-section-head">
                    <p class="ai-kicker">La domanda</p>
                    <h2>Il Test di Turing non chiedeva cos’è una mente.</h2>
                    <p>
                        La provocazione di Turing era pratica: se una macchina riesce
                        a sostenere una conversazione indistinguibile da quella umana,
                        possiamo trattarla come intelligente?
                    </p>
                </div>

                <div class="ai-cards">
                    <article class="ai-card">
                        <span>01</span>
                        <h3>Il gioco dell’imitazione</h3>
                        <p>
                            Il Test di Turing sposta il dibattito dalla coscienza alla
                            capacità di comportamento linguistico credibile.
                        </p>
                    </article>

                    <article class="ai-card">
                        <span>02</span>
                        <h3>Pattern e probabilità</h3>
                        <p>
                            I modelli moderni non “pensano” come esseri umani:
                            apprendono correlazioni gigantesche tra parole, immagini
                            e sequenze.
                        </p>
                    </article>

                    <article class="ai-card">
                        <span>03</span>
                        <h3>La nuova interfaccia</h3>
                        <p>
                            Per la prima volta milioni di persone parlano ogni giorno
                            con sistemi artificiali attraverso il linguaggio naturale.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="ai-section ai-section--light">
            <div class="container container--wide ai-split">
                <div class="ai-visual" style="{{ $bg($panelImage) }}"></div>

                <div class="ai-copy ai-light-copy">
                    <p class="ai-kicker ai-light-kicker">Dal simbolico al neurale</p>
                    <h2>L’intelligenza artificiale cambia forma.</h2>
                    <p class="ai-light-text">
                        Dalle prime macchine logiche ai modelli neurali contemporanei,
                        l’IA si è evoluta in ondate successive. Oggi i Large Language
                        Models rappresentano una sintesi di dati, calcolo distribuito
                        e apprendimento statistico su scala enorme.
                    </p>
                    <p class="ai-light-text">
                        Questi sistemi non memorizzano semplicemente frasi: costruiscono
                        rappresentazioni probabilistiche del linguaggio e prevedono il
                        token successivo in base al contesto.
                    </p>
                </div>
            </div>
        </section>

        <section class="ai-section">
            <div class="container container--wide">
                <div class="ai-section-head">
                    <p class="ai-kicker">La trasformazione</p>
                    <h2>Dalla ricerca accademica all’infrastruttura del presente.</h2>
                    <p>
                        L’IA non è più un esperimento isolato. È entrata nei motori di
                        ricerca, nella medicina, nella sicurezza, nella scrittura, nel
                        coding e nell’automazione quotidiana.
                    </p>
                </div>

                <div class="ai-flow">
                    <div class="ai-step">
                        <strong>1950</strong>
                        <div>
                            <h3>Turing formula il problema</h3>
                            <p>
                                La conversazione diventa un criterio operativo per
                                discutere di intelligenza artificiale.
                            </p>
                        </div>
                    </div>

                    <div class="ai-step">
                        <strong>1980+</strong>
                        <div>
                            <h3>Machine learning</h3>
                            <p>
                                I sistemi iniziano ad apprendere pattern dai dati invece
                                di seguire solo regole scritte a mano.
                            </p>
                        </div>
                    </div>

                    <div class="ai-step">
                        <strong>2017</strong>
                        <div>
                            <h3>Transformer</h3>
                            <p>
                                Nuove architetture neurali rendono possibile modellare
                                il linguaggio su scala gigantesca.
                            </p>
                        </div>
                    </div>

                    <div class="ai-step">
                        <strong>Oggi</strong>
                        <div>
                            <h3>IA generativa</h3>
                            <p>
                                Testo, immagini, audio e codice vengono creati in tempo
                                reale attraverso modelli multimodali.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ai-section ai-section--light">
            <div class="container container--wide ai-split">
                <div>
                    <p class="ai-kicker ai-light-kicker">Il sistema</p>
                    <h2 class="ai-big-title">
                        Le macchine producono linguaggio, ma anche potere.
                    </h2>
                    <p class="ai-light-text" style="line-height: 1.95;">
                        Automazione cognitiva, disinformazione, bias, copyright,
                        lavoro creativo e sorveglianza: la questione non è più solo
                        tecnica. L’IA è diventata una questione culturale, economica
                        e politica.
                    </p>
                </div>

                <div class="ai-terminal">
                    MODEL INITIALIZED...<br>
                    LANGUAGE VECTOR SPACE READY...<br>
                    TOKEN PREDICTION ACTIVE...<br>
                    MULTIMODAL REASONING ENABLED...<br>
                    HUMAN / MACHINE BOUNDARY UNCERTAIN.
                </div>
            </div>
        </section>

        <section class="ai-section ai-final">
            <div class="container container--wide">
                <div class="ai-final-card">
                    <p class="ai-kicker">Turing Experience</p>
                    <h2>
                        La domanda di Turing non è finita: si è solo spostata nel presente.
                    </h2>
                    <p>
                        Ogni chatbot, ogni modello generativo e ogni sistema conversazionale
                        riapre la stessa questione: cosa significa davvero “intelligenza”
                        quando il linguaggio può essere simulato?
                    </p>

                    <div class="ai-actions">
                        <a href="{{ route('turing') }}">Torna allo speciale</a>
                        <a href="{{ route('turing.enigma') }}">Rivedi Enigma</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
