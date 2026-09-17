@extends('layouts.admin')

@section('title', 'Mappa concettuale — Speciale Turing')

@section('content')
<div class="admin-topbar">
    <div>
        <a href="{{ route('admin.turing') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Speciale Turing</a>
        <h1 class="admin-page-title" style="margin-top:.25rem;">Mappa concettuale — Speciale Turing</h1>
    </div>
</div>

<p style="max-width:760px;color:var(--admin-muted);margin-bottom:1.25rem;">
    Strumento interno per l'editor, sola lettura, non una pagina pubblica. Trascrizione della
    mappa dei concetti già redatta in <code>docs/00_Governance/Architettura_Editoriale_v1.0.docx</code>
    (§4 "Mappa dei contenuti", versione 1.0 del 29 luglio 2026): per ogni argomento, il capitolo
    dove va sviluppato in profondità, gli eventuali richiami in altri capitoli, e il livello di
    approfondimento rilevato dai cinque audit tecnico-editoriali alla data del documento. Questa
    colonna è una fotografia editoriale di quella data, non un dato ricalcolato dal testo attuale
    dei capitoli: un editor che aggiorna un capitolo deve aggiornare a mano anche questa mappa.
</p>

@foreach($conceptsByChapter as $chapter => $concepts)
    @continue(empty($concepts))
    <div class="admin-card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:.95rem;margin:0 0 .75rem;text-transform:capitalize;">{{ $chapter }}</h2>
        <table class="admin-table" style="font-size:.85rem;">
            <thead>
                <tr>
                    <th>Argomento</th>
                    <th>Richiami</th>
                    <th>Livello di approfondimento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($concepts as $concept)
                    <tr>
                        <td>{{ $concept['argomento'] }}</td>
                        <td>
                            @if(empty($concept['richiami']))
                                <span style="color:var(--admin-muted);">—</span>
                            @else
                                {{ implode(' · ', array_map(
                                    fn (array $r) => ucfirst($r['capitolo']).' ('.$r['qualificatore'].')',
                                    $concept['richiami']
                                )) }}
                            @endif
                        </td>
                        <td>{{ $concept['livello_approfondimento'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endsection
