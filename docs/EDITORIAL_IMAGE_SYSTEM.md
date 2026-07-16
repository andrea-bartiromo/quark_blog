# Sistema Immagini Editoriali — Quark Blog

> **Stato:** documento di design e standard editoriale.
> **Non modifica alcuna immagine, vista, stile o codice del progetto.**
> Definisce le regole che guideranno la produzione, l'upload e la sostituzione delle immagini future di Quark, a partire dall'infrastruttura tecnica già esistente (`ImageService`, placeholder SVG, metadati `cover_*`) descritta in [DOCUMENTAZIONE.md](../DOCUMENTAZIONE.md#10-sistema-immagini-e-metadati-cover) e [README.md](../README.md#gestione-immagini).

---

## Indice

1. [Filosofia](#1-filosofia)
2. [Tipologie di immagini](#2-tipologie-di-immagini)
3. [Stile grafico](#3-stile-grafico)
4. [Palette](#4-palette)
5. [Tipografia nelle immagini](#5-tipografia-nelle-immagini)
6. [Rapporti (aspect ratio)](#6-rapporti-aspect-ratio)
7. [Risoluzioni](#7-risoluzioni)
8. [Naming](#8-naming)
9. [Accessibilità](#9-accessibilità)
10. [SEO](#10-seo)
11. [Prompt standard](#11-prompt-standard)
12. [Prompt specifici per area tematica](#12-prompt-specifici-per-area-tematica)
13. [Immagini da rifare](#13-immagini-da-rifare)
14. [Roadmap di adozione](#14-roadmap-di-adozione)
15. [Appendice — Audit dello stato attuale](#15-appendice--audit-dello-stato-attuale)

---

## 1. Filosofia

Quark è un blog di **divulgazione scientifica** (fisica, biologia, tecnologia, spazio — vedi `config/laboratorio.php`, tagline: *"La scienza spiegata come si deve"*). Le immagini non sono decorazione: sono parte del racconto editoriale e devono trasmettere le stesse qualità del testo.

Cinque principi guidano ogni immagine prodotta per Quark:

1. **Autorevolezza** — l'immagine deve comunicare rigore, non sensazionalismo. Niente iconografia da clickbait (punti esclamativi, frecce urlate, volti scioccati), niente stock photo generiche da ufficio.
2. **Divulgazione, non decorazione** — ogni immagine deve avere un legame concettuale chiaro con l'argomento trattato (il fenomeno fisico, l'oggetto tecnologico, il contesto scientifico), non essere un semplice riempitivo cromatico.
3. **Modernità sobria** — stile contemporaneo, pulito, mai retrò o "clip art". L'eccezione è la sezione **Turing**, dove una grana storica/vintage è coerente con il contenuto (macchine Enigma, calcolatori d'epoca) e va mantenuta come cifra stilistica di quella sola sezione.
4. **Accessibilità** — ogni immagine deve poter essere descritta a parole (per `alt`) senza perdere informazione essenziale: se un concetto vive *solo* dentro il testo incollato nell'immagine, l'immagine ha fallito il suo scopo.
5. **Coerenza** — stesso stile, stessa palette, stessa griglia di rapporti e risoluzioni in tutte le aree del sito (Admin, Redazione, pubblico), indipendentemente da chi produce l'immagine o quando.

Questo documento traduce questi principi in regole operative concrete: stile, palette, rapporti, risoluzioni, naming, accessibilità, SEO e prompt riutilizzabili.

---

## 2. Tipologie di immagini

Il sistema distingue otto tipologie, ciascuna con un ruolo tecnico ed editoriale preciso. La colonna "Meccanismo attuale" riporta il comportamento reale già implementato (nessuna modifica proposta in questa PR):

| Tipologia | Ruolo editoriale | Meccanismo attuale |
|---|---|---|
| **Cover articoli** | Immagine di apertura di un articolo (hero dell'`articolo.blade.php`, card in `notizie`/`categoria`/`autore`) | Upload via `Admin\ArticleController` o `Redazione\ArticleController` → `ImageService`; campo `cover_image` + metadati `cover_alt/caption/credit/source/source_url/license` |
| **Categorie** | Immagine identificativa di una categoria (`Category.image`, banner di sezione) | Upload via `Admin\CategoryController` → `ImageService`, salvata in `assets/img/categories/` |
| **Turing** | Immagini della sezione editoriale speciale dedicata ad Alan Turing (hero, timeline, approfondimenti storici) | Contenuto gestito da `Admin\TuringController`, immagini in `assets/img/turing/`, referenziate dal JSON in `special_pages` |
| **AI / Media library** | Immagini generiche caricate in libreria, riusabili in più contesti editoriali (non legate a un solo articolo) | Upload via `Admin\MediaController` → `ImageService`, modello `Media` |
| **Open Graph** | Anteprima di condivisione social (Facebook, LinkedIn, Twitter/X, WhatsApp) | Riusa `cover_image` dell'articolo (fallback a `hero-placeholder.svg`), dichiarata in `articolo.blade.php` con `og:image:width=1200`/`og:image:height=630` |
| **Placeholder** | Immagine di riserva quando un articolo/autore non ha copertina | `placeholder-1.svg` (card) e `hero-placeholder.svg` (OG fallback), SVG generati internamente, `role="img" aria-hidden="true"` |
| **Avatar** | Foto profilo di autori/redattori | Upload via `Redazione\ProfileController::updatePhoto()`, salvato con `Storage::disk('public')` — **percorso indipendente da `ImageService`**, nessun resize/compressione automatica applicata oggi |
| **Hero** | Banner ampio di apertura sezione (Turing hero, eventuali future hero di categoria/home) | Stesso meccanismo di cover/categorie a seconda del contesto; non è un tipo di upload a sé ma un *ruolo visivo* (immagine grande, in alto, di apertura) |

Il rapporto naming↔ruolo va sempre rispettato: un'immagine "Turing" non deve essere riusata come cover articolo generica e viceversa — ogni tipologia ha una propria coerenza stilistica interna (vedi §3) anche quando condivide la stessa infrastruttura tecnica.

---

## 3. Stile grafico

**Stile ufficiale: fotografico/cinematografico realistico**, generato con AI text-to-image, con color grading coerente per area tematica.

Questa non è una preferenza astratta: è la formalizzazione dello stile **già dominante e già di fatto adottato** nel materiale esistente (immagini spazio, AI, Turing — vedi audit in §15) e viene scelto come standard ufficiale per tre motivi:

- **Coerenza con quanto esiste**: la maggioranza delle immagini reali del progetto è già fotorealistica/cinematografica; adottare uno stile diverso (flat, isometrico, 3D cartoon) creerebbe una frattura visiva tra vecchio e nuovo materiale invece di una transizione graduale.
- **Autorevolezza scientifica**: per un blog di divulgazione, immagini fotorealistiche (nebulose, strumentazione, texture tecnologiche) comunicano rigore e concretezza meglio di illustrazioni stilizzate, che tendono a un registro più "prodotto consumer/startup".
- **Longevità**: uno stile flat/illustrativo invecchia rapidamente con le mode UI; una fotografia concettuale ben fatta resta leggibile nel tempo.

Sono **esclusi** dallo standard:
- **Flat design puro** (icone piatte, nessuna profondità) — troppo "prodotto SaaS", non comunica scienza.
- **Isometrico** — utile per infografiche tecniche, non per cover editoriali.
- **3D cartoon/render stilizzato** — rischia un registro infantile, in contrasto con l'autorevolezza richiesta.
- **Illustrazione vettoriale decorativa** — riservata esclusivamente ai placeholder SVG (§9) e a eventuali icone di interfaccia, mai alle cover.

**Eccezione dichiarata — Turing**: la sezione mantiene una grana fotografica *vintage/storica* (toni caldi, seppia, illuminazione da tavolo d'epoca — coerente con macchine Enigma e calcolatori storici) come sotto-stile riconoscibile della sola sezione, non come stile alternativo generale.

**Placeholder**: restano un'eccezione intenzionale e già corretta — geometria astratta minimale (icona "foto mancante" stilizzata), non fotorealistica. Il loro ruolo è segnalare l'assenza di un'immagine reale, non sostituirla stilisticamente (vedi §9).

---

## 4. Palette

La palette ufficiale è quella **già in uso** nel brand (favicon, placeholder SVG, CSS pubblico in `public/css/*.css`), qui formalizzata come riferimento univoco per la produzione di nuove immagini:

| Ruolo | Colore | Hex | Uso |
|---|---|---|---|
| **Primario — Teal** | Teal Quark | `#0d9488` | Colore del marchio (punto del wordmark, icone, link, elementi grafici nei placeholder) |
| **Primario scuro** | Teal scuro | `#0f766e` | Varianti hover/scure del primario, sfondi scuri con accento teal |
| **Secondario — Accento** | Orange | `#f97316` | Accento puntuale (mai dominante): dettaglio nel favicon, punto luce nei placeholder |
| **Neutro scuro** | Slate 950/900 | `#020617` / `#0f172a` | Sfondi scuri, testo su chiaro, immagini "spazio/notte/tecnologia" |
| **Neutro medio** | Slate 500 | `#64748b` | Testo secondario, elementi desaturati di sfondo |
| **Neutro chiaro** | Slate 50/100 | `#f8fafc` / `#f1f5f9` | Sfondi chiari, gradienti di base nei placeholder |
| **Verde di conferma** | Emerald 100 | `#d1fae5` | Solo per stati UI (non per immagini editoriali) |

**Regole d'uso per le immagini generate:**

- Ogni immagine deve poter "dialogare" con almeno uno tra teal `#0d9488` e i neutri scuri `#020617`/`#0f172a` — come colore dominante di sfondo/atmosfera (temi spazio, tecnologia, notte) o come accento riconoscibile (bagliori, elementi luminosi, dettagli).
- L'arancio `#f97316` è un **accento**, non un colore dominante: va usato per un singolo punto di interesse (una luce, un dettaglio), mai per grandi superfici.
- Le immagini fotorealistiche non devono necessariamente contenere questi hex esatti (sarebbe innaturale per una nebulosa o un tessuto biologico), ma la **temperatura cromatica complessiva** deve restare compatibile: toni freddi/blu-teal per spazio, tecnologia, IA; toni caldi/seppia solo per Turing; verde naturale per ambiente; non usare palette sature primarie (rosso-giallo-blu puri) che confliggono con l'identità sobria del brand.
- Evitare gradienti arcobaleno o palette "hype tech" (viola-magenta-ciano al neon) associate ad altri brand AI: non fanno parte dell'identità Quark.

---

## 5. Tipografia nelle immagini

**Regola generale: nessun testo dentro le immagini.**

Motivazioni (tecniche ed editoriali, non stilistiche):

1. **Duplicazione** — titolo, categoria e sottotitolo sono già resi come testo HTML reale (`<h1>`, kicker di categoria, `cover_caption`) sopra o accanto all'immagine (vedi `articles/partials/hero.blade.php`). Il testo nell'immagine è ridondante.
2. **Accessibilità** — il testo rasterizzato non è letto dagli screen reader e non viene descritto dall'`alt`, che deve invece descrivere il *contenuto visivo* (vedi §9).
3. **Manutenibilità** — un titolo che cambia (refusi, aggiornamenti editoriali) richiederebbe di rigenerare l'immagine; il testo HTML si modifica in un secondo.
4. **SEO** — i motori di ricerca non indicizzano testo rasterizzato in un'immagine; l'informazione testuale deve vivere nel markup.
5. **Localizzazione** — un'immagine senza testo resta riusabile anche in eventuali contesti multilingua futuri.

**Casi eccezionali ammessi** (unici):

- **Diagrammi tecnici esplicativi** all'interno del corpo articolo (non come cover), dove etichette minime (es. assi di un grafico, nome di un componente in uno schema) sono strumentali alla comprensione e non duplicano testo editoriale già presente altrove.
- **Elementi di interfaccia reali fotografati/catturati** (screenshot di un'app, schermata di un dispositivo) dove il testo fa parte del soggetto documentato, non è un'aggiunta grafica.

In entrambi i casi eccezionali, il testo deve restare minimo, funzionale, e comunque accompagnato da un `alt`/didascalia che lo renda accessibile.

---

## 6. Rapporti (aspect ratio)

| Tipologia | Rapporto | Motivazione |
|---|---|---|
| **Cover articoli** | **16:9** | Standard editoriale/video moderno, si adatta bene a hero full-width e a card responsive; coerente con la maggior parte del materiale fotorealistico esistente (temi spazio/tecnologia già vicini a questo rapporto) |
| **Open Graph** | **1.91:1** | Rapporto raccomandato da Facebook/LinkedIn/Twitter per le anteprime di condivisione — **già usato correttamente** da `hero-placeholder.svg` (1200×630), qui esteso a tutte le immagini OG |
| **Card / Placeholder** | **16:10** | **Già in uso** in `placeholder-1.svg` (1200×750) per le card di articoli, categorie e autore — mantenuto come standard per continuità con l'SVG esistente |
| **Categorie (banner)** | **16:9** | Stesso rapporto delle cover articolo, per coerenza visiva quando categoria e articolo appaiono nella stessa griglia |
| **Turing (hero storico)** | **16:9** | Allinea la sezione (oggi a ~1.78:1, molto vicino) allo standard generale senza alterarne il registro fotografico |
| **Avatar** | **1:1 (quadrato)** | Standard universale per foto profilo, compatibile con qualunque contenitore circolare/quadrato in interfaccia |
| **Hero (banner ampio di sezione)** | **21:9 o 16:9** | 21:9 solo per banner a piena larghezza molto bassi (es. testata di sezione); 16:9 per hero che devono restare leggibili anche più piccoli (mobile) |

**Regola di scelta:** se l'immagine deve comparire sia come card piccola sia come hero grande (caso tipico della cover articolo), prevale il rapporto **16:9**: si ritaglia meglio verso quadrato/16:10 di quanto un 16:10 si estenda verso 16:9 senza perdita.

---

## 7. Risoluzioni

Le risoluzioni qui indicate sono lo **standard di produzione/upload** per le nuove immagini. Sono compatibili con i vincoli tecnici già esistenti in `ImageService` (nessuna modifica al codice): ogni preset attuale definisce una larghezza **massima** oltre la quale l'immagine viene ridotta (`Admin\ArticleController`/`Redazione`: 1600px; `Admin\MediaController`: 1600px con ricompressione sempre attiva; `Admin\CategoryController`: 1200px) — le risoluzioni sotto sono scelte per rientrare in questi tetti senza upscaling.

| Tipologia | Risoluzione | Rapporto | Note |
|---|---|---|---|
| **Cover articoli** | **1200×675 px** | 16:9 | Sotto il tetto di 1600px di `Admin`/`Redazione\ArticleController`: nessun ridimensionamento forzato, solo eventuale ricompressione |
| **Open Graph** | **1200×630 px** | 1.91:1 | Standard di settore; stessa risoluzione già usata da `hero-placeholder.svg` |
| **Categorie** | **1200×675 px** | 16:9 | Alla larghezza massima (1200px) consentita da `Admin\CategoryController` — **importante**: 1200px è già il *tetto*, non caricare immagini più larghe pensando vengano "migliorate" dal resize (vedi §15, immagini categoria attuali a 1536px, oltre il tetto) |
| **Turing / Hero narrativi** | **1600×900 px** | 16:9 | In linea con la larghezza già utilizzata da molte immagini esistenti (~1536–1774px) |
| **Placeholder** | 1200×750 px (card) / 1200×630 px (OG) | 16:10 / 1.91:1 | Già definitivo, generato via SVG — nessuna azione richiesta |
| **Avatar** | **800×800 px** | 1:1 | Formato quadrato ad alta densità, sufficiente per qualunque visualizzazione circolare/piccola in UI |
| **Media library (generiche)** | **1600×900 px** (16:9) o **1200×1200 px** (1:1) a seconda del soggetto | variabile | La libreria Media è pensata per contenuti riusabili in contesti diversi: preferire 16:9 per foto di scena, 1:1 per soggetti isolati (oggetti, ritratti, diagrammi) |

Tutte le risoluzioni sono pensate come **file sorgente da caricare**, non come dimensioni di rendering finale a schermo: il browser scala l'immagine secondo il layout (`img` responsive nei blade esistenti), quindi non serve produrre multipli formati/risoluzioni (`srcset`) — coerente con quanto già dichiarato in `DOCUMENTAZIONE.md` (§10: *"Non implementato: generazione WebP/AVIF aggiuntiva, thumbnail, `srcset`"*), che questo documento non intende cambiare.

---

## 8. Naming

Il naming tecnico del file **su disco** resta quello già gestito da `ImageService::buildFileName()` (slug del nome originale + suffisso univoco per timestamp/hash, es. `nome-file-20260716143210-a1b2c3.png`) — questo documento **non modifica** quel meccanismo.

Questo documento definisce invece la convenzione per il **nome del file sorgente prima dell'upload** (quello che l'autore/redattore/designer assegna al file sul proprio computer prima di caricarlo), così che `getClientOriginalName()` (usato per costruire lo slug) produca sempre un risultato leggibile e coerente, invece dei nomi non descrittivi trovati nell'audit (es. `chatgpt-image-11-mag-2026-08-55-24...png`, `123.png` — vedi §15).

**Convenzione ufficiale (nome file sorgente, prima dell'upload):**

```
<tipologia>-<argomento-in-slug>-<variante-opzionale>.<estensione>
```

- `<tipologia>`: `cover`, `categoria`, `turing`, `hero`, `avatar`, `og`
- `<argomento-in-slug>`: 2–4 parole minuscole separate da trattino, in italiano, che descrivono il soggetto (non il tema generico della sezione, il soggetto specifico dell'immagine)
- `<variante-opzionale>`: numero progressivo solo se esistono più immagini per lo stesso soggetto (`-01`, `-02`)

**Esempi corretti:**

```
cover-buco-nero-supermassiccio.jpg
categoria-intelligenza-artificiale.jpg
categoria-spazio.jpg
turing-macchina-enigma-01.jpg
turing-bomba-bletchley-park.jpg
hero-missione-artemis.jpg
avatar-redazione-mario-rossi.jpg
og-fusione-nucleare-iter.jpg
```

**Da evitare:**

```
IMG_4821.png                          ← non descrittivo
chatgpt-image-11-mag-2026-...png      ← nome di export dello strumento, non del soggetto
foto2_finale_DEFINITIVA(2).jpg        ← versioning informale, caratteri non urlencoded
Ambiente.PNG                          ← maiuscole, nessuno slug del soggetto reale
```

**Formato file:** JPEG per fotografie con molte sfumature/gradazioni cromatiche (spazio, ambienti reali), PNG solo se è necessaria trasparenza reale, WebP quando lo strumento di origine lo supporta nativamente. L'estensione del file deve sempre corrispondere al formato reale del contenuto (vedi incoerenza rilevata in §15 — file `.jpg` con contenuto PNG).

---

## 9. Accessibilità

1. **Contrasto** — quando un'immagine è usata come sfondo di un elemento con testo sovrapposto (kicker, titolo nell'hero), il testo HTML reale deve restare leggibile tramite overlay/gradiente CSS già esistente (`article-premium__overlay`), non tramite regolazione manuale della luminosità dell'immagine stessa. L'immagine va comunque prodotta con un'area (in alto o in basso, a seconda del layout) sufficientemente scura/uniforme da non richiedere overlay eccessivamente opachi.
2. **Leggibilità del soggetto** — il soggetto principale deve essere riconoscibile anche a bassa risoluzione (thumbnail di card, ~300px di larghezza): evitare composizioni con dettagli minuti che si perdono al rimpicciolimento.
3. **Testo alternativo (`alt`)** — ogni cover deve avere un `cover_alt` esplicito che descriva **il contenuto visivo**, non il titolo dell'articolo (il fallback al titolo, già implementato in `hero.blade.php`, resta un fallback per compatibilità, non l'obiettivo). Esempio:
   - ❌ `alt="Le nuove scoperte su Marte"` (è il titolo, non descrive l'immagine)
   - ✅ `alt="Superficie rossa di Marte fotografata da un rover, con dune e crateri in primo piano"`
4. **Contenuti puramente decorativi** — le immagini che non aggiungono informazione (placeholder, texture di sfondo) devono restare marcate `aria-hidden="true"` e senza `alt` significativo, come già fatto correttamente in `placeholder-1.svg`/`hero-placeholder.svg` (`role="img" aria-hidden="true"`). Questo pattern va mantenuto per qualunque futura immagine puramente decorativa.
5. **Nessuna informazione solo visiva** — se un'immagine comunica un dato (es. un grafico), il dato deve essere disponibile anche in forma testuale nel corpo dell'articolo: l'immagine illustra, non sostituisce.

---

## 10. SEO

Linee guida coerenti con i meccanismi **già implementati** nelle PR precedenti (metadati `cover_*`, Schema.org `NewsArticle`, Open Graph — vedi `DOCUMENTAZIONE.md` §10 e §14 "SEO"):

| Campo | Linea guida |
|---|---|
| **Nome file** (`cover_image`, tramite naming sorgente §8) | Descrittivo, in slug, in italiano, coerente con l'argomento — contribuisce alla ricerca per immagini di Google |
| **`cover_alt`** | Sempre compilato esplicitamente (non lasciato al fallback sul titolo) — descrive il contenuto visivo, 8–15 parole, nessuna keyword stuffing |
| **`cover_caption`** | Facoltativa, usata solo quando aggiunge contesto reale non già nel corpo (es. dettaglio tecnico della fonte); non ripetere il titolo |
| **`cover_credit`** | Sempre compilato quando l'immagine non è originale Quark (fotografo, agenzia, ente — es. "ESA/Hubble", "NASA/JPL-Caltech") |
| **`cover_source`** + **`cover_source_url`** | Sempre compilati per immagini di provenienza esterna (archivi scientifici, enti spaziali, banche immagini con licenza); `cover_source_url` deve essere un URL valido (già validato server-side con la regola `url`) |
| **`cover_license`** | Sempre indicata per immagini non originali (es. "CC BY 4.0", "Public Domain", "Licenza editoriale Quark") — coerente con l'obbligo di attribuzione della licenza stessa |

Questi campi sono già mostrati pubblicamente sotto la cover quando valorizzati (`articles/partials/body.blade.php`: didascalia, credito, fonte con link `rel="noopener noreferrer"`, licenza) — il compito di questo documento è normare **quando e come compilarli in modo coerente**, non introdurre nuovi campi.

**Open Graph**: mantenere sempre un `cover_image` valorizzato per articoli destinati a essere condivisi sui social (il fallback a `hero-placeholder.svg` è corretto per articoli senza cover, ma un OG placeholder è meno efficace di un'immagine reale in termini di click-through sui social).

---

## 11. Prompt standard

Prompt riutilizzabile di base, da adattare con il soggetto specifico (vedi §12 per varianti tematiche). Pensato per strumenti text-to-image generici.

### Versione italiana

```
Fotografia editoriale scientifica, stile cinematografico e fotorealistico,
alta definizione. Soggetto: [DESCRIZIONE SOGGETTO SPECIFICO]. Illuminazione
drammatica ma naturale, profondità di campo marcata, palette cromatica sui
toni blu-teal freddi con un singolo accento arancione caldo, nessun testo o
scritta nell'immagine, nessun logo o watermark, composizione pulita con
spazio negativo per eventuale sovrapposizione di testo editoriale in alto o
in basso. Stile: fotografia scientifica/documentaristica di alta qualità,
non illustrazione, non cartoon, non render 3D stilizzato. Formato 16:9.
```

### English version

```
Scientific editorial photography, cinematic and photorealistic style, high
definition. Subject: [SPECIFIC SUBJECT DESCRIPTION]. Dramatic but natural
lighting, strong depth of field, cool blue-teal color palette with a single
warm orange accent, no text or lettering anywhere in the image, no logo or
watermark, clean composition with negative space for editorial text overlay
at the top or bottom. Style: high-quality scientific/documentary photography,
not illustration, not cartoon, not stylized 3D render. 16:9 aspect ratio.
```

---

## 12. Prompt specifici per area tematica

Template per le categorie/argomenti ricorrenti del blog (coerenti con `config('laboratorio.categories')`: Intelligenza Artificiale, Energia & Clima, Salute & Biotech, Tecnologia & Società, Spazio, Ambiente — più i due extra richiesti, Fisica e Matematica, per contenuti trasversali). Ogni prompt eredita le regole di §11 (nessun testo, palette coerente, 16:9) e aggiunge solo il soggetto.

**Astronomia / Spazio**
```
[EN] Deep space scene, nebula or galaxy or planetary surface, cinematic
astrophotography style reminiscent of real space telescope imagery, cool
blue-purple-teal tones with bright star highlights, vast scale, no text,
no UI elements, 16:9.
```

**Intelligenza Artificiale**
```
[EN] Abstract representation of artificial intelligence and neural networks,
glowing data connections and nodes, dark background with teal and orange
light accents, subtle human silhouette or circuit-like pattern optional,
avoid literal robot clichés, no text, no logos, 16:9.
```

**Fisica**
```
[EN] Conceptual visualization of a physical phenomenon (particles, waves,
energy fields, laboratory equipment), precise and technical but visually
striking, cool color grading with teal accents, realistic lighting, no
text, no diagrams with labels, 16:9.
```

**Matematica**
```
[EN] Abstract geometric or fractal patterns suggesting mathematical
structure, rendered with photographic depth and lighting rather than flat
vector shapes, restrained color palette (teal, slate, single orange
accent), no formulas or text rendered in the image, 16:9.
```

**Tecnologia**
```
[EN] Modern technology in a real-world context (device, infrastructure,
laboratory, data center), photojournalistic style, natural lighting,
neutral-cool palette, avoid generic stock-photo office scenes, no visible
brand logos, no text, 16:9.
```

**Ambiente**
```
[EN] Natural environment or climate-related scene (landscape, ecosystem,
renewable energy infrastructure), photorealistic, natural daylight, green
and blue tones balanced with the site's cool teal palette, hopeful but not
overly staged, no text, no overlaid graphics, 16:9.
```

**Medicina / Salute**
```
[EN] Scientific/medical subject (biology, healthcare research, laboratory
context), clinical but warm lighting, precise focus on the subject, cool
color grading with teal accents, no visible text on packaging/screens/
signage, no identifiable patients, 16:9.
```

**Spazio (missioni/esplorazione)**
```
[EN] Space exploration hardware or mission context (spacecraft, rover,
launch, astronaut equipment) rendered photorealistically as if real mission
photography, dramatic lighting, cool tones, no text, no agency logos
unless factually accurate and necessary, 16:9.
```

---

## 13. Immagini da rifare

Elenco delle immagini esistenti che, in base all'audit (§15), non rispettano lo standard qui definito e dovranno essere **rifatte in futuro** (nessuna sostituzione viene eseguita in questa PR):

**Priorità alta — testo bruciato nell'immagine (viola §5 e §9):**
- `public/assets/img/categories/chatgpt-image-8-mag-2026-11-23-43-20260508092406-3a9787.png` (categoria Ambiente, testo "AMBIENTE" + sottotitolo nell'immagine)
- Le restanti 5 immagini in `public/assets/img/categories/` (da verificare singolarmente, stesso pattern di generazione)

**Priorità alta — duplicati esatti da consolidare (uno dei due file va rimosso in una futura PR di pulizia, non qui):**
- `ai-law-italy.png` / `ai-law-italy-20260507195414-189f98.png`
- `hero-ai-premium.png` / `hero-ai-premium-20260507195549-f08dba.png`
- `hero-ambiente.png` / `hero-ambiente-20260507195651-f3855e.png`
- `hero-spazio.jpg` / `hero-spazio-20260507195745-a10db4.jpg`
- `chatgpt-image-11-mag-2026-08-55-24-20260511065609-904efe.png` / `...-080428-f7b28c.png`
- `chatgpt-image-11-mag-2026-10-17-29-20260511081831-8d8cca.png` / `...-082741-376ce4.png`
- `chatgpt-image-10-mag-2026-10-06-59-20260510081401-UjUxOE.png` / `...-101802-6fc6e8.png` / `turing-hero-correct.png` (tre file identici)

**Priorità media — naming non descrittivo (da rinominare secondo §8 alla prossima sostituzione):**
- Tutti i file `chatgpt-image-*-mag-2026-*.png` in `public/assets/img/` e `public/assets/img/categories/`
- `public/assets/img/123-20260511082957-6f456c.png`

**Priorità media — estensione non coerente con il formato reale (da correggere in fase di rigenerazione):**
- `hero-spazio.jpg` / `hero-spazio-20260507195745-a10db4.jpg` (estensione `.jpg`, contenuto PNG)
- `public/assets/img/turing/ai-moderna.jpg`, `enigma.jpg`, `macchina-universale.jpg` (estensione `.jpg`, contenuto PNG)

**Priorità bassa — fuori standard di rapporto/risoluzione (da riallineare a 16:9/1200×675 in fase di rigenerazione):**
- Le 6 immagini categoria a 1536×1024 (oltre il tetto di 1200px definito per le categorie, §7)
- Le immagini cover/hero a 1536×1024 (rapporto 1.5:1, da riallineare a 16:9)

**Da verificare (non un'immagine da rifare, ma un riferimento da correggere):** `database/seeders/DatabaseSeeder.php` referenzia `hero-placeholder.jpg`, `placeholder-1.jpg`…`placeholder-5.jpg`, file che non esistono sul disco (esistono solo `hero-placeholder.svg` e `placeholder-1.svg`). Non è un'immagine da rifare ma un riferimento da correggere in una futura PR sul seeder — segnalato qui solo per completezza dell'audit, **nessuna modifica al seeder in questa PR**.

---

## 14. Roadmap di adozione

Ordine consigliato per applicare gradualmente lo standard, dal più strutturale al più superficiale — ogni fase è indipendente e non blocca le successive:

1. **Placeholder** — già conformi allo standard (palette, assenza di testo, rapporti corretti); punto di partenza concettuale per tutto il resto, nessuna azione richiesta.
2. **Categorie** — priorità più alta tra le immagini da rifare (testo bruciato, fuori tetto di risoluzione, naming non descrittivo); impatto visivo alto perché le categorie compaiono in più punti del sito (barra categorie, card, pagina categoria).
3. **Hero** — banner di sezione (a partire da Turing, unico hero attualmente ben distinto), per validare lo stile fotografico/cinematografico su un formato ampio prima di estenderlo a tutte le cover.
4. **Articoli (cover)** — il gruppo più numeroso e visibile pubblicamente; da affrontare per nuovi articoli prima, poi gradualmente sugli articoli esistenti (a partire dai duplicati e dai file con naming non descrittivo elencati in §13).
5. **Turing** — consolidamento del sotto-stile storico/vintage una volta validato lo stile generale, correzione delle estensioni file non coerenti.
6. **Open Graph** — ultimo passaggio, perché dipende da `cover_image` già coerente: una volta allineate le cover articolo, l'OG eredita automaticamente la stessa immagine (nessuna immagine OG dedicata separata è necessaria, mantenendo il meccanismo di fallback già implementato).

---

## 15. Appendice — Audit dello stato attuale

Audit di sola lettura, eseguito su `public/assets/img/`, `public/assets/icons/`, `public/favicon.ico`, README.md e DOCUMENTAZIONE.md. Nessun file è stato modificato.

### Logo
`public/assets/icons/logo.svg` (160×40, wordmark "Quark" + punto teal `#0d9488`) esiste ma **non è referenziato in nessuna vista**: il logo effettivamente mostrato nell'header è un wordmark testuale/CSS (`Quark<span class="dot">.</span>` in `components/header.blade.php`), non l'SVG. L'asset è orfano.

### Favicon
`public/assets/icons/favicon.svg` (64×64, sfondo `#111827`, "Q" teal, punto arancione) esiste ma **non è referenziato da alcun tag `<link rel="icon">`** in `layouts/partials/head.blade.php` o altrove. `public/favicon.ico`, servito per convenzione di default dal browser, è **vuoto (0 byte)** — la favicon effettivamente servita oggi è quindi rotta/assente. Nessuna modifica applicata in questa PR (fuori scope: richiederebbe toccare Blade).

### Placeholder
`hero-placeholder.svg` (1200×630, 1.91:1) e `placeholder-1.svg` (1200×750, 16:10) sono coerenti, ben progettati, senza testo (`aria-hidden="true"`), palette allineata al brand. Nessuna incoerenza rilevata — sono il riferimento stilistico per §3–§4.

### Categorie (`public/assets/img/categories/`, 6 file)
Tutte PNG, 1536×1024 (1.5:1), stile fotorealistico/cinematografico. **Tutte contengono testo bruciato nell'immagine** (titolo categoria e sottotitolo, es. "AMBIENTE — Notizie, approfondimenti e soluzioni per un futuro sostenibile"). Risoluzione oltre il tetto di 1200px applicato da `Admin\CategoryController` — probabilmente caricate prima dell'introduzione di `ImageService` o tramite un canale diverso dall'upload standard. Naming non descrittivo (`chatgpt-image-8-mag-2026-...`).

### Turing (`public/assets/img/turing/`, 4 file)
1672×941 (~1.78:1), stile fotorealistico con grana storica/vintage coerente (illuminazione calda, oggetti d'epoca — Enigma, macchine da calcolo). Nessun testo bruciato. **Incoerenza**: `ai-moderna.jpg`, `enigma.jpg`, `macchina-universale.jpg` hanno estensione `.jpg` ma contenuto binario PNG (verificato via `getimagesize`/MIME reale).

### Cover / hero (radice di `public/assets/img/`, ~30 file)
Stile fotorealistico/cinematografico coerente con categorie e Turing (nebulose, ritratti concettuali IA, scene tecnologiche). Nessun testo bruciato rilevato in questo gruppo. Incoerenze:
- **7 gruppi di duplicati esatti** (stesso contenuto binario, MD5 identico, nomi file diversi) — elencati in §13.
- **Rapporti non uniformi**: 1.5:1 (maggioranza), ~1.78:1, nessuna immagine realmente a 16:9 o 1.91:1 puri.
- **Naming misto**: alcuni file hanno nomi descrittivi (`hero-energia.png`, `space-01.png`), altri sono export grezzi da strumento AI (`chatgpt-image-11-mag-2026-08-55-24-...png`) o completamente non descrittivi (`123-20260511082957-6f456c.png`).
- **Estensione non coerente col formato**: `hero-spazio.jpg`/`hero-spazio-...jpg` hanno contenuto PNG.
- Nessuno di questi file è referenziato staticamente da `config/`, dai seeder o dalle viste: sono legati dinamicamente a righe reali di `articles`/`categories` tramite i campi `cover_image`/`image` nel database locale (non tracciabile da codice sorgente).

### Open Graph
Non esiste un asset OG dedicato: `articolo.blade.php` riusa `cover_image` (fallback `hero-placeholder.svg`) e dichiara staticamente `og:image:width="1200"` / `og:image:height="630"` **indipendentemente dalle dimensioni reali del file caricato**, che quasi mai sono realmente 1.91:1 (vedi punto precedente). Le pagine non-articolo (home, categoria, pagine statiche) non dichiarano alcun `og:image` (solo `og:site_name`/`type`/`title`/`description`/`url` nel partial condiviso).

### README.md / DOCUMENTAZIONE.md
Entrambi già descrivono correttamente `ImageService`, i due placeholder SVG (con rapporti esatti) e i metadati `cover_*` — nessuna incoerenza rispetto al codice reale rilevata in questi due file. Entrambi elencano già **"Sostituzione delle immagini placeholder con foto originali"** nella sezione Roadmap/Futuro, confermando che questo documento si inserisce in un lavoro già pianificato dal progetto, non in un'iniziativa estemporanea.

### Seeder
`database/seeders/DatabaseSeeder.php` assegna `cover_image` uguale a `hero-placeholder.jpg`, `placeholder-1.jpg`…`placeholder-5.jpg` ad articoli di seed: nessuno di questi file `.jpg` esiste sul disco (esistono solo le due varianti `.svg` già citate). Su un'installazione seedata da zero, questi articoli mostrerebbero un'immagine rotta. Segnalato per completezza; **nessuna modifica al seeder o a PHP in questa PR** (fuori dai vincoli del task).
