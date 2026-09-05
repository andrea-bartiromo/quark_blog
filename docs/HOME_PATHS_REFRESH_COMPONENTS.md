# Home + Percorsi Visual Adoption — Matrice componenti

Cantiere D, Prompt 06. Ogni blocco mappato verso un componente esistente di
Kairus Editorial Foundations V1 — **nessun undicesimo componente creato**.
Dove un blocco ha una dipendenza JS esistente incompatibile con il
contratto "un solo link/nessun interattivo annidato" di un componente, la
scelta è documentata come esclusione motivata ("dove compatibile", Prompt 16),
non un'omissione silenziosa.

| Blocco | Componente Kairus | Note |
|---|---|---|
| Home — articolo in evidenza | `x-kairus.image-frame` + `x-kairus.article-meta` | **Non** `article-card`: il titolo dell'evidenza è l'H1 della pagina, mentre `article-card` renderizza sempre un `<h3>` interno — incompatibile con l'unico H1 richiesto. Markup dell'H1/link riorganizzato manualmente con i token del sistema. |
| Home — "Trending now" | Nessuno (lista testuale compatta, CSS namespaced) | Nessun componente della V1 copre una classifica numerata; non ne viene creato uno nuovo (fuori scope). Solo classi `.kairus-*` di rifinitura tipografica. |
| Home — Ultimi articoli | `x-kairus.article-card` (variant `featured` sulla prima card, `standard` sulle altre) | Mappatura diretta e pulita: la partial esistente ha già un solo link per card. |
| Home — Percorsi | `x-kairus.section-heading` + `x-kairus.path-card` | Mappatura diretta: card e intro esistenti hanno già struttura compatibile. |
| Home — Newsletter | `x-kairus.section-heading` (per istruzione esplicita del Prompt 46, non `form-shell`: il suo contenitore a riquadro non è coerente con questa banda orizzontale sottile) | Il `<form>` reale resta fuori dal componente, invariato byte per byte — nessuna modifica ad action/CSRF/campi. |
| Home — Speciale Turing | CSS namespaced (nessun componente `special-banner` esiste nelle fondamenta — non ne viene creato uno) | Le fondamenta V1 non definiscono un componente banner/speciale. Il Prompt 52 stesso prevede questo caso: "altrimenti usa classi namespaced senza creare un undicesimo componente." |
| Home — Categorie | CSS namespaced sulla struttura esistente | Il carosello è interamente JS-dipendente (`data-category-carousel/-track/-prev/-next`, script in `home.blade.php`): nessun componente Kairus della V1 modella un carosello. Struttura/attributi invariati, solo rifinitura visiva. |
| Indice Percorsi — apertura | `x-kairus.section-heading` (con `headingLevel="1"`, per requisito esplicito del Prompt 73) | |
| Indice Percorsi — card | `x-kairus.path-card` | La card stessa (link/slug/immagine/descrizione/count) diventa `x-kairus.path-card`. Il toggle "Anteprima delle tappe" (bottone + pannello, JS invariato) resta un **fratello** del componente dentro lo stesso `<li class="path-card kairus-path-card-host" data-percorso-id="...">` — mai annidato nell'unico `<a>` del componente. Verificato che il CSS legacy che ne governa la visibilità (`.path-card:hover .path-card__preview`, `.path-card:focus-within .path-card__preview`) usa combinatori discendenti, non genitore diretto: continua a funzionare identico con `.path-card` sull'`<li>` invece che sull'`<a>`. `.kairus-path-card-host` neutralizza il riquadro legacy di `.path-card` (bordo/ombra/sfondo), altrimenti sommato a quello — distinto e completo — già fornito da `x-kairus.path-card` (doppio riquadro, verificato via screenshot e corretto). Toggle e pannello portano anche `.kairus-path-card-host__toggle`/`__preview` (solo margini) accanto alle classi legacy, cosi editorial-system.css resta interamente `.kairus-*`. Il suggerimento del pillar confluisce nello slot `progress` del componente come "Si parte da: {titolo}" — stesso dato (`pillarArticle->title`), microcopy leggermente diversa dall'originale "Da qui si parte" + titolo, allineata alla formula libera del componente condiviso. |
| Indice Percorsi — stato vuoto | `x-kairus.empty-state` | Il fallback `@empty` esistente diventa questo componente, stessa condizione di attivazione. |
| Dettaglio Percorso — hero | CSS namespaced sulla struttura esistente | Il hero ha un bottone di apertura lightbox (`data-media-viewer-target`) accanto al testo — non nidificato, ma la combinazione titolo/eyebrow/meta/CTA immagine non corrisponde a nessun singolo componente della V1; rifinita con i token direttamente. |
| Dettaglio Percorso — pillar | CSS namespaced sulla struttura `.path-pillar` esistente | Struttura a due blocchi (numero+intro fuori dal link, contenuto dentro) non corrisponde al contratto a singolo-link di `path-step`; il link esistente resta unico e già pulito. |
| Dettaglio Percorso — tappe | `x-kairus.path-step` | Consolidamento a singolo link per tappa (oggi due link sorella allo stesso `href`, mai annidati ma ridondanti): la card diventa un solo `<a>`, il micro-testo "Leggi l'articolo →" viene assorbito dall'affordance dell'intera card (stesso pattern già in uso in `latest-articles.blade.php`). Il testo di transizione (`pivot->transition_text`), dato reale non coperto dall'API del componente, resta un paragrafo separato subito dopo la card, non dentro il link. |
| Dettaglio Percorso — stato vuoto tappe | Nessuna modifica | Il fallback attuale (`<li class="paths-empty">Nessun articolo pubblicato...</li>`) resta testuale semplice dentro l'`<ol>` esistente: `x-kairus.empty-state` è pensato per una superficie di pagina, non per un singolo elemento di lista dentro una sequenza numerata. |
| Dettaglio Percorso — form "Avvisami" | Nessuna modifica | Vedi file map: toggle JS proprio, incompatibile con `form-shell` senza cambiarne il comportamento. |

## Componenti della V1 non utilizzati in questo cantiere

`x-kairus.page-header` (nessuna delle tre pagine usa il tone/compact di
un'intestazione a piena larghezza — l'indice Percorsi usa `section-heading`
per esplicita istruzione del Prompt 73, home e dettaglio Percorso hanno già
i propri hero) e `x-kairus.trust-panel` (fuori scope: appartiene
all'adozione Trust Layer, un cantiere separato secondo il piano di adozione
di Kairus Editorial Foundations V1).
