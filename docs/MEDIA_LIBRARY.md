# Quark Blog – Media Library

Documento di riferimento per l'organizzazione degli asset multimediali di Quark Blog, in particolare per gli
Special Projects futuri (Ada Lovelace, Tesla, Curie, ecc. — si veda `docs/PROJECT_BOOK.md`, sezione 5).

Questo documento descrive **una struttura di destinazione** (target) e le sue convenzioni. Non comporta,
di per sé, alcuno spostamento, rinomina o eliminazione di file: la popolazione della libreria è pianificata
nella roadmap in fondo a questo documento ed è esplicitamente fuori dallo scope della Pull Request che introduce
questo documento (si veda Decision #005 in `docs/PROJECT_BOOK.md`).

## 1. Stato attuale — analisi

`public/assets/img/` contiene oggi 64 file per circa 82 MB, organizzati quasi interamente in un'unica cartella
piatta. Le uniche eccezioni sono `categories/` (6 thumbnail di categoria, ben nominate e coerenti — un buon
precedente da cui partire) e `turing/` (8 file legati allo Special Project Alan Turing, organizzati in modo
incoerente rispetto al resto degli asset Turing, che restano invece alla radice).

### 1.1 Criticità individuate

Le criticità seguenti sono state osservate direttamente sul contenuto attuale della cartella, non ipotizzate:

1. **Nessuna organizzazione per categoria o per tipo di utilizzo.** Quasi tutti gli asset (hero, cover, sfondi,
   pannelli editoriali, immagini generiche) convivono nella stessa cartella piatta, senza alcuna sotto-struttura
   che ne comunichi il soggetto o il ruolo.
2. **Due convenzioni incompatibili per lo stesso Special Project.** Alcuni asset Turing vivono alla radice con
   prefisso (`turing-hero.webp`, `turing-enigma-background.webp`, `turing-legacy-panel.webp`, ...), altri in
   `turing/` senza prefisso (`turing/enigma.webp`, `turing/universal-machine.webp`, ...). Non esiste una regola
   che stabilisca quando un progetto riceve una propria cartella e quando no.
3. **Naming bilingue incoerente all'interno della stessa cartella.** `turing/macchina-universale.jpg` (italiano)
   convive con `turing/universal-machine.webp` (inglese) per lo stesso soggetto; `turing/test-turing.png` e
   `turing/turing-test.webp` invertono persino l'ordine delle stesse due parole per lo stesso soggetto.
4. **Originali non ottimizzati mai rimossi dopo la conversione in WebP.** Nella sola cartella `turing/`
   convivono 4 coppie file-pesante/file-ottimizzato per lo stesso soggetto (`enigma.jpg` 2,1 MB accanto a
   `enigma.webp` 88 KB; stesso schema per `ai-moderna`/`modern-ai`, `macchina-universale`/`universal-machine`,
   `test-turing`/`turing-test`) — circa 8 MB di file morti che nessuna pagina referenzia più.
5. **Export grezzi di strumenti di generazione mai rinominati.** Almeno 14 file hanno nomi del tipo
   `chatgpt-image-11-mag-2026-08-55-24-20260511065609-904efe.png` (data, ora e hash casuale al posto di un nome
   descrittivo); un file si chiama semplicemente `123-20260511082957-6f456c.png`. Diversi di questi export sono
   quasi certamente doppioni mai ripuliti di file già rinominati correttamente (es. `hero-ai-premium.png` esiste
   sia in forma pulita sia come `hero-ai-premium-20260507195549-f08dba.png`; lo stesso schema si ripete per
   `hero-ambiente`, `hero-spazio` e `ai-law-italy`).
6. **Il nome non comunica il tipo di utilizzo, salvo poche eccezioni.** `cover-*.webp` e `hero-*.png/.jpg`
   codificano il proprio ruolo nel nome; `space-01.png`, `tech-society-01.png` no — un numero progressivo non
   dice se un file è pensato per un hero, una card, una gallery o altro.
7. **Naming legato in modo permanente a un singolo Special Project anche per soggetti generici.**
   `turing-ai-background.webp`, `turing-ai-panel.webp` rappresentano concetti generici (intelligenza artificiale,
   pensiero computazionale) che potrebbero servire ad altri Special Projects futuri (es. una pagina dedicata a
   Turing e all'IA non è concettualmente l'unica a poter usare quel tipo di immagine) — ma il nome del file lega
   permanentemente l'asset a "Turing", scoraggiandone il riuso anche quando il contenuto lo permetterebbe.
8. **Riferimenti a filename inesistenti sul disco.** `resources/views/admin/articles.blade.php` usa come
   fallback `placeholder-1.jpg`, che non esiste (esiste solo `placeholder-1.svg`); il partial legacy
   `resources/views/turing.blade.php` referenzia `turing/test-turing.jpg`, che non esiste (esiste solo
   `turing/test-turing.png`). Nessun meccanismo verifica oggi che un filename referenziato nel codice esista
   davvero — questi due riferimenti rotti sono passati inosservati.
9. **Nessuna metadatazione per categoria, tipo di utilizzo, credito o licenza.** La tabella `media` esistente
   (`app/Models/Media.php`, usata dall'uploader in `admin/media.blade.php`) traccia solo
   `filename`/`disk_name`/`mime_type`/`size`/`alt_text`: non esiste un modo strutturato per rispondere a "quali
   immagini esistono già che potrei riusare per un nuovo hero" o "a chi va attribuito questo asset".
10. **Una convenzione di credito/licenza esiste già, ma è isolata.** `docs/TURING_EDITORIAL_ASSETS.md` definisce
    già una tabella per-asset (Use / Origin / Production method / License / Credit) e uno standard tecnico
    (WebP, 1200×675 px, 16:9, RGB, nessun testo/logo/watermark incorporato) — ma solo per gli asset Turing, in un
    documento specifico di quel progetto invece che come convenzione condivisa da ogni Special Project.

Queste criticità motivano la Decision #005 (`docs/PROJECT_BOOK.md`): non una nuova collezione di immagini, ma una
struttura e una disciplina di naming condivise, così che ogni futuro Special Project non ripeta gli stessi errori.

## 2. Struttura della libreria (destinazione proposta)

```text
public/assets/img/
├── library/                     ← Media Library riutilizzabile (oggetto di questo documento)
│   ├── people/                  ← figure umane generiche, non identificate (silhouette, ritratti generici)
│   ├── technology/              ← computer, circuiti, macchine, hardware, codice
│   ├── history/                 ← archivi, documenti d'epoca, oggetti storici, ambientazioni datate
│   ├── science/                 ← laboratori, esperimenti, strumentazione scientifica
│   ├── abstract/                ← texture, pattern, concetti visivi non letterali (reti, dati, luce)
│   └── ui/                      ← elementi di interfaccia, sfondi neutri, texture di supporto al layout
├── placeholders/                ← segnaposto generici (nessuna categoria: sono "vuoti" per definizione)
├── categories/                  ← invariata: identità delle categorie del blog (già coerente oggi)
├── special-projects/
│   └── <slug>/                  ← es. turing/ — asset unici e non riusabili di UN progetto (ritratti reali,
│                                   materiale d'archivio specifico di quella figura)
└── articles/
    └── covers/                  ← invariata nella funzione: cover_image per-articolo, contenuto non riusabile
```

Note sulla struttura:

- **`library/` è la sola area "riusabile per definizione".** Un file che entra in `library/` non deve contenere
  riferimenti impliciti a un soggetto, una persona o un progetto specifico: deve poter illustrare un concetto
  generico (es. "intelligenza artificiale", "archivio storico", "laboratorio") indipendentemente da quale
  Special Project lo utilizzerà.
- **`special-projects/<slug>/` è l'opposto: contenuto intenzionalmente non generico.** Un ritratto reale (es.
  `alan-turing-portrait.png`) o una fotografia d'archivio specifica di una figura storica non è "riusabile" per
  un altro progetto — resta correttamente dedicato, ma organizzato sotto il proprio progetto invece che sparso
  alla radice.
- **`placeholders/` è separata da `library/`** perché un segnaposto non rappresenta un soggetto reale: non ha
  senso classificarlo per categoria.
- **`categories/` e `articles/covers/` restano concettualmente come sono oggi** — non fanno parte della Media
  Library riusabile in senso stretto (sono identità di categoria o contenuto editoriale specifico di un
  articolo), ma sono incluse nello schema per dare una mappa completa della cartella `assets/img/`.
- La struttura resta sotto `public/assets/img/`, quindi **nessuna modifica è necessaria al resolver di media
  già esistente** in `<x-special.timeline>`/`<x-special.chapter-opener>` e nei controller: quel resolver
  normalizza già qualsiasi percorso relativo (incluse le sottocartelle) verso `asset('assets/img/...')`.

## 3. Categorie (asse "soggetto")

| Categoria | Cosa contiene | Cosa NON contiene |
|---|---|---|
| `people` | Figure umane generiche, non identificabili (silhouette, ritratti generici, mani, gesti) | Ritratti di persone reali e nominate → `special-projects/<slug>/` |
| `technology` | Computer, circuiti, macchine, hardware, righe di codice, dispositivi | Interfacce grafiche del sito → `ui` |
| `history` | Archivi, documenti d'epoca, oggetti storici, ambientazioni datate | Materiale d'archivio specifico di una figura → `special-projects/<slug>/` |
| `science` | Laboratori, esperimenti, strumentazione, natura osservata scientificamente | Concetti astratti senza soggetto fisico → `abstract` |
| `abstract` | Texture, pattern, concetti visivi non letterali (reti, dati, luce, movimento) | Fotografie di soggetti reali → una delle categorie sopra |
| `ui` | Sfondi neutri, texture di supporto al layout, elementi puramente decorativi del sito | Contenuto editoriale/narrativo → una delle categorie sopra |

Le categorie sono **assi del soggetto**, ortogonali al tipo di utilizzo (sezione 4): una stessa immagine di
categoria `technology` può essere usata come `hero` in un progetto e come `chapter` in un altro.

`placeholders` **non è una categoria** di questo asse: un segnaposto non rappresenta un soggetto reale, quindi
non ha senso classificarlo per soggetto. Vive infatti nella propria cartella dedicata `placeholders/`, sorella
di `library/` e non sua sottocartella (sezione 2) — coerente con lo schema di naming (sezione 5.1), che per
`placeholders/` omette del tutto il segmento `{categoria}`.

## 4. Tipi di utilizzo (asse "ruolo sulla pagina")

| Tipo | Ruolo | Esempio nel codice attuale |
|---|---|---|
| `hero` | Immagine di apertura pagina, piena larghezza | `heroBackgroundImage`, `hero-*.png` |
| `cover` | Copertina di un contenuto (articolo, capitolo) | `cover_image` degli articoli, `cover-*.webp` |
| `chapter` | Apertura di un capitolo narrativo (Decision #003), immagine **contenuta**, mai a tutta sezione | `<x-special.chapter-opener :image>` |
| `editorial` | Pannello immagine affiancato al testo in un blocco editoriale | `sectionImageFallbacks()` → "panel" |
| `background` | Sfondo di sezione, sempre bordato in altezza (Decision #001), mai esteso al contenuto | `sectionBackgroundFallbacks()`, `*-background.webp` |
| `gallery` | Immagine in una sequenza/galleria di più immagini equivalenti | non ancora presente in codice — riservato per uso futuro |
| `thumbnail` | Anteprima piccola (card, elenco, correlati) | `categories/categoria-*.webp` |
| `portrait` | Ritratto di una persona reale, isolato dal contesto narrativo | `heroPortraitImage`, `.turing-portrait-card__photo` |

Il tipo di utilizzo determina **le proporzioni e il trattamento tecnico attesi** (si veda sezione 6), non la
cartella. All'interno di `library/`, la cartella è determinata solo dalla categoria (sezione 3); `placeholders/`
e `special-projects/<slug>/` restano invece posizioni sorelle a sé stanti, fuori da questo asse (sezione 2).

## 5. Convenzione di naming

### 5.1 Schema

```text
{categoria}/{soggetto-descrittivo}--{tipo-utilizzo}[--variante].{estensione}
```

- **`{categoria}`**: solo per gli asset dentro `library/` — corrisponde al nome della sottocartella (sezione 3);
  omesso per `placeholders/`, `special-projects/<slug>/`, `articles/covers/`, dove la cartella già lo comunica.
- **`{soggetto-descrittivo}`**: 2–4 parole in **kebab-case**, **sempre in inglese** (elimina l'incoerenza
  bilingue osservata in `turing/`), che descrivono cosa si vede, non a chi appartiene o dove viene usato
  (es. `circuit-board-macro`, non `turing-tech-image`).
- **`--{tipo-utilizzo}`**: uno dei valori della sezione 4 (`hero`, `cover`, `chapter`, `editorial`, `background`,
  `gallery`, `thumbnail`, `portrait`), separato con doppio trattino per non confondersi con i trattini interni al
  soggetto descrittivo.
- **`[--variante]`**: opzionale, solo quando più asset condividono soggetto e tipo (es. `--01`, `--02` per una
  gallery, oppure `--dark`/`--light` per varianti tonali).
- **`{estensione}`**: WebP di default (si veda sezione 6); SVG solo per `placeholders/` e `ui/` quando il
  contenuto è vettoriale.

### 5.2 Esempi

| File attuale (criticità) | Nome proposto nella nuova struttura |
|---|---|
| `turing-ai-background.webp` | `library/technology/neural-network-glow--background.webp` |
| `turing/enigma.webp` (+ `enigma.jpg` da rimuovere) | `library/history/cipher-machine-archive--editorial.webp` |
| `space-01.png` | `library/abstract/deep-space-nebula--hero.webp` |
| `tech-society-01.png` | `library/technology/connected-city-network--editorial.webp` |
| `hero-placeholder.svg` | `placeholders/generic-image--hero.svg` |
| `alan-turing-portrait.png` | `special-projects/turing/alan-turing--portrait.webp` *(resta dedicato: è un ritratto reale; convertito a WebP come da standard tecnico, sezione 6)* |

Il principio guida: **se una descrizione del soggetto senza il nome del progetto ha ancora senso, l'asset
appartiene a `library/`; se lo perde, appartiene a `special-projects/<slug>/`.**

## 6. Standard tecnico

Si applica a `library/`, `placeholders/` e `special-projects/<slug>/` — le cartelle della Media Library vera e
propria (sezione 2) — non solo a `library/` in senso stretto, estendendo lo standard già in uso per gli asset
Turing (`docs/TURING_EDITORIAL_ASSETS.md`). Non si applica retroattivamente a `categories/` e
`articles/covers/`, che restano fuori dallo scope di questo documento (sezione 2: "restano concettualmente come
sono oggi"):

- **Formato**: WebP per fotografie/immagini raster (qualità 80–85); SVG per elementi vettoriali/placeholder;
  nessun PNG/JPG lasciato sul disco dopo la conversione — l'originale pesante non deve convivere con la versione
  ottimizzata (criticità #4, sezione 1.1).
- **Proporzioni per tipo di utilizzo**: `hero`/`background` 16:9 (es. 1600×900 o 1200×675); `cover` 4:3 o 16:9 a
  seconda del layout ospitante; `chapter` proporzioni contenute, mai a piena sezione (coerente con Decision
  #003); `thumbnail` 1:1 o 4:3; `editorial`/`gallery` 4:3.
- **Nessun testo, logo o watermark incorporato nell'immagine** — coerente con lo standard Turing esistente,
  così che la stessa immagine resti utilizzabile in contesti/lingue diversi senza dover essere rigenerata.
- **RGB, senza profilo colore esotico**, per coerenza di resa tra browser e dispositivi.

## 7. Riuso: linee guida

1. **Prima di generare o caricare una nuova immagine, cercare in `library/<categoria>/`** un asset già esistente
   che copra il soggetto richiesto. Un nuovo Special Project su Ada Lovelace, ad esempio, può riusare
   direttamente asset `library/technology/` o `library/history/` già presenti per Turing, senza commissionarne
   di nuovi.
2. **Un asset entra in `library/` solo se è stato scritto per essere generico.** Se un'immagine è stata prodotta
   pensando esplicitamente a un soggetto o progetto specifico, va in `special-projects/<slug>/`, anche se
   visivamente potrebbe sembrare riusabile — il nome e i metadati di credito (sezione 8) devono riflettere
   l'intento originale, non solo l'aspetto finale.
3. **Un asset può migrare da `special-projects/<slug>/` a `library/`** in un secondo momento, se ci si accorge
   che è di fatto abbastanza generico da servire altri progetti — ma questa è una decisione esplicita (rinomina
   + spostamento + aggiornamento riferimenti), non qualcosa che avviene per caso lasciando il file dov'è.
4. **Non duplicare lo stesso soggetto in categorie diverse.** Se un'immagine di "rete neurale astratta" esiste
   già in `abstract/`, non generarne una nuova quasi identica per metterla anche in `technology/`: si sceglie la
   categoria più specifica e ci si tiene quella.

## 8. Crediti e licenze

Ogni asset in `library/` e in `special-projects/<slug>/` deve avere una riga corrispondente in un registro dei
crediti, sul modello già avviato da `docs/TURING_EDITORIAL_ASSETS.md` (Use / Origin / Production method /
License / Credit). In una fase successiva di popolazione della libreria (si veda roadmap, sezione 9), questo
registro verrà generalizzato in `docs/MEDIA_CREDITS.md`, con una riga per asset e le stesse colonne già in uso
per Turing:

| Colonna | Significato |
|---|---|
| File | Percorso relativo a `public/assets/img/` |
| Categoria / Tipo | Categoria (sezione 3) e tipo di utilizzo (sezione 4) |
| Origin | Provenienza (es. "Original Quark editorial asset") |
| Production method | Come è stato prodotto (es. "Artificially generated digital image") |
| License | Es. "Quark editorial use" per asset originali; licenza esplicita per materiale di terze parti |
| Credit | A chi va attribuito (es. "Quark" per asset originali; nome dell'autore/fonte per materiale esterno) |

Nessun asset di terze parti (foto stock, materiale non originale) deve essere aggiunto alla libreria senza una
licenza compatibile con l'uso editoriale del sito, esplicitamente registrata in questa tabella prima dell'uso.

## 9. Roadmap di popolazione (fuori scope di questa Pull Request)

Questo documento definisce solo la struttura e le convenzioni. La popolazione effettiva procederà in Pull
Request separate, nello spirito dei "piccoli passi incrementali" già definito in `docs/PROJECT_BOOK.md`
(sezione 3):

1. **Approvazione della struttura e delle convenzioni** — questa Pull Request (Decision #005).
2. **Creazione di `docs/MEDIA_CREDITS.md`** come registro generale, a partire dalle righe già presenti in
   `docs/TURING_EDITORIAL_ASSETS.md`.
3. **Migrazione incrementale degli asset realmente riusabili** in `library/<categoria>/` con i nuovi nomi,
   categoria per categoria, aggiornando i riferimenti nel codice che li usa (Blade/controller) contestualmente
   a ogni spostamento — mai come rinomina "silenziosa" che rompe un riferimento esistente.
4. **Pulizia degli originali non ottimizzati** rimasti dopo la conversione WebP (criticità #4), una volta che
   la versione migrata/ottimizzata è confermata in uso e non referenziata altrove con il vecchio nome.
5. **Correzione dei riferimenti rotti individuati** (`placeholder-1.jpg`, `turing/test-turing.jpg`, sezione
   1.1, criticità #8) come parte della migrazione della cartella che li contiene.
6. **Estensione della tabella `media`**: una nuova migration che aggiunge le colonne `category`, `usage_type`,
   `credit` e `license` allo schema, insieme ai corrispondenti aggiornamenti del model (`app/Models/Media.php`)
   e dell'uploader amministrativo (`admin/media.blade.php`), così da poter applicare la stessa tassonomia anche
   ai caricamenti manuali futuri.
7. **Strumento di verifica automatica (facoltativo)** che segnali riferimenti a filename inesistenti nel
   codice (avrebbe intercettato la criticità #8 prima che arrivasse in produzione).

Nessuno di questi passi è incluso nella Pull Request che introduce questo documento.
