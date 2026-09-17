@extends('layouts.admin')

@section('title', 'Fonti per capitolo — Speciale Turing')

@section('content')
<div class="admin-topbar">
    <div>
        <a href="{{ route('admin.turing') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Speciale Turing</a>
        <h1 class="admin-page-title" style="margin-top:.25rem;">Fonti per capitolo — Speciale Turing</h1>
    </div>
</div>

<p style="max-width:760px;color:var(--admin-muted);margin-bottom:1.25rem;">
    Registro delle fonti/citazioni per ciascun capitolo dello Speciale, gestito dall'editor.
    <code>docs/00_Governance/Architettura_Editoriale_v1.0.docx</code> §7 raccomanda almeno una
    citazione attribuita per pagina (es. il saggio «On Computable Numbers» del 1936 per Computation,
    le scuse pubbliche del 2009 per Legacy) — questo strumento non aggiunge da solo alcuna fonte:
    parte vuoto, l'editor decide cosa citare. Le fonti aggiunte qui compaiono in una sezione «Fonti»
    in fondo alla pagina pubblica del capitolo corrispondente, solo quando lo Speciale è pubblico.
</p>

@if($errors->any())
    <div class="admin-card" style="margin-bottom:1.25rem;border-color:#fca5a5;background:#fef2f2;">
        @foreach($errors->all() as $error)
            <p style="margin:0;color:#991b1b;">{{ $error }}</p>
        @endforeach
    </div>
@endif

@if(session('success'))
    <div class="admin-card" style="margin-bottom:1.25rem;border-color:#86efac;background:#f0fdf4;">
        <p style="margin:0;color:#166534;">{{ session('success') }}</p>
    </div>
@endif

<div class="admin-card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:.95rem;margin:0 0 .75rem;">Aggiungi fonte</h2>
    <form method="POST" action="{{ route('admin.turing.chapter-sources.store') }}" style="display:grid;gap:.75rem;grid-template-columns:repeat(4, minmax(0, 1fr));align-items:end;">
        @csrf
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="chapter">Capitolo</label>
            <select class="form-select" id="chapter" name="chapter" required>
                @foreach($chapters as $chapter)
                    <option value="{{ $chapter }}" @selected(old('chapter') === $chapter)>{{ ucfirst($chapter) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="label">Titolo/etichetta</label>
            <input class="form-input" type="text" id="label" name="label" value="{{ old('label') }}" maxlength="255" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="url">URL</label>
            <input class="form-input" type="url" id="url" name="url" value="{{ old('url') }}" maxlength="500" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="year">Anno (opzionale)</label>
            <input class="form-input" type="text" id="year" name="year" value="{{ old('year') }}" maxlength="20">
        </div>
        <div style="grid-column:1/-1;">
            <button type="submit" class="btn btn--primary">Aggiungi</button>
        </div>
    </form>
</div>

@foreach($chapters as $chapter)
    <div class="admin-card" style="margin-bottom:1.5rem;">
        <h2 style="font-size:.95rem;margin:0 0 .75rem;text-transform:capitalize;">{{ $chapter }}</h2>

        @php($sources = $sourcesByChapter->get($chapter, collect()))

        @if($sources->isEmpty())
            <p style="color:var(--admin-muted);margin:0;">Nessuna fonte registrata.</p>
        @else
            <table class="admin-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Etichetta</th>
                        <th>URL</th>
                        <th>Anno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sources as $source)
                        <tr>
                            <td>{{ $source->label }}</td>
                            <td><a href="{{ $source->url }}" target="_blank" rel="noopener">{{ $source->url }}</a></td>
                            <td>{{ $source->year ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.turing.chapter-sources.destroy', $source) }}" onsubmit="return confirm('Rimuovere questa fonte?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm">Rimuovi</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach
@endsection
