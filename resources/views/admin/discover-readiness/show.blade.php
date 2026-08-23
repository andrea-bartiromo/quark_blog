@extends('layouts.admin')
@section('title','Idoneità Discover')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Idoneità Google Discover</h1>
  <a href="{{ route('admin.articles.edit', $article) }}" class="action-btn">← Torna all'articolo</a>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:.35rem;">
  <strong>{{ $article->title }}</strong>
</p>
<p style="color:var(--admin-faint);font-size:.78rem;margin-bottom:1.25rem;max-width:70ch;">
  Verifica dei prerequisiti tecnici ed editoriali pubblicamente noti per Google Discover.
  Questo audit non contatta Google, non consulta la Search Console e non garantisce né
  promette che l'articolo verrà mostrato in Discover: quella decisione dipende da segnali
  editoriali e algoritmici fuori dal controllo del sito.
</p>

<div style="overflow-x:auto;">
  <table class="admin-table">
    <thead>
      <tr>
        <th scope="col">Controllo</th>
        <th scope="col">Esito</th>
        <th scope="col">Spiegazione</th>
      </tr>
    </thead>
    <tbody>
      @foreach($checks as $check)
        <tr>
          <td>{{ $check['label'] }}</td>
          <td><span class="status status--{{ strtolower($check['status']) }}">{{ $check['status'] }}</span></td>
          <td style="max-width:520px;font-size:.82rem;color:var(--admin-ink-soft);">{{ $check['reason'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endsection
