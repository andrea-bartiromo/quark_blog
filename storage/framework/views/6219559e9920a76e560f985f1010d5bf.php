
<?php
  $popular = \App\Models\Article::published()
    ->orderByDesc('views')
    ->limit(5)
    ->get(['title','slug','category','read_minutes']);

  $categories = config('laboratorio.categories');
?>

<div class="sidebar">

  
  <?php echo $__env->make('components.adsense', [
      'slot'   => '3333333333',
      'format' => 'rectangle',
      'style'  => 'margin-bottom:.5rem;'
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


  
  <div class="newsletter-box">
    <h3>Non perderti niente 🧪</h3>
    <p>Ogni settimana i migliori articoli di Quark direttamente nella tua inbox.</p>
    <form class="newsletter-form" action="<?php echo e(route('newsletter.subscribe')); ?>" method="POST">
      <?php echo csrf_field(); ?>
      <input type="email" name="email" placeholder="La tua email" required>
      <button type="submit">Iscriviti gratis</button>
    </form>
  </div>

  
  <div class="sidebar-box">
    <div class="sidebar-box__head">
      <h3>🔥 Più letti</h3>
    </div>
    <div class="sidebar-box__body" style="padding:0;">
      <?php $__currentLoopData = $popular; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $art): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('articolo', $art->slug)); ?>"
         style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;
                border-bottom:1px solid var(--border-light);text-decoration:none;
                transition:background .15s;"
         onmouseover="this.style.background='var(--paper-warm)'"
         onmouseout="this.style.background=''">
        <span style="font-family:var(--font-display);font-size:1.4rem;font-weight:900;
                     color:var(--border);line-height:1;flex-shrink:0;margin-top:2px;">
          <?php echo e($i + 1); ?>

        </span>
        <div>
          <span class="badge badge--<?php echo e($art->category); ?>" style="margin-bottom:.2rem;display:inline-block;">
            <?php echo e($categories[$art->category] ?? $art->category); ?>

          </span>
          <div style="font-size:.82rem;font-weight:600;color:var(--ink);line-height:1.3;">
            <?php echo e(Str::limit($art->title, 60)); ?>

          </div>
          <div style="font-size:.7rem;color:var(--ink-muted);margin-top:.2rem;">
            <?php echo e($art->read_minutes); ?> min
          </div>
        </div>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>

  
  <div class="sidebar-box">
    <div class="sidebar-box__head">
      <h3>Argomenti</h3>
    </div>
    <div class="sidebar-box__body">
      <div class="tag-pills">
        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('categoria', $slug)); ?>" class="tag-pill"><?php echo e($label); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>

</div><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/sidebar.blade.php ENDPATH**/ ?>