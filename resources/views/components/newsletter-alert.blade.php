@if(request('newsletter') === 'ok')
  <div id="newsletter-alert" class="newsletter-alert newsletter-alert--success">
    ✅ Iscrizione ricevuta! Controlla la tua email per confermare.
  </div>
@endif

@if(isset($errors) && $errors->has('email'))
  <div id="newsletter-alert" class="newsletter-alert newsletter-alert--error">
    ❌ {{ $errors->first('email') }}
  </div>
@endif
