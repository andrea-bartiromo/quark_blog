<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#05070f">

        <title>Quark — Premium stories for curious minds</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#05070f] text-white antialiased selection:bg-cyan-300 selection:text-slate-950">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -top-28 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="absolute top-40 -left-32 h-96 w-96 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-[34rem] w-[34rem] rounded-full bg-amber-300/10 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.10),transparent_34%),linear-gradient(rgba(255,255,255,0.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.035)_1px,transparent_1px)] bg-[size:100%_100%,72px_72px,72px_72px]"></div>
        </div>

        <main class="relative isolate overflow-hidden">
            <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <a href="/" class="group flex items-center gap-3" aria-label="Quark home">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl border border-white/15 bg-white/10 shadow-2xl shadow-cyan-500/10 backdrop-blur">
                        <span class="h-4 w-4 rounded-full bg-cyan-300 shadow-[0_0_30px_rgba(103,232,249,0.9)] transition group-hover:scale-125"></span>
                    </span>
                    <span>
                        <span class="block text-base font-semibold tracking-[0.28em] text-white">QUARK</span>
                        <span class="block text-xs uppercase tracking-[0.24em] text-slate-400">Signal over noise</span>
                    </span>
                </a>

                @if (Route::has('login'))
                    <nav class="hidden items-center gap-3 text-sm text-slate-300 md:flex">
                        <a href="#dispatch" class="rounded-full px-4 py-2 transition hover:bg-white/10 hover:text-white">Dispatch</a>
                        <a href="#features" class="rounded-full px-4 py-2 transition hover:bg-white/10 hover:text-white">Features</a>
                        <a href="#pricing" class="rounded-full px-4 py-2 transition hover:bg-white/10 hover:text-white">Premium</a>
                        @auth
                            <a href="{{ url('/dashboard') }}" class="rounded-full border border-cyan-300/40 bg-cyan-300 px-5 py-2 font-semibold text-slate-950 shadow-lg shadow-cyan-500/20 transition hover:-translate-y-0.5 hover:bg-cyan-200">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-full border border-white/15 px-5 py-2 transition hover:border-white/30 hover:bg-white/10 hover:text-white">Log in</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="rounded-full border border-cyan-300/40 bg-cyan-300 px-5 py-2 font-semibold text-slate-950 shadow-lg shadow-cyan-500/20 transition hover:-translate-y-0.5 hover:bg-cyan-200">Start free</a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <section class="mx-auto grid min-h-[calc(100vh-92px)] w-full max-w-7xl items-center gap-12 px-6 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-24">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.07] px-3 py-2 text-sm text-slate-300 shadow-2xl shadow-black/20 backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(110,231,183,0.9)]"></span>
                        Independent briefings, deep dives, and premium essays
                    </div>

                    <h1 class="mt-8 text-5xl font-black tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Read the internet like a private research desk.
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                        Quark turns fast-moving ideas into elegant, evidence-led stories: sharp analysis, curated sources, and a calmer way to follow what matters.
                    </p>

                    <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="group inline-flex items-center justify-center rounded-full bg-white px-7 py-4 text-base font-bold text-slate-950 shadow-2xl shadow-white/10 transition hover:-translate-y-1 hover:bg-cyan-200">
                                Unlock premium
                                <span class="ml-2 transition group-hover:translate-x-1">→</span>
                            </a>
                        @else
                            <a href="#dispatch" class="group inline-flex items-center justify-center rounded-full bg-white px-7 py-4 text-base font-bold text-slate-950 shadow-2xl shadow-white/10 transition hover:-translate-y-1 hover:bg-cyan-200">
                                Explore Quark
                                <span class="ml-2 transition group-hover:translate-x-1">→</span>
                            </a>
                        @endif
                        <a href="#dispatch" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/[0.06] px-7 py-4 text-base font-semibold text-white backdrop-blur transition hover:-translate-y-1 hover:border-white/30 hover:bg-white/10">
                            View latest issue
                        </a>
                    </div>

                    <dl class="mt-12 grid max-w-2xl grid-cols-3 gap-4 border-t border-white/10 pt-8">
                        <div>
                            <dt class="text-3xl font-black text-white">12k+</dt>
                            <dd class="mt-1 text-sm text-slate-400">curious readers</dd>
                        </div>
                        <div>
                            <dt class="text-3xl font-black text-white">42</dt>
                            <dd class="mt-1 text-sm text-slate-400">weekly signals</dd>
                        </div>
                        <div>
                            <dt class="text-3xl font-black text-white">8 min</dt>
                            <dd class="mt-1 text-sm text-slate-400">average briefing</dd>
                        </div>
                    </dl>
                </div>

                <div id="dispatch" class="relative">
                    <div class="absolute -inset-4 rounded-[2.5rem] bg-gradient-to-br from-cyan-300/25 via-fuchsia-400/15 to-amber-300/20 blur-2xl"></div>
                    <article class="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/[0.08] p-4 shadow-2xl shadow-black/40 backdrop-blur-2xl">
                        <div class="rounded-[1.5rem] border border-white/10 bg-slate-950/70 p-5">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.35em] text-cyan-200">Quark Dispatch</p>
                                    <h2 class="mt-2 text-2xl font-bold">The Premium Brief</h2>
                                </div>
                                <span class="rounded-full bg-emerald-300/10 px-3 py-1 text-xs font-semibold text-emerald-200">Live</span>
                            </div>

                            <div class="mt-6 grid gap-4">
                                <div class="rounded-3xl bg-white/[0.06] p-5 ring-1 ring-white/10">
                                    <p class="text-sm text-slate-400">Lead story</p>
                                    <h3 class="mt-3 text-2xl font-bold leading-tight">Why small teams now publish like global newsrooms</h3>
                                    <p class="mt-3 text-sm leading-6 text-slate-300">A field guide to editorial systems, trust signals, and distribution loops that make niche media feel premium.</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                                        <p class="text-xs uppercase tracking-[0.25em] text-fuchsia-200">Insight</p>
                                        <p class="mt-4 text-3xl font-black">74%</p>
                                        <p class="mt-2 text-sm text-slate-400">of premium readers value synthesis over speed.</p>
                                    </div>
                                    <div class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                                        <p class="text-xs uppercase tracking-[0.25em] text-amber-200">Queue</p>
                                        <ul class="mt-4 space-y-3 text-sm text-slate-300">
                                            <li class="flex gap-3"><span class="text-cyan-200">01</span> AI agents</li>
                                            <li class="flex gap-3"><span class="text-cyan-200">02</span> Spatial computing</li>
                                            <li class="flex gap-3"><span class="text-cyan-200">03</span> Creator finance</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section id="features" class="mx-auto w-full max-w-7xl px-6 py-20 lg:px-8">
                <div class="grid gap-6 md:grid-cols-3">
                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 backdrop-blur">
                        <div class="mb-6 grid h-12 w-12 place-items-center rounded-2xl bg-cyan-300/15 text-cyan-200">✦</div>
                        <h3 class="text-xl font-bold">Editorial-grade curation</h3>
                        <p class="mt-3 leading-7 text-slate-400">Signal-rich reading paths, source trails, and context that survives beyond the news cycle.</p>
                    </div>
                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 backdrop-blur">
                        <div class="mb-6 grid h-12 w-12 place-items-center rounded-2xl bg-fuchsia-300/15 text-fuchsia-200">◈</div>
                        <h3 class="text-xl font-bold">Premium visual system</h3>
                        <p class="mt-3 leading-7 text-slate-400">Dark, cinematic surfaces with precise typography and modular cards for future article feeds.</p>
                    </div>
                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 backdrop-blur">
                        <div class="mb-6 grid h-12 w-12 place-items-center rounded-2xl bg-amber-300/15 text-amber-200">●</div>
                        <h3 class="text-xl font-bold">Built for memberships</h3>
                        <p class="mt-3 leading-7 text-slate-400">Clear premium CTAs, member access points, and a landing structure ready for pricing.</p>
                    </div>
                </div>
            </section>

            <section id="pricing" class="mx-auto w-full max-w-7xl px-6 pb-24 lg:px-8">
                <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.07] shadow-2xl shadow-black/30 backdrop-blur">
                    <div class="grid gap-8 p-8 lg:grid-cols-[1fr_auto] lg:p-10">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-200">Premium access</p>
                            <h2 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Start with a calmer, sharper homepage.</h2>
                            <p class="mt-4 max-w-2xl text-slate-300">This frontend is now positioned as a premium editorial product rather than a default Laravel welcome page.</p>
                        </div>
                        <div class="flex items-center">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex rounded-full bg-cyan-300 px-7 py-4 font-bold text-slate-950 shadow-xl shadow-cyan-500/20 transition hover:-translate-y-1 hover:bg-cyan-200">Create account</a>
                            @else
                                <a href="#dispatch" class="inline-flex rounded-full bg-cyan-300 px-7 py-4 font-bold text-slate-950 shadow-xl shadow-cyan-500/20 transition hover:-translate-y-1 hover:bg-cyan-200">Explore issue</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
