<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accesso collaboratori — Quark</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Fraunces:wght@900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
  <meta name="robots" content="noindex,nofollow">
</head>
<body style="background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;">

  <div style="width:100%;max-width:420px;">

    {{-- Logo --}}
    <div style="text-align:center;margin-bottom:2rem;">
      <a href="{{ route('home') }}" style="text-decoration:none;">
        <div style="font-family:'Fraunces',Georgia,serif;font-size:2.5rem;font-weight:900;
                    color:#0d9488;letter-spacing:-.02em;">Quark.</div>
      </a>
      <p style="color:#6b7280;font-size:.85rem;margin-top:.4rem;">Area collaboratori</p>
    </div>

    {{-- Card login --}}
    <div style="background:#fff;border-radius:16px;padding:2rem;box-shadow:0 4px 24px rgba(0,0,0,.08);">
      <h1 style="font-size:1.2rem;font-weight:700;color:#111827;margin-bottom:1.5rem;text-align:center;">
        Accedi alla redazione
      </h1>

      @if($errors->any())
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
                  padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#991b1b;">
        {{ $errors->first() }}
      </div>
      @endif

      <form method="POST" action="{{ route('redazione.login.post') }}">
        @csrf
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" type="email" name="email"
                 value="{{ old('email') }}" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" name="password" required>
        </div>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;
                       color:#6b7280;margin-bottom:1.25rem;cursor:pointer;">
          <input type="checkbox" name="remember" style="accent-color:#0d9488;">
          Ricordami
        </label>
        <button type="submit" class="btn btn--primary btn--full">
          Accedi
        </button>
      </form>
    </div>

    <p style="text-align:center;margin-top:1.25rem;font-size:.78rem;color:#9ca3af;">
      <a href="{{ route('home') }}" style="color:#6b7280;">← Torna al sito</a>
    </p>

  </div>
</body>
</html>
