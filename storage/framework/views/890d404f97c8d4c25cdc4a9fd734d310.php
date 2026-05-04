
<footer class="site-footer" role="contentinfo">
  <div class="container">

    <div class="footer-grid">

      
      <div>
        <div class="footer-logo">Quark<span class="dot">.</span></div>
        <p class="footer-desc">
          La scienza spiegata come si deve. Fisica, biologia, tecnologia e spazio
          raccontati in modo semplice, curioso e senza filtri.
        </p>
        <div class="footer-social">
          <?php $__currentLoopData = config('laboratorio.social'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $net => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e($url); ?>" target="_blank" rel="noopener"
               aria-label="<?php echo e(ucfirst($net)); ?>">
              <?php echo e(strtoupper(substr($net,0,1))); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>

      
      <div>
        <div class="footer-col-title">Esplora</div>
        <nav class="footer-links" aria-label="Sezioni">
          <?php $__currentLoopData = config('laboratorio.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('categoria', $slug)); ?>"><?php echo e($label); ?></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
      </div>

      
      <div>
        <div class="footer-col-title">Quark</div>
        <nav class="footer-links">
          <a href="<?php echo e(route('chi-siamo')); ?>">Chi siamo</a>
          <a href="<?php echo e(route('redazione')); ?>">La redazione</a>
          <a href="<?php echo e(route('contatti')); ?>">Contatti</a>
          <a href="<?php echo e(route('pubblicita')); ?>">Collabora con noi</a>
          <a href="<?php echo e(route('rettifiche')); ?>">Rettifiche</a>
          <a href="<?php echo e(route('feed')); ?>">RSS Feed</a>
        </nav>
      </div>

      
      <div>
        <div class="footer-col-title">Legale</div>
        <nav class="footer-links">
          <a href="<?php echo e(route('privacy')); ?>">Privacy policy</a>
          <a href="<?php echo e(route('cookie')); ?>">Cookie policy</a>
          <a href="<?php echo e(route('termini')); ?>">Termini d'uso</a>
        </nav>
      </div>

    </div>

    <div class="footer-bottom">
      <span>© <?php echo e(date('Y')); ?> Quark — Un progetto di
        <a href="<?php echo e(route('chi-siamo')); ?>#fondatore"
           style="color:rgba(255,255,255,.4);text-decoration:none;">Andrea Bartiromo</a>
      </span>
      <span>Tutti i diritti riservati</span>
    </div>

    <div class="footer-credit">
      Sviluppato con ♥ in Italia
      &nbsp;·&nbsp;
      <a href="<?php echo e(route('chi-siamo')); ?>#progetto">Il progetto</a>
    </div>

  </div>
</footer>
<?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/footer.blade.php ENDPATH**/ ?>