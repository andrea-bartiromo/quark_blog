# Fonti primarie pubbliche (V1)

## Cosa fa

Mostra pubblicamente, sulla pagina articolo, il contenuto di
`Article::primary_sources` — un campo `TEXT` nullable già esistente
(colonna aggiunta da `2026_05_02_062119_add_verification_fields_to_articles_table.php`,
parte del flusso di verifica editoriale Admin/Redazione, fuori scope di
questo lavoro). Questa modifica **non tocca né trasforma** il dato salvato:
legge, interpreta solo per la presentazione, non scrive mai.

## Contratto di input

`primary_sources` è testo libero, non JSON, non una struttura riga
garantita. Ogni riga (separata da `\n`, `\r\n` o `\r`) viene classificata da
`App\Services\ArticlePrimarySourcesParser::parse()`:

- **Link**: la riga, per intero (dopo `trim()`), è un URL assoluto con
  schema `http` o `https` (`filter_var(..., FILTER_VALIDATE_URL)` +
  controllo esplicito dello schema). Solo in questo caso diventa un
  `<a href>`.
- **Testo**: qualunque altra riga — testo descrittivo, URL con schema non
  sicuro (`javascript:`, `data:`, `vbscript:`, ...), URL relativo, testo
  misto a un URL, markup HTML — resta testo semplice, sempre escapato,
  **mai perso**.
- Righe vuote/solo spazi: scartate silenziosamente.
- `null` o stringa vuota/whitespace: nessun elemento, il pannello non
  viene renderizzato affatto (vedi sotto).

Deliberatamente **non** viene tentata l'estrazione di un URL incorporato
dentro una riga di testo più lunga (es. `"Fonte: https://..."` resta
testo semplice per intero): un'estrazione parziale via regex
aumenterebbe la superficie di attacco e la possibilità di link
fuorvianti costruiti ad arte, per un guadagno minimo.

## Rendering

`resources/views/components/article/primary-sources.blade.php`:
componente Blade anonimo e riusabile, riceve solo l'array già
classificato (mai la stringa grezza — resta testabile senza costruire un
`Article`). Riusa `.article-premium__panel` (stile già esistente,
nessuna CSS globale nuova). Ogni link esterno ha
`rel="nofollow noopener noreferrer"` e `target="_blank"`, con un
`<span class="sr-only">` che dichiara l'apertura in nuova scheda per gli
screen reader. Lista semantica (`<ul>/<li>`), heading proprio (`<h3>`)
referenziato via `aria-labelledby` sulla `<section>`.

Escaping: sia il testo sia l'URL passano da `{{ }}` (mai `{!! !!}`) —
markup ostile in `primary_sources` viene mostrato come testo letterale,
mai eseguito.

## Assenza vs. non interpretabilità

- **Nessuna fonte disponibile** (`null`/vuoto/solo spazi): il componente
  non renderizza nulla — nessun riquadro vuoto, nessun messaggio
  fuorviante.
- **Fonti presenti ma non riconoscibili come URL**: vengono comunque
  mostrate come testo — mai sostituite da un placeholder, mai trattate
  come "assenti".

## Coesistenza con il blocco "Fonti" legacy

`articles/partials/body.blade.php` contiene già un blocco "Fonti"
distinto, derivato da un delimitatore `---` dentro `Article::body` (vedi
commento nel file). È un meccanismo diverso, con dati potenzialmente
diversi per lo stesso articolo. Questa modifica **non li unifica**: i due
blocchi possono comparire entrambi sulla stessa pagina, con intestazioni
distinte ("Fonti" per il legacy, "Fonti primarie" per il nuovo). Unificarli
è una decisione editoriale futura (probabile migrazione progressiva dei
contenuti verso `primary_sources`), non tecnica.

## Limiti di questa V1

- Nessuna deduplicazione/validazione incrociata col blocco "Fonti" legacy.
- Nessun limite esplicito di lunghezza/numero di righe (eredita il limite
  della colonna `TEXT`, ~64KB) — non è mai stato un problema osservato nei
  dati esistenti (valori di test tipici: una singola riga).
- Nessuna verifica di raggiungibilità dell'URL (nessuna chiamata di rete
  in questa V1 — puramente presentazione locale).

## Test

- `tests/Unit/ArticlePrimarySourcesParserTest.php`: classificazione
  link/testo, input vuoto/blank, schemi non sicuri, markup ostile
  preservato come testo, Unicode, righe multiple, righe vuote.
- `tests/Feature/ArticlePublicPrimarySourcesTest.php`: rendering end-to-end
  sulla pagina articolo — link, testo semplice, escaping di markup ostile,
  assenza del pannello quando non c'è nulla da mostrare, coesistenza col
  blocco legacy.

## Responsabilità editoriale

Il contenuto di `primary_sources` resta responsabilità di chi lo scrive in
Admin/Redazione (fuori scope): questa modifica non introduce alcuna
validazione editoriale (es. "è davvero una fonte primaria?"), solo un
contratto di presentazione sicuro per qualunque cosa sia già stata salvata.
