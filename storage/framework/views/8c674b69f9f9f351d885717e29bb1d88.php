
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo $__env->yieldContent('title','Admin'); ?> — Il Laboratorio</title>

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">

  
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>?v=<?php echo e(time()); ?>">

  <meta name="robots" content="noindex,nofollow">
</head>

<body class="admin-body">

<div class="admin-layout">

  
  <aside class="admin-sidebar">

    <div class="admin-sidebar__brand">
      <div class="admin-sidebar__logo">
        <span style="font-family:var(--font-display);font-weight:900;color:white;">
          Quark<span style="color:var(--admin-primary,#0d9488);">.</span>
        </span>
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
      </a>

      <a href="<?php echo e(route('admin.newsletter')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('admin.newsletter')]); ?>">
        <span class="icon">✉️</span> Newsletter
      </a>

      <span class="admin-nav__section">Sistema</span>

      <a href="<?php echo e(route('home')); ?>" target="_blank">
        <span class="icon">🌐</span> Vedi sito
      </a>

      <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
        <span class="icon">🚪</span> Esci
      </a>

      <form id="logout-form" action="<?php echo e(route('admin.logout')); ?>" method="POST" style="display:none">
        <?php echo csrf_field(); ?>
      </form>

    </nav>

    <div class="admin-sidebar__user">
      <div>
        <span class="admin-sidebar__user-name"><?php echo e(auth()->user()->name); ?></span>
        <span class="admin-sidebar__user-role"><?php echo e(auth()->user()->role); ?></span>
      </div>
    </div>

  </aside>

  
  <main class="admin-main">

    <?php if(session('success')): ?>
      <div style="
        background:#e8f5e9;
        border:1px solid #a5d6a7;
        border-radius:6px;
        padding:.75rem 1rem;
        margin-bottom:1rem;
        font-family:var(--font-ui);
        font-size:.85rem;
        color:#2e7d32;
      ">
        <?php echo e(session('success')); ?>

      </div>
    <?php endif; ?>

    <?php echo $__env->yieldContent('content'); ?>

  </main>

</div>


<?php echo $__env->yieldContent('scripts'); ?>
<?php echo $__env->yieldPushContent('scripts'); ?>

</body>
</html><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/layouts/admin.blade.php ENDPATH**/ ?>