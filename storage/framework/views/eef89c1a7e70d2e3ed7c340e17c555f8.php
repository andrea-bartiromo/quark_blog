<?php if($paginator->hasPages()): ?>
<nav class="pagination" aria-label="Paginazione articoli" role="navigation">

  
  <?php if($paginator->onFirstPage()): ?>
    <span style="opacity:.35;cursor:default;" aria-disabled="true">←</span>
  <?php else: ?>
    <a href="<?php echo e($paginator->previousPageUrl()); ?>" aria-label="Pagina precedente">←</a>
  <?php endif; ?>

  
  <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(is_string($element)): ?>
      <span style="display:flex;align-items:center;font-family:var(--font-ui);font-size:.82rem;
                   padding:0 .4rem;color:var(--color-ink-muted);">…</span>
    <?php endif; ?>

    <?php if(is_array($element)): ?>
      <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($page == $paginator->currentPage()): ?>
          <span class="current" aria-current="page"><?php echo e($page); ?></span>
        <?php else: ?>
          <a href="<?php echo e($url); ?>"><?php echo e($page); ?></a>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

  
  <?php if($paginator->hasMorePages()): ?>
    <a href="<?php echo e($paginator->nextPageUrl()); ?>" aria-label="Pagina successiva">→</a>
  <?php else: ?>
    <span style="opacity:.35;cursor:default;" aria-disabled="true">→</span>
  <?php endif; ?>

</nav>
<?php endif; ?>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/pagination.blade.php ENDPATH**/ ?>