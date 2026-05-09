{{-- Footer Quark --}}
<footer class="site-footer" role="contentinfo">
  <div class="container">

    <div class="footer-grid">

      {{-- Brand --}}
      <div>
        <div class="footer-logo">Quark<span class="dot">.</span></div>
        <p class="footer-desc">
          La scienza spiegata come si deve. Fisica, biologia, tecnologia e spazio
          raccontati in modo semplice, curioso e senza filtri.
        </p>
        <div class="footer-social">
          @foreach(config('laboratorio.social') as $net => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener"
               aria-label="{{ ucfirst($net) }}">
              {{ strtoupper(substr($net,0,1)) }}
            </a>
          @endforeach
        </div>
      </div>

      {{-- Sezioni --}}
      <div>
        <div class="footer-col-title">Esplora</div>
        <nav class="footer-links" aria-label="Sezioni">
          @foreach(config('laboratorio.categories') as $slug => $label)
            <a href="{{ url('/categoria/'.$slug) }}">{{ $label }}</a>
          @endforeach
        </nav>
      </div>

      {{-- Blog --}}
      <div>
        <div class="footer-col-title">Quark</div>
        <nav class="footer-links" aria-label="Quark">
          <a href="{{ url('/chi-siamo') }}">Chi siamo</a>
          <a href="{{ url('/la-redazione') }}">La redazione</a>
          <a href="{{ url('/contatti') }}">Contatti</a>
          <a href="{{ url('/pubblicita') }}">Pubblicità e collaborazioni</a>
          <a href="{{ url('/rettifiche') }}">Rettifiche</a>
          <a href="{{ url('/feed.xml') }}">RSS Feed</a>
        </nav>
      </div>

      {{-- Legale --}}
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
      <span>© {{ date('Y') }} Quark — Un progetto di
        <a href="{{ url('/chi-siamo#fondatore') }}"
           style="color:rgba(255,255,255,.4);text-decoration:none;">Andrea Bartiromo</a>
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