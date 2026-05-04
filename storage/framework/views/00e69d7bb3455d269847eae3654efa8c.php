
<nav class="category-bar" aria-label="Categorie">
  <div class="container">
    <a href="<?php echo e(route('notizie')); ?>"
       class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->routeIs('notizie') && !request()->route('slug')]); ?>">
      Tutti
    </a>
    <?php $__currentLoopData = config('laboratorio.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('categoria', $slug)); ?>"
         class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => request()->is("categoria/{$slug}")]); ?>">
        <?php echo e($label); ?>

      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</nav>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/category-bar.blade.php ENDPATH**/ ?>