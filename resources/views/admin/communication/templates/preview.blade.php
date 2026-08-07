@extends('layouts.admin')
@section('title', 'Anteprima — '.$template->name)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.templates.show', $template) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $template->name }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">Anteprima — v{{ $version->version_number }}</h1>
  </div>
</div>

<p style="color:#9ca3af;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.5rem;">Solo rendering nel browser: nessuna email viene inviata da questa pagina.</p>

<div class="admin-card" style="max-width:640px;">
  <div style="border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto</div>
    <div style="font-weight:700;font-size:1.05rem;margin-top:.25rem;">{{ $version->subject ?: '(nessun oggetto)' }}</div>
  </div>
  <div style="border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Preheader</div>
    <div style="color:#6b7280;margin-top:.25rem;">{{ $version->preheader ?: '—' }}</div>
  </div>
  <div>
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:.5rem;">Corpo</div>
    <div style="white-space:pre-line;line-height:1.6;">{{ $version->content['body'] ?? '(nessun contenuto)' }}</div>
  </div>
</div>

@endsection
