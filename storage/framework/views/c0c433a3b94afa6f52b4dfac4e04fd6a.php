
<?php
  $tickerArticles = \App\Models\Article::published()
    ->orderByDesc('published_at')
    ->limit(8)
    ->get(['title', 'slug']);
?>

<?php if($tickerArticles->count() > 0): ?>
<div class="ticker" aria-label="Ultimi articoli">
  <div class="ticker-label">🔬 Nuovo</div>
  <div style="overflow:hidden;flex:1;">
    <div class="ticker-track">
      <?php $__currentLoopData = $tickerArticles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <span class="ticker-item">
          <a href="<?php echo e(route('articolo', $a->slug)); ?>"><?php echo e($a->title); ?></a>
        </span>
        <span class="ticker-sep">·</span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      
      <?php $__currentLoopData = $tickerArticles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <span class="ticker-item">
          <a href="<?php echo e(route('articolo', $a->slug)); ?>"><?php echo e($a->title); ?></a>
        </span>
        <span class="ticker-sep">·</span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/ticker.blade.php ENDPATH**/ ?>