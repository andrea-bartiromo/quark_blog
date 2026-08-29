{{-- Footer Kairus --}}
<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">

      <div>
        <div class="footer-logo">
          <img
            src="{{ asset('assets/icons/symbol.svg') }}"
            width="26" height="26"
            alt=""
            class="footer-logo__symbol"
            decoding="async"
          >
          Kairus<span class="dot">.</span>
        </div>
        <p class="footer-desc">
          La scienza spiegata come si deve. Fisica, biologia, tecnologia e spazio
          raccontati in modo semplice, curioso e senza filtri.
        </p>

        @php
          $socialProfiles = collect(config('laboratorio.social', []))
            ->filter(function ($profile) {
              $url = is_array($profile) ? ($profile['url'] ?? null) : null;

              return is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
            });
        @endphp

        @if($socialProfiles->isNotEmpty())
          <div class="footer-social-section">
            {{--
                Titolo/descrizione con stile dedicato (non .footer-col-title,
                condivisa con le etichette eyebrow neutre "Esplora"/"Kairus"/
                "Legale"): qui il tono è un invito, non una categoria di
                navigazione, quindi resta leggibile come frase invece che
                come maiuscolo distanziato.
            --}}
            <p class="footer-social-invite" id="footer-social-title">La curiosità continua</p>
            <p class="footer-social-desc">Nuove storie, idee e domande da esplorare insieme. Trova Kairus anche su Facebook e LinkedIn.</p>
            <nav class="footer-social" aria-labelledby="footer-social-title">
              @foreach($socialProfiles as $network => $profile)
                <a
                  href="{{ $profile['url'] }}"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Kairus su {{ $profile['label'] }}"
                >
                  @if($network === 'linkedin')
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <path fill="currentColor" d="M5.2 3.7A2.2 2.2 0 1 1 5.2 8a2.2 2.2 0 0 1 0-4.3ZM3.3 9.5h3.8V21H3.3V9.5Zm6.1 0H13v1.6h.1c.5-.9 1.7-2 3.6-2 3.9 0 4.6 2.5 4.6 5.8V21h-3.8v-5.4c0-1.3 0-3-1.9-3s-2.2 1.4-2.2 2.9V21H9.4V9.5Z"/>
                    </svg>
                  @elseif($network === 'facebook')
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <path fill="currentColor" d="M13.7 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.5 1.6-1.5H17V3.9c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3V10H7.5v3h2.8v8h3.4Z"/>
                    </svg>
                  @endif
                  <span>{{ $profile['label'] }}</span>
                </a>
              @endforeach
            </nav>
          </div>
        @endif
      </div>

      <div>
        <div class="footer-col-title">Esplora</div>
        <nav class="footer-links" aria-label="Sezioni">
          @foreach($categoryOptions as $slug => $label)
            <a href="{{ url('/categoria/' . $slug) }}">{{ $label }}</a>
          @endforeach
        </nav>
      </div>

      <div>
        <div class="footer-col-title">Kairus</div>
        <nav class="footer-links" aria-label="Kairus">
          <a href="{{ url('/chi-siamo') }}">Chi siamo</a>
          <a href="{{ url('/la-redazione') }}">La redazione</a>
          <a href="{{ url('/contatti') }}">Contatti</a>
          <a href="{{ url('/pubblicita') }}">Pubblicità e collaborazioni</a>
          <a href="{{ url('/rettifiche') }}">Rettifiche</a>
          <a href="{{ url('/feed.xml') }}">RSS Feed</a>
        </nav>
      </div>

      <div>
        <div class="footer-col-title">Legale</div>
        <nav class="footer-links" aria-label="Legale">
          <a href="{{ url('/privacy') }}">Privacy policy</a>
          <a href="{{ url('/cookie') }}">Cookie policy</a>
          <a href="{{ url('/termini') }}">Termini d'uso</a>
        </nav>
      </div>

    </div>

    <div class="footer-bottom">
      <span>
        © {{ date('Y') }} Kairus — Un progetto di
        <a href="{{ url('/chi-siamo#fondatore') }}" style="color:rgba(255,255,255,.4);text-decoration:none;">
          Andrea Bartiromo
        </a>
      </span>
      <span>Tutti i diritti riservati</span>
    </div>

    <div class="footer-credit">
      Sviluppato con ♥ in Italia
      &nbsp;·&nbsp;
      <a href="{{ url('/chi-siamo#progetto') }}">Il progetto</a>
    </div>
  </div>
</footer>