# Trust Layer pubblico — QA (Cantiere H, Prompt 200-207)

Verifica empirica (Playwright + Chromium locale) su un articolo con
combinazione completa: bio autore lunga, handle Twitter, URL LinkedIn
molto lungo, cover con tutti i crediti, fonte con URL molto lungo.

## Mobile (Prompt 200)

320px e 375px: nessun overflow orizzontale (`clientWidth === scrollWidth`
a 320px, verificato con dati volutamente lunghi su bio/social/URL
fonte). Screenshot a 375px: blocco autore (avatar+nome+link+bio+social),
pannello Fonti, tutti leggibili e ben distanziati, nessuna sovrapposizione.

## Tastiera (Prompt 201)

- Link social autore (`kairus-focusable`): outline visibile al focus
  (verificato: 3px solid, colore teal del design system).
- Link fonte cover: dentro un `<details>` chiuso di default — non
  raggiungibile da tastiera finché il pannello "Informazioni
  sull'immagine" non viene aperto (comportamento nativo di `<details>`,
  invariato, non introdotto da questo cantiere). Una volta aperto, la
  classe `kairus-focusable` è presente e funzionante (stessa verificata
  sui link social).
- Ordine di lettura: breadcrumb → hero (kicker, H1, meta) → corpo →
  Fonti → autore (con bio/social) → condividi — invariato rispetto al
  Cantiere E, solo bio/social aggiunti come contenuto in coda al blocco
  autore esistente, mai in mezzo.

## Contrasto (Prompt 202)

Bio e link social usano gli stessi token già certificati dal resto del
sistema (`--kairus-ink-soft` per il testo, `--kairus-teal-deep` per i
link) — nessun nuovo colore introdotto in questo cantiere, nessun
grigio-su-grigio.

## Stampa (Prompt 203)

Verificato con `page.emulateMedia({ media: 'print' })`: pannello Fonti
e blocco autore restano visibili (`display` non `none`) — nessun CSS
esistente li nasconde in stampa, nessun foglio di stile dedicato
introdotto (non necessario: nulla da nascondere).

## Fallback completi (Prompt 204) e nessun blocco vuoto (Prompt 205)

Coperti da `PublicTrustLayerTest`:
`test_full_combination_of_trust_elements_renders_together` (tutto
insieme) e `test_no_trust_blocks_are_orphaned_when_no_trust_data_exists`
(nessun pannello/intestazione orfano quando bio, social, fonti e
crediti cover sono tutti assenti — l'autore minimo, nome+link, resta
comunque presente).

## Integrazione articolo/Trust (Prompt 206)

`test_full_combination_of_trust_elements_renders_together` verifica
cover con crediti completi, autore con bio+social, fonti, e Percorso/
correlati (già coperti dai test del Cantiere E) sulla stessa pagina
contemporaneamente, con un solo H1.

## Privacy e dati interni (Prompt 207)

Verificato per lettura di `articolo.blade.php` e le sue partial (nessuna
riga cambiata rispetto a questo controllo): nessun `user_id` numerico
grezzo, nessuna email dell'autore, nessuna nota di revisione
(`verification_notes`), nessuno stato di verifica
(`verification_status`), nessun `verified_by`, nessuno snapshot interno
esposto in nessuna vista pubblica. `PublicTrustLayerTest` verifica
esplicitamente l'assenza di claim di revisione/metodologia/disclosure.
