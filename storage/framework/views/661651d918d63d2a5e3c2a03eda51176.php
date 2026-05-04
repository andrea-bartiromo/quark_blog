
<header class="site-header" role="banner">
  <div class="container">

    
    <a href="<?php echo e(route('home')); ?>" class="header-logo">
      Quark<span class="dot">.</span>
    </a>

    
    <nav class="header-nav" aria-label="Navigazione principale">
      <a href="<?php echo e(route('notizie')); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('notizie')]); ?>">Articoli</a>
      <?php $__currentLoopData = config('laboratorio.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($loop->index < 4): ?>
        <a href="<?php echo e(route('categoria', $slug)); ?>"
           class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->is("categoria/{$slug}")]); ?>">
          <?php echo e($label); ?>

        </a>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>

    
    <div class="header-actions">
      <a href="<?php echo e(route('ricerca')); ?>" class="btn-search" aria-label="Cerca">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.35-4.35"/>
        </svg>
      </a>
      <button class="btn-subscribe"
              onclick="document.querySelector('.newsletter-popup').classList.add('visible')">
        ✉ Newsletter
      </button>
    </div>

  </div>
</header>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/header.blade.php ENDPATH**/ ?>