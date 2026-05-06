@extends('layouts.admin')
@section('title', isset($user) ? 'Modifica collaboratore' : 'Nuovo collaboratore')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">
    {{ isset($user) ? 'Modifica — '.$user->name : 'Nuovo collaboratore' }}
  </h1>
  <a href="{{ route('admin.collaborators') }}" class="btn btn--outline">← Collaboratori</a>
</div>

@if(!isset($user))
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Verrà generata automaticamente una password temporanea e inviata via email al collaboratore.
</div>
@endif

@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
            padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
</div>
@endif

<div style="max-width:600px;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
    <form method="POST"
          action="{{ isset($user) ? route('admin.collaborators.update', $user) : route('admin.collaborators.store') }}">
      @csrf
      @if(isset($user)) @method('PUT') @endif

      <div class="form-group">
        <label class="form-label">Nome completo *</label>
        <input class="form-input" type="text" name="name"
               value="{{ old('name', $user->name ?? '') }}" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email *</label>
        <input class="form-input" type="email" name="email"
               value="{{ old('email', $user->email ?? '') }}" required>
        @if(!isset($user))
        <small style="font-size:.72rem;color:#6b7280;">
          Le credenziali di accesso verranno inviate a questo indirizzo.
        </small>
        @endif
      </div>

      <div class="form-group">
        <label class="form-label">Bio (max 500 caratteri)</label>
        <textarea class="form-textarea" name="bio"
                  maxlength="500" style="min-height:90px;"
                  placeholder="Breve presentazione del collaboratore...">{{ old('bio', $user->bio ?? '') }}</textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div class="form-group">
          <label class="form-label">Twitter / X</label>
          <input class="form-input" type="text" name="twitter"
                 value="{{ old('twitter', $user->twitter ?? '') }}"
                 placeholder="@handle">
        </div>
        <div class="form-group">
          <label class="form-label">LinkedIn</label>
          <input class="form-input" type="url" name="linkedin"
                 value="{{ old('linkedin', $user->linkedin ?? '') }}"
                 placeholder="https://linkedin.com/in/...">
        </div>
      </div>

      <button type="submit" class="btn btn--primary btn--full">
        {{ isset($user) ? 'Salva modifiche' : '+ Aggiungi collaboratore' }}
      </button>
    </form>
  </div>
</div>

@endsection
