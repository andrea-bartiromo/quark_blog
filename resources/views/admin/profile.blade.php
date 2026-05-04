@extends('layouts.admin')

@section('title', 'Il mio profilo')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Il mio profilo</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">

  {{-- ── Dati profilo ──────────────────────────────── --}}
  <div style="display:flex;flex-direction:column;gap:1.5rem;">

    {{-- Foto profilo --}}
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="background:var(--color-ink);height:80px;position:relative;">
        <div style="position:absolute;bottom:-36px;left:1.5rem;">
          <div style="width:72px;height:72px;border-radius:50%;border:3px solid var(--color-white);
                      overflow:hidden;background:var(--color-paper-warm);">
            @if($user->photo)
              <img src="{{ asset('assets/img/'.$user->photo) }}"
                   alt="Foto di {{ $user->name }}"
                   id="photo-preview"
                   style="width:100%;height:100%;object-fit:cover;"
                   onerror="this.style.display='none'">
            @else
              <div id="photo-preview"
                   style="width:100%;height:100%;display:flex;align-items:center;
                          justify-content:center;font-size:2rem;">👤</div>
            @endif
          </div>
        </div>
      </div>
      <div style="padding:3rem 1.5rem 1.5rem;">
        <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;">{{ $user->name }}</div>
        <div style="font-family:var(--font-ui);font-size:.72rem;text-transform:uppercase;
                    letter-spacing:.08em;color:var(--color-accent);margin-bottom:1rem;">
          {{ ucfirst($user->role) }}
        </div>

        <form method="POST" action="{{ route('admin.profile.photo') }}" enctype="multipart/form-data">
          @csrf
          <div style="display:flex;align-items:center;gap:.75rem;">
            <label style="cursor:pointer;">
              <input type="file" name="photo" id="photo-input"
                     accept="image/jpeg,image/png,image/webp"
                     style="display:none;"
                     onchange="previewPhoto(this)">
              <span class="btn btn--outline"
                    style="color:var(--color-ink);border-color:var(--color-border);font-size:.75rem;cursor:pointer;">
                📷 Cambia foto
              </span>
            </label>
            <button type="submit" id="photo-submit" class="btn btn--primary"
                    style="font-size:.75rem;display:none;">
              Salva foto
            </button>
          </div>
          @error('photo')
            <p style="color:var(--color-accent);font-size:.78rem;margin-top:.35rem;">{{ $message }}</p>
          @enderror
        </form>
      </div>
    </div>

    {{-- Dati personali --}}
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">
      <div class="admin-section-title" style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;
           text-transform:uppercase;letter-spacing:.1em;border-bottom:2px solid var(--color-ink);
           padding-bottom:.5rem;margin-bottom:1rem;">
        Dati profilo
      </div>

      @if(session('success'))
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:4px;padding:.6rem .85rem;
                    font-family:var(--font-ui);font-size:.84rem;color:#2e7d32;margin-bottom:1rem;">
          ✓ {{ session('success') }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.profile.update') }}">
        @csrf @method('PUT')

        <div class="form-group">
          <label class="form-label" for="name">Nome e cognome *</label>
          <input class="form-input" type="text" id="name" name="name"
                 value="{{ old('name', $user->name) }}" required maxlength="100">
          @error('name') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email *</label>
          <input class="form-input" type="email" id="email" name="email"
                 value="{{ old('email', $user->email) }}" required>
          @error('email') <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="bio">Bio (max 500 caratteri)</label>
          <textarea class="form-textarea" id="bio" name="bio"
                    style="min-height:100px;" maxlength="500">{{ old('bio', $user->bio) }}</textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="twitter">Twitter / X (es. @nomeutente)</label>
          <input class="form-input" type="text" id="twitter" name="twitter"
                 placeholder="@tuonomeutente"
                 value="{{ old('twitter', $user->twitter) }}" maxlength="50">
        </div>

        <div class="form-group">
          <label class="form-label" for="linkedin">LinkedIn (URL profilo)</label>
          <input class="form-input" type="url" id="linkedin" name="linkedin"
                 placeholder="https://linkedin.com/in/tuonomeutente"
                 value="{{ old('linkedin', $user->linkedin) }}" maxlength="200">
        </div>

        <button type="submit" class="btn btn--primary">Salva modifiche</button>
      </form>
    </div>

  </div>

  {{-- ── Password + statistiche ────────────────────── --}}
  <div style="display:flex;flex-direction:column;gap:1.5rem;">

    {{-- Statistiche --}}
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;border-bottom:2px solid var(--color-ink);padding-bottom:.5rem;margin-bottom:1rem;">
        Le tue statistiche
      </div>
      @php
        $myArticles = $user->articles();
        $published  = $myArticles->where('status','published')->count();
        $drafts     = $myArticles->where('status','draft')->count();
        $totalViews = $myArticles->sum('views');
      @endphp
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;text-align:center;">
        @foreach([
          [$published, 'Pubblicati'],
          [$drafts,    'Bozze'],
          [number_format($totalViews), 'Visualizzazioni'],
        ] as [$val, $label])
        <div>
          <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:900;
                      color:var(--color-accent);line-height:1;">{{ $val }}</div>
          <div style="font-family:var(--font-ui);font-size:.65rem;text-transform:uppercase;
                      letter-spacing:.08em;color:var(--color-ink-muted);margin-top:.2rem;">{{ $label }}</div>
        </div>
        @endforeach
      </div>

      {{-- Ultimi articoli --}}
      @php $lastArticles = $user->articles()->latest()->limit(3)->get(); @endphp
      @if($lastArticles->isNotEmpty())
      <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--color-border);">
        <div style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.08em;color:var(--color-ink-muted);margin-bottom:.5rem;">
          Ultimi articoli
        </div>
        @foreach($lastArticles as $art)
        <a href="{{ route('admin.articles.edit', $art) }}"
           style="display:block;font-size:.84rem;color:var(--color-ink);
                  padding:.35rem 0;border-bottom:1px solid var(--color-border);
                  transition:color .2s;"
           onmouseover="this.style.color='var(--color-accent)'"
           onmouseout="this.style.color='var(--color-ink)'">
          {{ Str::limit($art->title, 55) }}
          <span style="font-size:.7rem;color:var(--color-ink-muted);margin-left:.35rem;">
            [{{ $art->status }}]
          </span>
        </a>
        @endforeach
      </div>
      @endif
    </div>

    {{-- Cambio password --}}
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;border-bottom:2px solid var(--color-ink);padding-bottom:.5rem;margin-bottom:1rem;">
        Cambia password
      </div>

      <form method="POST" action="{{ route('admin.profile.password') }}">
        @csrf @method('PUT')

        <div class="form-group">
          <label class="form-label" for="current_password">Password attuale *</label>
          <input class="form-input" type="password" id="current_password"
                 name="current_password" required autocomplete="current-password">
          @error('current_password')
            <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span>
          @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Nuova password * (min. 8 caratteri)</label>
          <input class="form-input" type="password" id="password"
                 name="password" required minlength="8" autocomplete="new-password">
          @error('password')
            <span style="color:var(--color-accent);font-size:.78rem;">{{ $message }}</span>
          @enderror
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirmation">Conferma nuova password *</label>
          <input class="form-input" type="password" id="password_confirmation"
                 name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--primary">Aggiorna password</button>
      </form>
    </div>

  </div>
</div>

@endsection

@section('scripts')
<script>
function previewPhoto(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('photo-preview');
    if (prev.tagName === 'IMG') {
      prev.src = e.target.result;
    } else {
      prev.outerHTML = `<img id="photo-preview" src="${e.target.result}"
        style="width:100%;height:100%;object-fit:cover;">`;
    }
    document.getElementById('photo-submit').style.display = 'inline-flex';
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
@endsection
