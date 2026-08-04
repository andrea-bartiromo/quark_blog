{{-- components/newsletter-popup.blade.php --}}

<div class="newsletter-popup"
     id="newsletter-popup"
     role="dialog"
     aria-modal="true"
     aria-labelledby="newsletter-popup-title">

    {{-- Overlay --}}
    <div class="newsletter-popup__overlay"
         id="newsletter-popup-overlay"></div>

    <div class="newsletter-popup__box">

        {{-- Chiusura --}}
        <button type="button"
                class="newsletter-popup__close"
                id="newsletter-popup-close"
                aria-label="Chiudi">
            &times;
        </button>

        {{-- Icona --}}
        <div class="newsletter-popup__icon" aria-hidden="true">
            🧪
        </div>

        {{-- Titolo --}}
        <h2 class="newsletter-popup__title"
            id="newsletter-popup-title">
            Resta aggiornato su Kairus
        </h2>

        {{-- Descrizione --}}
        <p class="newsletter-popup__sub">
            Una email ogni settimana con i migliori articoli scientifici.
            Nessuno spam.
        </p>

        {{-- Form --}}
        <form class="newsletter-form newsletter-popup__form"
              method="POST"
              action="{{ route('newsletter.subscribe') }}">

            @csrf

            <input type="hidden"
                   name="_redirect"
                   value="1">

            {{-- Honeypot --}}
            <input type="text"
                   name="website"
                   tabindex="-1"
                   autocomplete="off"
                   style="display:none;">

            {{-- Label --}}
            <label for="newsletter-popup-email"
                   style="
                        display:block;
                        margin-bottom:6px;
                        color:#334155;
                        font-size:13px;
                        font-weight:700;
                   ">
                Indirizzo email
            </label>

            {{-- Campo email --}}
            <input
                id="newsletter-popup-email"
                type="email"
                name="email"
                placeholder="La tua email"
                autocomplete="email"
                required

                style="
                    display:block;
                    width:100%;
                    height:52px;
                    padding:0 16px;
                    margin-bottom:16px;

                    background:#ffffff;
                    color:#111827;

                    border:2px solid #94a3b8;
                    border-radius:10px;

                    font-size:16px;
                    font-family:inherit;

                    outline:none;
                    box-sizing:border-box;

                    -webkit-text-fill-color:#111827;
                    caret-color:#111827;
                ">

            {{-- Bottone --}}
            <button type="submit"
                    class="newsletter-popup__submit"
                    style="
                        width:100%;
                        height:50px;

                        border:none;
                        border-radius:10px;

                        background:#0d9488;
                        color:#ffffff;

                        font-size:15px;
                        font-weight:700;

                        cursor:pointer;
                    ">
                Iscriviti gratis
            </button>

        </form>

    </div>
</div>

@if(request('newsletter') === 'ok')
    <div id="newsletter-alert"
         class="newsletter-alert newsletter-alert--success">
        ✅ Iscrizione ricevuta! Controlla la tua email per confermare.
    </div>
@endif

@if(isset($errors) && $errors->has('email'))
    <div id="newsletter-alert"
         class="newsletter-alert newsletter-alert--error">
        ❌ {{ $errors->first('email') }}
    </div>
@endif