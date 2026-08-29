<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accesso redazione — Kairus</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/icons/favicon.svg') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <style>
    body { display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--color-paper-warm); }
    .login-box { background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow-hover);padding:2.5rem;width:100%;max-width:420px; }
    .login-logo { font-family:var(--font-display);font-size:1.6rem;font-weight:900;text-align:center;margin-bottom:1.5rem; }
    .login-logo em { color:var(--color-accent);font-style:normal; }
    .login-logo__symbol { display:block;margin:0 auto .5rem; }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="login-logo">
      <img
        src="{{ asset('assets/icons/symbol.svg') }}"
        width="48" height="48"
        alt=""
        class="login-logo__symbol"
        decoding="async"
      >
      Kairus.<br>
      <small style="font-family:var(--font-ui);font-size:.65rem;font-weight:400;text-transform:uppercase;letter-spacing:.12em;color:var(--color-ink-muted);">
        Pannello redazionale
      </small>
    </div>

    @if($errors->any())
    <div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:4px;padding:.75rem 1rem;font-family:var(--font-ui);font-size:.85rem;color:var(--color-accent);margin-bottom:1rem;">
      {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.login.post') }}">
      @csrf
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-input" type="email" id="email" name="email"
               placeholder="redazione@kairus.it" required autocomplete="email"
               value="{{ old('email') }}">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-input" type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-checkbox">
          <input type="checkbox" name="remember"> Ricordami
        </label>
      </div>
      <button type="submit" class="btn btn--primary btn--full" style="margin-top:.5rem;">
        Accedi
      </button>
    </form>

    <p style="text-align:center;margin-top:1rem;">
      <a href="{{ route('home') }}" style="font-family:var(--font-ui);font-size:.8rem;color:var(--color-ink-muted);">
        ← Torna al sito
      </a>
    </p>
  </div>
</body>
</html>
