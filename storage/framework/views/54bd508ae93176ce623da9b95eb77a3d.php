<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accesso redazione — Il Laboratorio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <style>
    body { display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--color-paper-warm); }
    .login-box { background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow-hover);padding:2.5rem;width:100%;max-width:420px; }
    .login-logo { font-family:var(--font-display);font-size:1.6rem;font-weight:900;text-align:center;margin-bottom:1.5rem; }
    .login-logo em { color:var(--color-accent);font-style:normal; }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="login-logo">
      Il <em>Lab</em>oratorio<br>
      <small style="font-family:var(--font-ui);font-size:.65rem;font-weight:400;text-transform:uppercase;letter-spacing:.12em;color:var(--color-ink-muted);">
        Pannello redazionale
      </small>
    </div>

    <?php if($errors->any()): ?>
    <div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:4px;padding:.75rem 1rem;font-family:var(--font-ui);font-size:.85rem;color:var(--color-accent);margin-bottom:1rem;">
      <?php echo e($errors->first()); ?>

    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('admin.login.post')); ?>">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-input" type="email" id="email" name="email"
               placeholder="redazione@illaboratorio.it" required autocomplete="email"
               value="<?php echo e(old('email')); ?>">
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
      <a href="<?php echo e(route('home')); ?>" style="font-family:var(--font-ui);font-size:.8rem;color:var(--color-ink-muted);">
        ← Torna al sito
      </a>
    </p>
  </div>
</body>
</html>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/login.blade.php ENDPATH**/ ?>