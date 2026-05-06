@extends('layouts.redazione')
@section('title', 'Il mio profilo')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Il mio profilo</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:800px;">

  {{-- Dati personali --}}
  <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:1rem;">Informazioni personali</h3>
    <form method="POST" action="{{ route('redazione.profile.update') }}">
      @csrf @method('PUT')
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-input" type="text" name="name"
               value="{{ old('name', $user->name) }}" required>
      </div>
      <div class="form-group">
        <label class="form-label">Bio (max 500 caratteri)</label>
        <textarea class="form-textarea" name="bio" maxlength="500"
                  style="min-height:100px;">{{ old('bio', $user->bio) }}</textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Twitter / X</label>
        <input class="form-input" type="text" name="twitter"
               value="{{ old('twitter', $user->twitter) }}"
               placeholder="@handle">
      </div>
      <div class="form-group">
        <label class="form-label">LinkedIn</label>
        <input class="form-input" type="url" name="linkedin"
               value="{{ old('linkedin', $user->linkedin) }}"
               placeholder="https://linkedin.com/in/...">
      </div>
      <button type="submit" class="btn btn--primary btn--full">Salva modifiche</button>
    </form>
  </div>

  <div style="display:flex;flex-direction:column;gap:1rem;">

    {{-- Foto profilo --}}
    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
      <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:1rem;">Foto profilo</h3>
      <div style="text-align:center;margin-bottom:1rem;">
        <div style="width:80px;height:80px;border-radius:50%;background:#0d9488;
                    display:flex;align-items:center;justify-content:center;
                    font-size:1.8rem;font-weight:700;color:#fff;margin:0 auto;">
          @if($user->photo)
            <img src="{{ asset('storage/'.$user->photo) }}" alt=""
                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
          @else
            {{ mb_substr($user->name, 0, 2) }}
          @endif
        </div>
      </div>
      <form method="POST" action="{{ route('redazione.profile.photo') }}"
            enctype="multipart/form-data">
        @csrf
        <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png"
               style="font-size:.78rem;width:100%;margin-bottom:.5rem;">
        <button type="submit" class="btn btn--secondary btn--full" style="font-size:.78rem;">
          Aggiorna foto
        </button>
      </form>
    </div>

    {{-- Cambia password --}}
    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">
      <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:1rem;">Cambia password</h3>
      <form method="POST" action="{{ route('redazione.profile.password') }}">
        @csrf @method('PUT')
        <div class="form-group">
          <label class="form-label">Password attuale</label>
          <input class="form-input" type="password" name="current_password" required>
          @error('current_password')
            <div style="font-size:.72rem;color:#dc2626;margin-top:.25rem;">{{ $message }}</div>
          @enderror
        </div>
        <div class="form-group">
          <label class="form-label">Nuova password</label>
          <input class="form-input" type="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Conferma password</label>
          <input class="form-input" type="password" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn--secondary btn--full" style="font-size:.78rem;">
          Aggiorna password
        </button>
      </form>
    </div>

  </div>
</div>

@endsection