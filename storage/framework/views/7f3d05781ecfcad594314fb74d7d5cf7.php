

<div class="newsletter-popup"
     id="newsletter-popup"
     role="dialog"
     aria-modal="true"
     aria-labelledby="newsletter-popup-title">

  
  <div class="newsletter-popup__overlay"
       id="newsletter-popup-overlay"></div>

  <div class="newsletter-popup__box">

    
    <button type="button"
            class="newsletter-popup__close"
            id="newsletter-popup-close"
            aria-label="Chiudi">
      ×
    </button>

    
    <div class="newsletter-popup__icon">
      🧪
    </div>

    
    <h2 class="newsletter-popup__title"
        id="newsletter-popup-title">
      Resta aggiornato su Quark
    </h2>

    
    <p class="newsletter-popup__sub">
      Una email ogni settimana con i migliori articoli scientifici. Niente spam.
    </p>

    
    <form class="newsletter-form"
          method="POST"
          action="<?php echo e(route('newsletter.subscribe')); ?>">

      <?php echo csrf_field(); ?>

      
      <input type="hidden" name="_redirect" value="1">

      
      <input type="text"
             name="website"
             tabindex="-1"
             autocomplete="off"
             style="display:none;">

      
      <input type="email"
             name="email"
             placeholder="La tua email"
             required
             autocomplete="email">

      
      <button type="submit">
        Iscriviti gratis
      </button>

    </form>

  </div>
</div><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/components/newsletter-popup.blade.php ENDPATH**/ ?>