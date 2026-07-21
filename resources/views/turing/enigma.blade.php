@extends('layouts.app')

@php
    $page = \App\Models\SpecialPage::where('slug', 'turing')->first();
    $content = ($page && $page->is_active) ? ($page->content ?? []) : [];
    $blocks = collect($content['editorial_blocks'] ?? []);
    $enigmaBlock = $blocks->firstWhere('key', 'enigma') ?? [];

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

    $heroImage = $enigmaBlock['background_image']
        ?? $enigmaBlock['image']
        ?? 'turing/enigma/turing-enigma-background.webp';

    $panelImage = $enigmaBlock['image']
        ?? $enigmaBlock['background_image']
        ?? 'turing/enigma/turing-enigma-panel.webp';
@endphp

@section('title', 'Enigma e Bletchley Park — Quark')
@section(
    'description',
    'La guerra invisibile dei codici: Alan Turing, Bletchley Park, la macchina Enigma e la nascita del calcolo moderno.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">

    <style>
        .enigma-page {
            background: #f8fafc;
            color: #0f172a;
        }

        .enigma-hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 78vh;
            overflow: hidden;
            isolation: isolate;
            color: #fff;
            background: #07111f;
            background-size: cover;
            background-position: center;
        }

        .enigma-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background: linear-gradient(
                90deg,
                rgba(3, 7, 18, .84),
                rgba(3, 7, 18, .58),
                rgba(3, 7, 18, .18)
            );
        }

        .enigma-hero .container {
            position: relative;
            z-index: 1;
        }

        .enigma-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 3rem;
            align-items: center;
            padding-block: 5rem;
        }

        .enigma-kicker {
            display: inline-flex;
            margin: 0 0 1rem;
            color: #67e8f9;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .enigma-hero h1,
        .enigma-section__head h2,
        .enigma-copy h2,
        .enigma-final-card h2 {
            font-family: var(--font-display);
            letter-spacing: -.055em;
        }

        .enigma-hero h1 {
            max-width: 900px;
            margin: 0;
            font-size: clamp(3rem, 7vw, 6.5rem);
            line-height: .9;
        }

        .enigma-lead {
            max-width: 780px;
            margin: 1.5rem 0 0;
            color: rgba(255, 255, 255, .88);
            font-size: clamp(1rem, 2vw, 1.18rem);
            line-height: 1.9;
        }

        .enigma-meta,
        .enigma-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
        }

        .enigma-meta {
            margin-top: 2rem;
        }

        .enigma-chip {
            padding: .55rem .85rem;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            backdrop-filter: blur(12px);
            color: #e0f2fe;
            font-size: .78rem;
            font-weight: 800;
        }

        .enigma-machine-card {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 30px;
            background: rgba(255, 255, 255, .12);
            backdrop-filter: blur(18px);
            box-shadow: 0 30px 90px rgba(0, 0, 0, .28);
        }

        .enigma-machine-card__image,
        .enigma-visual {
            background-size: cover;
            background-position: center;
        }

        .enigma-machine-card__image {
            min-height: 340px;
            background-color: #0f172a;
        }

        .enigma-machine-card__body {
            padding: 1.2rem;
        }

        .enigma-machine-card strong {
            display: block;
            color: #fff;
        }

        .enigma-machine-card span {
            display: block;
            margin-top: .35rem;
            color: rgba(255, 255, 255, .72);
            font-size: .9rem;
            line-height: 1.6;
        }

        .enigma-section {
            padding-block: 5rem;
        }

        .enigma-section--white {
            background: #fff;
        }

        .enigma-section__head {
            max-width: 900px;
            margin: 0 auto 2.5rem;
            text-align: center;
        }

        .enigma-section__head h2,
        .enigma-copy h2 {
            margin: .6rem 0 0;
            font-size: clamp(2.2rem, 5vw, 4.4rem);
            line-height: 1;
        }

        .enigma-section__head p,
        .enigma-card p,
        .enigma-copy p,
        .enigma-step p {
            color: #475569;
            line-height: 1.8;
        }

        .enigma-section__head p {
            max-width: 760px;
            margin: 1rem auto 0;
            line-height: 1.9;
        }

        .enigma-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.2rem;
        }

        .enigma-card {
            padding: 1.4rem;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        }

        .enigma-card span {
            display: inline-flex;
            margin-bottom: .7rem;
            color: #0f766e;
            font-size: .76rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .enigma-card h3 {
            margin: 0 0 .7rem;
            font-size: 1.35rem;
        }

        .enigma-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .enigma-visual {
            position: relative;
            min-height: 520px;
            overflow: hidden;
            border-radius: 34px;
            background-color: #dbeafe;
            box-shadow: 0 30px 80px rgba(15, 23, 42, .14);
        }

        .enigma-visual::after {
            content: "ENIGMA / ULTRA";
            position: absolute;
            right: 1.2rem;
            bottom: 1.2rem;
            left: 1.2rem;
            padding: 1rem;
            border: 1px solid rgba(15, 23, 42, .1);
            border-radius: 20px;
            background: rgba(255, 255, 255, .78);
            color: #0f766e;
            font-weight: 950;
            letter-spacing: .18em;
            text-align: center;
        }

        .enigma-copy h2 {
            margin-bottom: 1rem;
            line-height: .96;
        }

        .enigma-copy p {
            font-size: 1.04rem;
            line-height: 1.95;
        }

        .enigma-timeline {
            display: grid;
            gap: 1rem;
            max-width: 980px;
            margin: 0 auto;
        }

        .enigma-step {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 1.25rem;
            padding: 1.4rem;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 24px;
            background: rgba(255, 255, 255, .82);
            box-shadow: 0 16px 40px rgba(15, 23, 42, .07);
        }

        .enigma-step strong {
            color: #0f766e;
            font-size: 1.2rem;
        }

        .enigma-step h3 {
            margin: 0 0 .35rem;
        }

        .enigma-step p {
            margin: 0;
        }

        .enigma-code-panel {
            padding: 1.4rem;
            border: 1px solid rgba(103, 232, 249, .18);
            border-radius: 28px;
            background: #020617;
            box-shadow: 0 24px 70px rgba(2, 6, 23, .3);
            color: #d1fae5;
            font-family: monospace;
            line-height: 1.8;
        }

        .enigma-final {
            background: #07111f;
            color: #fff;
        }

        .enigma-final-card {
            max-width: 980px;
            margin: 0 auto;
            padding: 4rem;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 34px;
            background: linear-gradient(
                135deg,
                rgba(14, 116, 144, .28),
                rgba(15, 23, 42, .88)
            );
            box-shadow: 0 30px 90px rgba(0, 0, 0, .24);
            text-align: center;
        }

        .enigma-final-card h2 {
            margin: .6rem 0 1rem;
            font-size: clamp(2.2rem, 5vw, 4rem);
            line-height: 1;
        }

        .enigma-final-card p {
            max-width: 760px;
            margin: 0 auto;
            color: rgba(255, 255, 255, .78);
            line-height: 1.9;
        }

        .enigma-actions {
            justify-content: center;
            margin-top: 2rem;
        }

        .enigma-actions a {
            padding: 1rem 1.3rem;
            border-radius: 16px;
            font-weight: 900;
            text-decoration: none;
        }

        .enigma-actions a:first-child {
            background: #67e8f9;
            color: #001018;
        }

        .enigma-actions a:last-child {
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        @media (max-width: 900px) {
            .enigma-hero-grid,
            .enigma-split {
                grid-template-columns: 1fr;
            }

            .enigma-grid {
                grid-template-columns: 1fr;
            }

            .enigma-step {
                grid-template-columns: 1fr;
            }

            .enigma-machine-card__image,
            .enigma-visual {
                min-height: 320px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="enigma-page">
        <section class="enigma-hero" style="{{ $bg($heroImage) }}">
            <div class="container container--wide">
                <div class="enigma-hero-grid">
                    <div>
                        <p class="enigma-kicker">
                            {{ $enigmaBlock['kicker'] ?? 'Bletchley Park / Ultra' }}
                        </p>

                        <h1>
                            {{ $enigmaBlock['title'] ?? 'Enigma, Ultra e la guerra invisibile.' }}
                        </h1>

                        <p class="enigma-lead">
                            {{ $enigmaBlock['text'] ?? 'Durante la Seconda guerra mondiale, Alan Turing contribuì al lavoro di Bletchley Park per decifrare i messaggi tedeschi prodotti dalla macchina Enigma. Quel lavoro accelerò la nascita del calcolo automatico moderno e cambiò per sempre il rapporto tra matematica, sicurezza e tecnologia.' }}
                        </p>

                        <div class="enigma-meta">
                            <span class="enigma-chip">Rotori</span>
                            <span class="enigma-chip">Probabilità</span>
                            <span class="enigma-chip">Bombe</span>
                            <span class="enigma-chip">Ultra</span>
                        </div>
                    </div>

                    <aside class="enigma-machine-card">
                        <div
                            class="enigma-machine-card__image"
                            style="{{ $bg($panelImage) }}"
                        ></div>

                        <div class="enigma-machine-card__body">
                            <strong>Dal cifrario al calcolo</strong>
                            <span>
                                Enigma non fu solo una macchina: fu il problema che
                                obbligò matematica, ingegneria e intuizione a
                                lavorare insieme.
                            </span>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="enigma-section enigma-section--white">
            <div class="container container--wide">
                <div class="enigma-section__head">
                    <p class="enigma-kicker">Il problema</p>
                    <h2>Una guerra combattuta anche con pattern e probabilità.</h2>
                    <p>
                        Ogni messaggio cifrato sembrava una parete opaca. Il lavoro
                        a Bletchley Park fu trasformare quell’opacità in ipotesi
                        verificabili, restringendo ogni giorno milioni di combinazioni
                        possibili.
                    </p>
                </div>

                <div class="enigma-grid">
                    <article class="enigma-card">
                        <span>01</span>
                        <h3>Rotori e chiavi</h3>
                        <p>
                            Enigma combinava rotori, cablaggi e impostazioni
                            giornaliere. La sicurezza non dipendeva da un singolo
                            trucco, ma da uno spazio enorme di configurazioni.
                        </p>
                    </article>

                    <article class="enigma-card">
                        <span>02</span>
                        <h3>Crib e indizi</h3>
                        <p>
                            Gli analisti cercavano frammenti plausibili nei messaggi,
                            piccoli appigli linguistici da trasformare in test logici
                            e meccanici.
                        </p>
                    </article>

                    <article class="enigma-card">
                        <span>03</span>
                        <h3>La Bombe</h3>
                        <p>
                            La macchina progettata dal team di Bletchley automatizzava
                            la ricerca, eliminando rapidamente configurazioni impossibili.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="enigma-section">
            <div class="container container--wide enigma-split">
                <div class="enigma-visual" style="{{ $bg($panelImage) }}"></div>

                <div class="enigma-copy">
                    <p class="enigma-kicker">Il metodo</p>
                    <h2>Non forza bruta, ma intelligenza organizzata.</h2>
                    <p>
                        Il punto non era provare tutto: era capire cosa non poteva
                        essere vero. La genialità stava nel trasformare una montagna
                        di possibilità in una sequenza di esclusioni, accelerata da
                        macchine, turni di lavoro e disciplina matematica.
                    </p>
                    <p>
                        Questa logica anticipa molte idee moderne: filtrare segnali,
                        ridurre lo spazio di ricerca, usare pattern ricorrenti e
                        costruire sistemi capaci di amplificare il ragionamento umano.
                    </p>
                </div>
            </div>
        </section>

        <section class="enigma-section enigma-section--white">
            <div class="container container--wide">
                <div class="enigma-section__head">
                    <p class="enigma-kicker">Dentro Bletchley</p>
                    <h2>Una catena di lavoro, non un atto solitario.</h2>
                    <p>
                        La decrittazione fu un ecosistema: intercettazioni,
                        catalogazione, ipotesi linguistiche, macchine, verifiche
                        e distribuzione rapida dell’informazione alle strutture
                        militari.
                    </p>
                </div>

                <div class="enigma-timeline">
                    <div class="enigma-step">
                        <strong>Segnale</strong>
                        <div>
                            <h3>Il messaggio arriva cifrato</h3>
                            <p>
                                Le comunicazioni radio venivano intercettate e
                                passate agli analisti con urgenza crescente.
                            </p>
                        </div>
                    </div>

                    <div class="enigma-step">
                        <strong>Ipotesi</strong>
                        <div>
                            <h3>Si cercano pattern possibili</h3>
                            <p>
                                Formule ricorrenti, abitudini operative e contesto
                                militare fornivano indizi da testare.
                            </p>
                        </div>
                    </div>

                    <div class="enigma-step">
                        <strong>Macchina</strong>
                        <div>
                            <h3>La Bombe restringe le chiavi</h3>
                            <p>
                                L’automazione riduceva drasticamente il numero di
                                configurazioni compatibili con gli indizi.
                            </p>
                        </div>
                    </div>

                    <div class="enigma-step">
                        <strong>Ultra</strong>
                        <div>
                            <h3>L’informazione diventa vantaggio</h3>
                            <p>
                                Il valore non era solo decifrare, ma far arrivare
                                il risultato in tempo utile senza rivelare la fonte.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="enigma-section">
            <div class="container container--wide enigma-split">
                <div class="enigma-copy">
                    <p class="enigma-kicker">Eredità</p>
                    <h2>Dalla crittografia alla cybersecurity.</h2>
                    <p>
                        Il lavoro su Enigma è una delle radici culturali della sicurezza
                        informatica contemporanea. Oggi parliamo di cifratura, attacchi,
                        modelli, chiavi e automazione con un linguaggio che deve molto
                        a quella stagione.
                    </p>
                </div>

                <div class="enigma-code-panel">
                    ENIGMA ROTOR ANALYSIS...<br>
                    CRIB MATCH FOUND...<br>
                    CONFIGURATION SPACE REDUCED...<br>
                    PROBABILISTIC MODEL UPDATED...<br>
                    ULTRA PRIORITY ACTIVE...<br>
                    MESSAGE DECRYPTED.
                </div>
            </div>
        </section>

        <section class="enigma-section enigma-final">
            <div class="container container--wide">
                <div class="enigma-final-card">
                    <p class="enigma-kicker">Prossima tappa</p>
                    <h2>
                        La domanda si sposta dalle macchine che decifrano alle
                        macchine che pensano.
                    </h2>
                    <p>
                        Dopo Enigma, il percorso di Turing porta alla macchina
                        universale, al calcolo automatico e alla domanda che ancora
                        oggi ci accompagna: può una macchina apparire intelligente?
                    </p>

                    <div class="enigma-actions">
                        <a href="{{ route('turing.ai') }}">Vai all’IA moderna</a>
                        <a href="{{ route('turing') }}">Torna allo speciale</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
