<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $__env->yieldContent('title','Redazione'); ?> — Quark</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
  <meta name="robots" content="noindex,nofollow">
  <style>
    .redazione-badge {
      display:inline-flex;align-items:center;gap:.3rem;
      background:#f0fdfa;color:#0d9488;font-size:.65rem;font-weight:700;
      text-transform:uppercase;letter-spacing:.08em;padding:.2rem .6rem;border-radius:20px;
      border:1px solid #99f6e4;
    }
  </style>
</head>
<body class="admin-body">
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        Quark<span style="color:#0d9488;">.</span>
      </div>
      <span class="admin-sidebar__sub">Area collaboratori</span>
    </div>

    <nav class="admin-nav">
      <span class="admin-nav__section">La mia area</span>

      <a href="<?php echo e(route('redazione.dashboard')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('redazione.dashboard')]); ?>">
        <span class="icon">📊</span> Dashboard
      </a>

      <a href="<?php echo e(route('redazione.articles')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('redazione.articles*')]); ?>">
        <span class="icon">📝</span> I miei articoli
        <?php
          $inReview = \App\Models\Article::where('user_id', auth()->id())->where('status','review')->count();
        ?>
        <?php if($inReview > 0): ?>
          <span style="background:#f97316;color:#fff;font-size:.6rem;font-weight:700;
                       padding:.1rem .4rem;border-radius:20px;margin-left:auto;">
            <?php echo e($inReview); ?> in rev.
          </span>
        <?php endif; ?>
      </a>

      <a href="<?php echo e(route('redazione.articles.create')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('redazione.articles.create')]); ?>">
        <span class="icon">✍️</span> Scrivi articolo
      </a>

      <span class="admin-nav__section">Account</span>

      <a href="<?php echo e(route('redazione.profile')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('redazione.profile')]); ?>">
        <span class="icon">👤</span> Il mio profilo
      </a>

      <?php if(auth()->user()->isEditor()): ?>
      <a href="<?php echo e(route('admin.dashboard')); ?>" style="border-top:1px solid rgba(255,255,255,.1);margin-top:.5rem;padding-top:.75rem;">
        <span class="icon">⚙️</span> Pannello admin
      </a>
      <?php endif; ?>

      <a href="<?php echo e(route('home')); ?>" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <a href="#" onclick="event.preventDefault();document.getElementById('logout-redazione').submit();">
        <span class="icon">🚪</span> Esci
      </a>

      <form id="logout-redazione" action="<?php echo e(route('admin.logout')); ?>" method="POST" style="display:none">
        <?php echo csrf_field(); ?>
      </form>
    </nav>

    <div class="admin-sidebar__user">
      <div class="admin-sidebar__user-avatar">
        <?php echo e(mb_substr(auth()->user()->name, 0, 2)); ?>

      </div>
      <div>
        <span class="admin-sidebar__user-name"><?php echo e(auth()->user()->name); ?></span>
        <span class="admin-sidebar__user-role">
          <span class="redazione-badge">✍ Collaboratore</span>
        </span>
      </div>
    </div>
  </aside>

  <main class="admin-main">
    <?php if(session('success')): ?>
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
                padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#065f46;">
      ✅ <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;
                padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#991b1b;">
      ❌ <?php echo e(session('error')); ?>

    </div>
    <?php endif; ?>

    <?php echo $__env->yieldContent('content'); ?>
  </main>
</div>

<?php echo $__env->yieldContent('scripts'); ?>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/layouts/redazione.blade.php ENDPATH**/ ?>