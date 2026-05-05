
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $__env->yieldContent('title','Admin'); ?> — Quark</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
  <meta name="robots" content="noindex,nofollow">
</head>

<body class="admin-body">
<div class="admin-layout">

  
  <aside class="admin-sidebar">

    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        Quark<span class="dot">.</span>
      </div>
      <span class="admin-sidebar__sub">Pannello redazionale</span>
    </div>

    <nav class="admin-nav">

      <span class="admin-nav__section">Contenuti</span>

      <a href="<?php echo e(route('admin.dashboard')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.dashboard')]); ?>">
        <span class="icon">📊</span> Dashboard
      </a>

      <a href="<?php echo e(route('admin.articles')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.articles*')]); ?>">
        <span class="icon">📝</span> Articoli
      </a>

      <a href="<?php echo e(route('admin.comments')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.comments*')]); ?>">
        <span class="icon">💬</span> Commenti
        <?php
          $pendingComments = \App\Models\Comment::where('status','pending')->count();
        ?>
        <?php if($pendingComments > 0): ?>
          <span style="background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;
                       padding:.1rem .4rem;border-radius:20px;margin-left:auto;">
            <?php echo e($pendingComments); ?>

          </span>
        <?php endif; ?>
      </a>

      <a href="<?php echo e(route('admin.newsletter')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.newsletter')]); ?>">
        <span class="icon">✉️</span> Newsletter
      </a>

      <span class="admin-nav__section">Gestione</span>

      <a href="<?php echo e(route('admin.media')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.media*')]); ?>">
        <span class="icon">🖼</span> Media
      </a>

      <a href="<?php echo e(route('admin.ads')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.ads*')]); ?>">
        <span class="icon">📢</span> Pubblicità
      </a>

      <a href="<?php echo e(route('admin.verification')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.verification')]); ?>">
        <span class="icon">✅</span> Verifica fonti
        <?php
          $unverified = \App\Models\Article::where('status','published')
            ->whereIn('verification_status',['unverified','in_progress'])->count();
        ?>
        <?php if($unverified > 0): ?>
          <span style="background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;
                       padding:.1rem .4rem;border-radius:20px;margin-left:auto;">
            <?php echo e($unverified); ?>

          </span>
        <?php endif; ?>
      </a>

      <a href="<?php echo e(route('admin.suggestions')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.suggestions*')]); ?>">
        <span class="icon">🤖</span> Suggerimenti AI
      </a>

      <span class="admin-nav__section">Account</span>

      <a href="<?php echo e(route('admin.profile')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.profile*')]); ?>">
        <span class="icon">👤</span> Profilo
      </a>

      <a href="<?php echo e(route('home')); ?>" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <a href="#" onclick="event.preventDefault();document.getElementById('logout-form').submit();">
        <span class="icon">🚪</span> Esci
      </a>

      <form id="logout-form" action="<?php echo e(route('admin.logout')); ?>" method="POST" style="display:none">
        <?php echo csrf_field(); ?>
      </form>

    </nav>

    <div class="admin-sidebar__user">
      <div class="admin-sidebar__user-avatar">
        <?php echo e(mb_substr(auth()->user()->name, 0, 2)); ?>

      </div>
      <div>
        <span class="admin-sidebar__user-name"><?php echo e(auth()->user()->name); ?></span>
        <span class="admin-sidebar__user-role"><?php echo e(auth()->user()->role); ?></span>
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
</html><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/layouts/admin.blade.php ENDPATH**/ ?>