@php
    $subscribeFormId = 'path-subscribe-form-'.$cluster->id;
    $subscriptionFeedbackHere = session('percorso_subscription_slug') === $cluster->slug;
    $subscriptionStatus = $subscriptionFeedbackHere ? session('percorso_subscription_status') : null;
@endphp
<div class="path-subscribe" data-path-subscribe>
    @if($subscriptionStatus === 'ok')
        <p class="path-subscribe__success" role="status">
            Grazie! Se il tuo indirizzo richiede conferma, riceverai un'email a breve.
        </p>
    @elseif($subscriptionStatus === 'unavailable')
        <p class="path-subscribe__error" role="alert">
            Questo Percorso non accetta nuove iscrizioni al momento.
        </p>
    @else
        <button type="button" class="path-subscribe__cta" data-path-subscribe-toggle
                aria-expanded="false" aria-controls="{{ $subscribeFormId }}">
            Avvisami quando continua
        </button>
        <form method="POST" action="{{ route('percorsi.subscribe', $cluster->slug) }}"
              class="path-subscribe__form" id="{{ $subscribeFormId }}" hidden data-path-subscribe-form>
            @csrf
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label for="website-{{ $subscribeFormId }}">Non compilare questo campo</label>
                <input type="text" id="website-{{ $subscribeFormId }}" name="website" tabindex="-1" autocomplete="off">
            </div>
            <label class="path-subscribe__label" for="email-{{ $subscribeFormId }}">Email</label>
            <div class="path-subscribe__row">
                <input type="email" id="email-{{ $subscribeFormId }}" name="email" required maxlength="150"
                       placeholder="La tua email" class="path-subscribe__input">
                <button type="submit" class="path-subscribe__submit">Avvisami</button>
            </div>
            <p class="path-subscribe__microcopy">Riceverai solo gli aggiornamenti relativi a questo Percorso.</p>
            @error('email')<p class="path-subscribe__error" role="alert">{{ $message }}</p>@enderror
        </form>
    @endif
</div>
@once
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-path-subscribe-toggle]').forEach(button => {
      button.addEventListener('click', () => {
        const form = document.getElementById(button.getAttribute('aria-controls'));

        if (!form) {
          return;
        }

        const expanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(!expanded));
        form.hidden = expanded;

        if (!expanded) {
          const emailField = form.querySelector('input[type="email"]');

          if (emailField) {
            emailField.focus();
          }
        }
      });
    });
  });
</script>
@endpush
@endonce
