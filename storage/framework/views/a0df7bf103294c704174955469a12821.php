
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo $__env->yieldContent('title', config('laboratorio.name').' — '.config('laboratorio.tagline')); ?></title>
  <meta name="description" content="<?php echo $__env->yieldContent('description', config('laboratorio.description')); ?>">

  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

  
  <meta property="og:site_name" content="<?php echo e(config('laboratorio.name')); ?>">
  <meta property="og:type" content="<?php echo $__env->yieldContent('og_type','website'); ?>">
  <meta property="og:title" content="<?php echo $__env->yieldContent('title'); ?>">
  <meta property="og:description" content="<?php echo $__env->yieldContent('description'); ?>">
  <meta property="og:url" content="<?php echo e(url()->current()); ?>">

  
  

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">

  <?php echo $__env->yieldContent('head'); ?>
</head>

<body>

<?php echo $__env->make('components.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.ticker', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.category-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php if(request('newsletter') === 'ok'): ?>
<div id="newsletter-alert" style="
  max-width:1200px;margin:1rem auto 0;padding:.85rem 1.25rem;
  background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;
  border-radius:10px;font-size:.9rem;font-weight:600;">
  ✅ Iscrizione ricevuta! Controlla la tua email per confermare.
</div>
<?php endif; ?>

<?php if($errors->has('email')): ?>
<div id="newsletter-alert" style="
  max-width:1200px;margin:1rem auto 0;padding:.85rem 1.25rem;
  background:#fee2e2;color:#991b1b;border:1px solid #fecaca;
  border-radius:10px;font-size:.9rem;font-weight:600;">
  ❌ <?php echo e($errors->first('email')); ?>

</div>
<?php endif; ?>

<main>
  <?php echo $__env->yieldContent('content'); ?>
</main>

<?php echo $__env->make('components.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.cookie-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php echo $__env->make('components.newsletter-popup', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const popup   = document.getElementById("newsletter-popup");
  const closeBtn = document.getElementById("newsletter-popup-close");
  const overlay  = document.getElementById("newsletter-popup-overlay");

  // Auto-hide messaggio iscrizione dopo 5 secondi
  const alert = document.getElementById("newsletter-alert");
  if (alert) {
    setTimeout(() => {
      alert.style.transition = "opacity .5s";
      alert.style.opacity = "0";
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  }

  if (!popup) return;

  // Mostra popup solo se:
  // 1. L'utente non ha già chiuso il popup in precedenza (localStorage)
  // 2. Non è appena arrivato dalla pagina di conferma
  // 3. Non ha già un cookie di iscrizione
  const dismissed = localStorage.getItem('newsletter_dismissed');
  const subscribed = localStorage.getItem('newsletter_subscribed');

  if (!dismissed && !subscribed) {
    setTimeout(() => {
      popup.classList.add("visible");
    }, 30000);
  }

  // Chiusura popup — salva in localStorage per non mostrarlo più oggi
  function closePopup() {
    popup.classList.remove("visible");
    // Non mostrare per 7 giorni
    const expires = Date.now() + 7 * 24 * 60 * 60 * 1000;
    localStorage.setItem('newsletter_dismissed', expires);
  }

  if (closeBtn) closeBtn.addEventListener("click", closePopup);
  if (overlay)  overlay.addEventListener("click", closePopup);
  document.addEventListener("keydown", e => {
    if (e.key === "Escape") closePopup();
  });

  // Se l'utente si è appena iscritto, segna come iscritto in localStorage
  <?php if(request('newsletter') === 'ok'): ?>
  localStorage.setItem('newsletter_subscribed', '1');
  <?php endif; ?>

  // Controlla se il dismissal è scaduto
  const dismissedUntil = localStorage.getItem('newsletter_dismissed');
  if (dismissedUntil && Date.now() > parseInt(dismissedUntil)) {
    localStorage.removeItem('newsletter_dismissed');
  }
});
</script>

<?php echo $__env->yieldPushContent('scripts'); ?>

</body>
</html><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/layouts/app.blade.php ENDPATH**/ ?>