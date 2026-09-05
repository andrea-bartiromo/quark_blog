# Kairus Editorial Foundations V1 — Audit iniziale

Missione 01 del cantiere `feat/kairus-editorial-foundations`. Sola lettura: nessuna
modifica applicata prima di questo audit.

## Baseline

- Repository: `andrea-bartiromo/quark_blog`
- SHA di partenza (`origin/main` dopo `git fetch origin main`): `a094e7e07e6b2dfda71fbb7c4dee0c38e69f9e5e`
  (`fix: make article saves atomic (#514)`)
- Branch creato da questo SHA: `feat/kairus-editorial-foundations`
- Working tree al momento della creazione del branch: pulito (nessuna modifica in
  sospeso, nessun file staged). Presente un unico file non tracciato,
  `playwright.local.config.js` in root — locale a questo ambiente di esecuzione,
  mai committato in nessun branch, ignorato da questo cantiere.

## Pull request aperte al momento dell'audit

Nessuna PR aperta tocca il perimetro di questo cantiere (CSS/componenti condivisi);
elencate per completezza dell'audit:

| PR | Titolo | Branch base |
|----|--------|--------------|
| #522 | Workspace Social Admin V1 (senza publishing esterno) | `a094e7e0` |
| #521 | Trust Layer: design + prototipo "Atlante visuale" | `a094e7e0` |
| #520 | Trust Layer: design + prototipo "Cosa sappiamo davvero" | `a094e7e0` |
| #519 | Trust Layer V1: pagina pubblica Metodologia | `a094e7e0` |
| #518 | Trust Layer V1: eleggibilità pubblica pagina autore | `a094e7e0` |
| #517 | Trust Layer V1: trasparenza pubblica revisione articolo | `a094e7e0` |
| #516 | Trust Layer V1: rendering pubblico fonti primarie | `a094e7e0` |
| #515 | Read-only article content hygiene tools | `a094e7e0` |
| #513 | docs: September operational closeout | `84a58032` |
| #512 | fix: redact newsletter provider failure logs | `84a58032` |
| #511 | feat: weekly scheduled-article certification | `84a58032` |
| #510 | test: human-reviewed Trova benchmark | `84a58032` |
| #507 | feat: enforce deterministic category pagination | `84a58032` |

Tutte già verdi in CI e senza commenti di revisione aperti (verificato in una
sessione precedente per #516–#522). Nessuna di queste PR crea
`public/css/editorial-system.css` né alcun file sotto
`resources/views/components/kairus/` — verificato puntualmente sotto.

## Verifica assenza di conflitti (Missione 01, punto 2)

```
find public/css -iname "*editorial*"                          → nessun risultato
find resources/views/components -iname "*kairus*"              → nessun risultato
ls resources/views/components/kairus                           → directory inesistente
```

Confermato: né `editorial-system.css` né alcun componente
`resources/views/components/kairus/*` esistono già su `main` né in nessuna PR
aperta. Questo cantiere parte da zero, nessuna sovrapposizione da risolvere.

## Token di brand già esistenti (riferimento, non perimetro)

Lettura read-only di `public/css/style.css` per ancorare i nuovi token
`--kairus-*` al linguaggio visivo reale del sito, senza copiarlo 1:1 (i nuovi
token sono un sistema editoriale più raffinato, pensato per una futura
adozione, non un duplicato):

- Font già caricati globalmente in `head.blade.php`: **Fraunces** (display) e
  **Plus Jakarta Sans** (corpo/interfaccia) — nessun nuovo font verrà caricato
  da `editorial-system.css` (Missione 17): i nuovi token di tipografia
  riferiscono queste stesse famiglie già disponibili.
- Colori attuali: `--primary:#0d9488` (teal), `--accent:#f97316` (arancio
  vivo), `--ink:#111827`, `--paper:#fafaf9`/`--paper-warm:#f5f5f4`.
- Il nuovo accento ambra richiesto dal cantiere è **volutamente più discreto**
  dell'arancio CTA esistente (`#f97316`): un dorato smorzato, non un secondo
  accento competitivo.

## Punto di inserimento CSS (Missione 03, ricognizione)

`resources/views/layouts/partials/head.blade.php` carica in ordine:
`style.css` → `frontend-hardening.css` → `@yield('home_css')` →
`public-premium.css` → `public-unified.css` → `premium-fixes.css` →
`@yield('head')`. Il nuovo `editorial-system.css` verrà aggiunto subito dopo
`premium-fixes.css` e prima di `@yield('head')`: ultimo CSS pubblico fisso,
senza alterare l'ordine di nessuno dei cinque file esistenti.

## Esito

Nessun blocco. Il cantiere procede dalla Missione 02.
