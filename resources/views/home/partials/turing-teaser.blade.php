<div class="container container--wide">
  <section style="margin:2rem 0 4rem;border-radius:34px;overflow:hidden;color:#fff;position:relative;box-shadow:0 24px 70px rgba(15,23,42,.12);background-size:cover;background-position:center;{{ $turingHome['style'] }}">
    <div style="position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:2rem;align-items:center;padding:2.4rem;">
      <div>
        <span style="display:inline-flex;margin-bottom:.9rem;color:#a5f3fc;font-size:.72rem;font-weight:900;letter-spacing:.16em;text-transform:uppercase;text-shadow:0 2px 8px rgba(0,0,0,.7);">
          {{ $turingHome['kicker'] }}
        </span>

        <h2 style="font-family:var(--font-display);font-size:clamp(2rem,4.6vw,4.2rem);line-height:.98;letter-spacing:-.055em;margin:0;color:#fff;text-shadow:0 3px 12px rgba(0,0,0,.85),0 8px 28px rgba(0,0,0,.55);">
          {{ $turingHome['title'] }}
        </h2>

        <p style="margin-top:1rem;max-width:760px;color:#fff;font-size:1rem;line-height:1.8;text-shadow:0 2px 8px rgba(0,0,0,.8),0 5px 18px rgba(0,0,0,.5);">
          {{ $turingHome['lead'] }}
        </p>

        <a href="{{ route('turing') }}" style="display:inline-flex;margin-top:1.3rem;padding:.9rem 1.2rem;border-radius:14px;background:#67e8f9;color:#001018;text-decoration:none;font-weight:900;">
          {{ $turingHome['cta'] }} →
        </a>
      </div>

      <div style="border:1px solid rgba(255,255,255,.15);border-radius:24px;background:rgba(15,23,42,.45);backdrop-filter:blur(10px);padding:1.3rem;font-family:monospace;color:#a5f3fc;line-height:1.8;font-size:.86rem;box-shadow:0 18px 44px rgba(15,23,42,.25);">
        {{ $turingHome['terminalTitle'] }}<br>

        @foreach($turingHome['terminalLines'] as $line)
          {{ $line }}<br>
        @endforeach
      </div>
    </div>
  </section>
</div>
