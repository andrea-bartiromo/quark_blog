@extends('layouts.admin')
@section('title','Importa Coverage Search Console')
@section('content')
<div class="admin-topbar"><h1 class="admin-page-title">Importa Coverage Search Console</h1><a href="{{ route('admin.search-console-coverage') }}" class="btn">← Salute indicizzazione</a></div>
<p style="color:var(--admin-muted);font-size:.85rem">CSV privato, sola lettura. Formato minimo: <code>Ragione,Sorgente,Convalida,Pagine</code>. Una colonna URL è facoltativa.</p>
<form method="POST" enctype="multipart/form-data" class="admin-form" action="{{ route('admin.search-console-coverage.import') }}">@csrf
<label class="form-label" for="property">Property Search Console</label><input class="form-input" id="property" name="property" required value="{{ old('property') }}" placeholder="sc-domain:kairus.it">
<label class="form-label" for="observed_at" style="margin-top:1rem">Data osservazione</label><input class="form-input" id="observed_at" name="observed_at" type="date" required value="{{ old('observed_at') }}">
<label class="form-label" for="csv" style="margin-top:1rem">File CSV</label><input class="form-input" id="csv" name="csv" type="file" accept=".csv,text/csv,text/plain" required>
@error('csv')<p style="color:#b91c1c">{{ $message }}</p>@enderror
<button class="btn btn--primary" style="margin-top:1rem">Importa</button></form>
@endsection
