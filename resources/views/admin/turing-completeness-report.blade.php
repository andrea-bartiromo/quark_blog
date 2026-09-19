@extends('layouts.admin')

@section('title', 'Report completezza — Speciale Turing')

@section('content')
<div class="admin-topbar">
    <div>
        <a href="{{ route('admin.turing') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Speciale Turing</a>
        <h1 class="admin-page-title" style="margin-top:.25rem;">Report completezza — Speciale Turing</h1>
    </div>
</div>

<p style="max-width:760px;color:var(--admin-muted);margin-bottom:1.25rem;">
    Strumento interno per l'editor, sola lettura, non una pagina pubblica. Fonti registrate e
    metriche di navigazione sono aggregate dal vero stato attuale del database — mai un'istantanea
    statica. La colonna "Livello di approfondimento" invece <strong>non lo è</strong>: resta la
    stessa fotografia editoriale del 29 luglio 2026 già usata dalla
    <a href="{{ route('admin.turing.concept-map') }}">mappa concettuale</a> — un editor che aggiorna
    un capitolo deve aggiornarla a mano, questo report non la ricalcola dal testo attuale.
    Accessibilità e performance restano fuori da questo report: richiederebbero ri-misurazioni reali
    (axe-core/Lighthouse), non incluse qui — vedi <code>docs/02_Turing_Audit/</code> per l'ultimo
    audit manuale disponibile (29 luglio 2026, non aggiornato automaticamente).
</p>

<div class="admin-card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:.95rem;margin:0 0 .5rem;">Stato di pubblicazione</h2>
    <p style="margin:0;font-size:.85rem;">
        @if($report['chapters_public'])
            <strong>Pubblico.</strong> L'hub e i 5 capitoli sono visibili a chiunque.
        @else
            <strong>Non pubblico.</strong> L'hub mostra la landing "In arrivo" e i capitoli
            reindirizzano fuori — <code>config('turing.chapters_public')</code> è <code>false</code>.
        @endif
    </p>
    @if($report['hub_navigation'])
        <p style="margin:.5rem 0 0;font-size:.8rem;color:var(--admin-muted);">
            Navigazione hub: {{ $report['hub_navigation']['count'] }} visite aggregate
            ({{ $report['hub_navigation']['state'] === 'available' ? 'dato disponibile' : 'dati insufficienti' }},
            {{ $report['hub_navigation']['days_collected'] }} giorni di raccolta).
        </p>
    @endif
</div>

@foreach($report['chapters'] as $chapter => $data)
    <div class="admin-card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:.95rem;margin:0 0 .75rem;text-transform:capitalize;">{{ $chapter }}</h2>

        <p style="margin:0 0 .5rem;font-size:.85rem;">
            Fonti registrate: <strong>{{ $data['sources_count'] }}</strong>
            @if($data['navigation'])
                · Navigazione: <strong>{{ $data['navigation']['count'] }}</strong> visite aggregate
                ({{ $data['navigation']['state'] === 'available' ? 'dato disponibile' : 'dati insufficienti' }})
            @endif
        </p>

        @if(empty($data['concepts_as_main_chapter']))
            <p style="margin:0;font-size:.85rem;color:var(--admin-muted);">
                Nessun concetto della mappa concettuale ha questo capitolo come principale.
            </p>
        @else
            <p style="margin:0 0 .5rem;font-size:.75rem;color:var(--admin-muted);">
                Fotografia editoriale del 29 luglio 2026, non ricalcolata dal testo attuale del
                capitolo (vedi sopra).
            </p>
            <table class="admin-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Concetto (capitolo principale)</th>
                        <th>Livello di approfondimento (29/07/2026)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['concepts_as_main_chapter'] as $concept)
                        <tr>
                            <td>{{ $concept['argomento'] }}</td>
                            <td>{{ $concept['livello_approfondimento'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach
@endsection
