@extends('layouts.admin')
@section('title','Diagnostica ricerca')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Diagnostica ricerca</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
  Query digitate in /ricerca che non hanno mai restituito alcun articolo pubblicato — un possibile
  segnale di contenuto mancante. Nessun identificativo di visitatore è mai registrato: solo il testo
  della query, in forma normalizzata, con un conteggio di quante volte è fallita.
</p>

@if($queries->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🔍</p>
    <p>Nessuna ricerca a zero risultati registrata ancora.</p>
    <p class="articles-empty-state__hint">
      Le voci compaiono qui non appena un lettore digita una query in /ricerca che non trova alcun articolo.
    </p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Query</th>
          <th scope="col">Occorrenze</th>
          <th scope="col">Ultima volta</th>
        </tr>
      </thead>
      <tbody>
        @foreach($queries as $row)
          <tr>
            <td>{{ $row->normalized_query }}</td>
            <td>{{ number_format($row->hit_count) }}</td>
            <td>{{ $row->updated_at?->diffForHumans() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
