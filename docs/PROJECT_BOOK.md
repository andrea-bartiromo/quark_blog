# Quark Blog – Project Book

## Introduzione
Il presente documento rappresenta il punto di riferimento per lo sviluppo di Quark Blog. Non nasce come una semplice raccolta di appunti tecnici, ma come un vero e proprio Project Book, destinato a documentare la visione, le decisioni progettuali e l'evoluzione della piattaforma nel tempo.

Ogni scelta architetturale, grafica o funzionale di una certa importanza verrà registrata all'interno di questo documento, con l'obiettivo di costruire una memoria storica del progetto e mantenere una direzione chiara durante tutte le fasi di sviluppo.

## 1. Visione del progetto
Quark Blog nasce con l'ambizione di diventare una piattaforma editoriale moderna dedicata ai temi della tecnologia, dell'informatica, della scienza e dell'innovazione.

L'obiettivo non è semplicemente pubblicare articoli, ma creare un'esperienza di lettura capace di coinvolgere il visitatore attraverso contenuti approfonditi, una struttura narrativa curata e un'interfaccia elegante e accessibile.

Ogni pagina dovrà contribuire a costruire un'identità riconoscibile, mantenendo un equilibrio tra qualità editoriale, semplicità di navigazione e cura del dettaglio.

## 2. Obiettivi del progetto
Lo sviluppo di Quark Blog segue alcuni obiettivi fondamentali che guidano ogni decisione tecnica e progettuale.

Il primo è la realizzazione di una piattaforma moderna, veloce e accessibile, costruita secondo le buone pratiche dello sviluppo web contemporaneo.

Il secondo consiste nel garantire un elevato livello qualitativo sia dal punto di vista tecnico che da quello editoriale, evitando soluzioni improvvisate o difficili da mantenere nel tempo.

Infine, ogni componente dovrà essere progettato pensando alla riusabilità, così da poter essere impiegato anche nelle future evoluzioni del progetto senza dover essere riscritto.

## 3. Principi progettuali
Nel corso dello sviluppo sono stati definiti alcuni principi che rappresentano la filosofia del progetto.

La priorità viene sempre data alle soluzioni strutturali rispetto ai semplici workaround. Quando emerge un problema, l'obiettivo è comprenderne la causa e risolverlo in modo definitivo, evitando scorciatoie che potrebbero generare ulteriori criticità in futuro.

Ogni funzionalità significativa viene sviluppata attraverso una Pull Request dedicata. Questo approccio rende il lavoro più ordinato, facilita le revisioni e permette di ricostruire facilmente la storia del progetto.

Prima di ogni merge vengono sempre effettuati controlli, test e verifiche di compatibilità, con particolare attenzione al comportamento dell'interfaccia su desktop, tablet e dispositivi mobili.

Infine, tutte le decisioni che influenzano l'architettura o la direzione del progetto vengono documentate all'interno di questo Project Book.

## 4. Decision Log

### Decision #001 – Nuova architettura della Timeline
Durante lo sviluppo della pagina dedicata ad Alan Turing è emersa una criticità importante relativa alla Timeline.

Inizialmente l'intera sezione utilizzava un'unica immagine di sfondo estesa per tutta la sua altezza. Poiché la Timeline supera i duemila pixel su desktop e i tremila pixel su mobile, il browser era costretto ad ingrandire e ritagliare eccessivamente l'immagine per adattarla alla sezione.

Il risultato era visivamente poco soddisfacente e variava sensibilmente da un dispositivo all'altro.

Tra le possibili soluzioni è stato preso in considerazione l'utilizzo di `background-attachment: fixed`. Sebbene questa tecnica producesse un miglioramento su desktop e su alcuni dispositivi Android, è stata successivamente scartata perché non garantisce un comportamento uniforme su Safari per iOS e rappresenta un workaround piuttosto che una soluzione realmente strutturale.

La scelta definitiva è stata quindi quella di ripensare l'architettura della sezione.

La Timeline è stata suddivisa in due parti distinte: una testata fotografica, con altezza controllata e indipendente dal contenuto, e una seconda area dedicata esclusivamente agli eventi cronologici.

Questa soluzione elimina il problema alla radice, migliora la compatibilità tra browser, semplifica la manutenzione del codice e costituisce la base architetturale per tutti i futuri Special Projects.

## 5. Architettura degli Special Projects
La pagina dedicata ad Alan Turing rappresenta il primo esempio di una nuova tipologia di contenuto chiamata Special Project.

L'idea è quella di costruire esperienze narrative complete dedicate a personalità che hanno avuto un impatto significativo nella storia della scienza, della tecnologia e dell'innovazione.

Ogni Special Project seguirà una struttura comune composta da una Hero introduttiva, una sezione di contestualizzazione, una Timeline degli eventi principali, approfondimenti dedicati, una sezione finale dedicata all'eredità lasciata dal protagonista e collegamenti ai contenuti correlati presenti nel blog.

Questa architettura non è stata pensata esclusivamente per Alan Turing, ma dovrà diventare uno standard riutilizzabile anche per futuri progetti dedicati, ad esempio, ad Ada Lovelace, Nikola Tesla, Marie Curie, Alan Kay, Tim Berners-Lee e molte altre figure.

## 6. Roadmap
Lo sviluppo procede attraverso piccoli passi incrementali, privilegiando modifiche limitate e facilmente verificabili.

Ad oggi sono stati completati la Hero, l'introduzione, la sezione Legacy e la nuova architettura della Timeline. La successiva Pull Request #35, "Timeline riutilizzabile e strutturata + design token", è stata implementata, mergiata in main e validata con successo. Con questa PR è stato introdotto il componente Blade riutilizzabile `<x-special.timeline>`, insieme al layer di design token `--sp-*` destinato agli Special Projects. La verifica end-to-end della Timeline si è conclusa con esito "Merge Ready".

La fase successiva sarà dedicata a una revisione approfondita dell'esperienza utente della pagina, con particolare attenzione alla composizione visiva della Timeline e al ritmo della lettura.

Successivamente verranno affrontati gli interventi funzionali già pianificati, tra cui la correzione degli errori presenti nelle pagine /turing/enigma e /turing/ia, il ripristino dei pulsanti "Approfondisci", la trasformazione della sezione "03 · Eredità" in un elemento interattivo e, infine, l'introduzione dei modal dedicati agli eventi della Timeline.

Solo al termine di queste attività verrà avviata la fase di rifinitura generale dell'interfaccia.

## 7. Design System
Con la crescita del progetto diventerà necessario definire un Design System condiviso.

L'obiettivo sarà quello di raccogliere in un unico documento tutte le linee guida relative a tipografia, palette cromatica, spaziature, componenti, pulsanti, card, Timeline e comportamenti responsive.

Questo consentirà di mantenere un linguaggio grafico coerente in tutto il progetto e renderà più semplice la realizzazione di nuovi Special Projects.

## 8. Documentazione
Parallelamente allo sviluppo del codice verrà costruita una documentazione tecnica organizzata all'interno della cartella `/docs`.

Il materiale comprenderà un manuale dedicato agli Special Projects, la descrizione dell'architettura del progetto, il Design System, la roadmap evolutiva e un registro cronologico delle decisioni progettuali.

L'obiettivo è fare in modo che ogni scelta importante rimanga documentata e facilmente consultabile nel tempo.

## 9. Visione a lungo termine
Quark Blog non vuole essere soltanto un blog.

L'ambizione è costruire una piattaforma editoriale capace di raccontare la tecnologia attraverso contenuti di qualità, esperienze narrative coinvolgenti e una forte attenzione all'esperienza utente.

Gli Special Projects rappresentano il cuore di questa visione. Nel tempo diventeranno il formato editoriale distintivo della piattaforma, condividendo componenti, principi progettuali e un linguaggio grafico comune.

Ogni nuova decisione contribuirà a consolidare un ecosistema coerente, facilmente estendibile e in grado di evolvere senza perdere la propria identità.

## 10. Linee guida di UX e composizione editoriale

### Premessa
Durante lo sviluppo del primo Special Project dedicato ad Alan Turing è emersa una riflessione importante che va oltre la semplice implementazione tecnica della pagina.

L'obiettivo di Quark Blog non è soltanto pubblicare contenuti, ma costruire esperienze editoriali capaci di accompagnare il lettore attraverso un percorso narrativo chiaro, coinvolgente e facilmente leggibile.

Per questo motivo ogni decisione relativa al layout, ai colori, alle immagini e alla disposizione delle sezioni dovrà essere valutata non solo dal punto di vista estetico, ma anche in funzione del ritmo della lettura e della comprensione della struttura narrativa.

Le linee guida riportate di seguito rappresentano quindi principi progettuali destinati a diventare uno standard condiviso per tutti i futuri Special Projects.

### 10.1 La struttura racconta, le immagini introducono
Una delle principali lezioni emerse durante lo sviluppo della Timeline riguarda il ruolo delle immagini all'interno delle pagine molto lunghe.

Inizialmente si è pensato che un maggiore utilizzo di immagini potesse rendere la Timeline più coinvolgente. L'analisi progettuale ha invece evidenziato che il problema non era la quantità di elementi grafici, bensì la mancanza di una struttura visiva sufficientemente forte.

Per questo motivo viene adottato il seguente principio: le immagini hanno il compito di introdurre un contenuto; è la struttura della pagina che deve sostenerne la lettura.

La fotografia rappresenta quindi l'apertura di un capitolo, mentre il ritmo della narrazione viene costruito attraverso la gerarchia visiva, le spaziature, il contrasto, la disposizione dei contenuti e la loro organizzazione logica.

### 10.2 Ogni capitolo deve essere riconoscibile
Ogni sezione significativa della pagina deve essere percepita come un nuovo capitolo dell'esperienza editoriale.

Il passaggio da un argomento al successivo non deve dipendere esclusivamente dal cambio del titolo, ma deve essere comunicato anche attraverso il linguaggio visivo.

Uno stacco di capitolo può essere ottenuto mediante una diversa composizione, una variazione tonale, un diverso ritmo verticale o altri elementi progettuali coerenti con il Design System.

L'obiettivo è fare in modo che il lettore percepisca naturalmente il cambio di argomento senza doverlo ricostruire mentalmente.

### 10.3 Evitare la ripetizione dello stesso linguaggio visivo
Una lunga sequenza di sezioni costruite con lo stesso schema grafico tende ad appiattire la percezione della pagina.

Quando ogni blocco utilizza lo stesso tipo di sfondo, lo stesso overlay, la stessa disposizione dei contenuti e la stessa gerarchia tipografica, il lettore perde progressivamente la percezione delle differenze narrative.

Per questo motivo il ritmo della pagina dovrà alternare differenti intensità visive, mantenendo una forte coerenza stilistica ma evitando la ripetizione sistematica dello stesso schema compositivo.

L'obiettivo non è cambiare stile ad ogni sezione, bensì creare una progressione visiva capace di accompagnare il lettore lungo tutto il percorso.

### 10.4 Le sezioni lunghe devono avere una struttura interna
Le sezioni che si sviluppano per diverse schermate di scorrimento non possono basarsi esclusivamente su uno sfondo uniforme.

All'aumentare della lunghezza della sezione aumenta infatti anche il rischio di monotonia visiva.

Per questo motivo ogni sezione estesa dovrà possedere una propria organizzazione interna, costruita attraverso elementi che guidino naturalmente la lettura.

Nel caso della Timeline questo principio si traduce nell'introduzione di una struttura narrativa composta da una spina dorsale cronologica, da una chiara gerarchia degli eventi e, in prospettiva, da capitoli temporali capaci di suddividere il racconto in fasi facilmente riconoscibili.

Il ritmo della pagina dovrà quindi essere generato dalla struttura stessa e non dalla semplice ripetizione di immagini o sfondi decorativi.

### 10.5 La fotografia ha un ruolo preciso
Le immagini fotografiche rappresentano uno degli elementi più forti del linguaggio visivo di Quark Blog.

Per preservarne l'efficacia, il loro utilizzo dovrà essere intenzionale.

Le fotografie principali verranno utilizzate come apertura di una sezione o di un capitolo, con proporzioni controllate e indipendenti dalla lunghezza del contenuto.

Non dovranno invece essere utilizzate come sfondo esteso di sezioni molto lunghe, poiché questa soluzione genera problemi di adattamento, compromette la leggibilità e riduce progressivamente l'impatto visivo della fotografia stessa.

### 10.6 La coerenza è più importante della spettacolarità
Ogni nuova soluzione progettuale dovrà essere valutata privilegiando la qualità dell'esperienza complessiva rispetto all'effetto scenografico della singola sezione.

Animazioni, immagini, effetti grafici e componenti interattivi dovranno essere introdotti soltanto quando contribuiscono realmente alla comprensione del contenuto o al miglioramento dell'esperienza utente.

L'obiettivo di Quark Blog è costruire un linguaggio editoriale riconoscibile, elegante e duraturo, capace di evolversi nel tempo senza dipendere da soluzioni spettacolari ma difficili da mantenere.

## 11. Aggiornamento successivo alla Pull Request #35

**Stato della PR #35.** La Pull Request #35, "Timeline riutilizzabile e strutturata + design token", è stata implementata, mergiata in main e validata con successo. La Decisione #001 resta invariata e continua a rappresentare il fondamento architetturale della Timeline.

### Decision #002 – Componente Timeline riutilizzabile e design token per gli Special Projects
A seguito della nuova architettura definita dalla Decisione #001, la Timeline è stata trasformata in un componente Blade riutilizzabile, identificato come `<x-special.timeline>`. La responsabilità del componente è fornire una struttura comune e mantenibile per le Timeline dei futuri Special Projects, senza vincolare i singoli progetti alla baseline visiva del progetto dedicato ad Alan Turing.

Contestualmente è stato introdotto un layer di design token con prefisso `--sp-*`. I token costituiscono l'interfaccia stilistica condivisa degli Special Projects e permettono di adattare colori, spaziature e altri valori di presentazione senza duplicare la struttura del componente o introdurre dipendenze rigide da una singola pagina.

La soluzione consolida i principi di riusabilità, separazione delle responsabilità e manutenzione evolutiva già definiti nel Project Book. La pagina /turing rimane una baseline visiva di riferimento, ma non rappresenta un requisito grafico obbligatorio per i futuri Special Projects.

**Validazione.** La verifica end-to-end della Timeline è stata completata con esito "Merge Ready". La validazione ha confermato il corretto comportamento strutturale, funzionale e responsive del componente e l'assenza di regressioni involontarie nelle sezioni adiacenti.

**Standard di test riutilizzabile.** Era in revisione la skill Devin `testing-special-project-timeline`, destinata a standardizzare i controlli pre-merge delle Pull Request che modificano la Timeline di uno Special Project. La skill dovrà essere indipendente dall'ambiente, utilizzare una route configurabile, distinguere i vincoli invarianti del Project Book dalla baseline visiva corrente e produrre un `test-report.md` con esiti tracciabili e un giudizio finale tra "Merge Ready", "Merge con piccoli fix" e "Da rivedere".

## 12. Aggiornamento successivo alla Pull Request #36

**Stato della PR #36.** La Pull Request della skill `testing-special-project-timeline` è stata completata, validata, mergiata nel branch main e costituisce lo standard di verifica per le modifiche alla Timeline degli Special Projects.

### Decision #003 – Capitoli temporali e Chapter Opener
La Timeline è stata evoluta in una struttura narrativa suddivisa in capitoli temporali (era/chapter): `Cover → (Chapter Opener → Events)`, ripetuto per ogni capitolo.

Ogni capitolo è introdotto da un nuovo componente Blade riutilizzabile, `<x-special.chapter-opener>` (`period`, `title`, `intro`, `image`, `alt`), che rappresenta uno stacco narrativo reale (10.2) tra un periodo e il successivo. La sua fotografia è un'immagine contenuta e proporzionata (10.5) — mai uno sfondo esteso a tutta sezione, a differenza della Cover di apertura definita dalla Decisione #001, che resta invariata. Il componente possiede una superficie di sfondo propria, cosi' il testo mantiene un contrasto adeguato indipendentemente dalla sezione che lo precede.

Gli eventi di ogni capitolo continuano a essere renderizzati esclusivamente tramite il componente esistente `<x-special.timeline>` (Decisione #002), senza duplicarne il markup: ogni capitolo passa al componente soltanto il proprio sottoinsieme di eventi. L'unica modifica al componente `<x-special.timeline>` è stata la scomposizione della singola condizione di rendering in due condizioni indipendenti (cover, lista eventi), così da poter invocare lo stesso componente sia "solo cover" (l'apertura generale della Timeline) sia "solo eventi" (ogni capitolo), senza introdurre alcun cambiamento nel comportamento già esistente.

Entrambi i componenti restano interamente basati sui dati (data-driven): nessun riferimento ad Alan Turing è presente nel componente o nel CSS condiviso (`public/css/special-project.css`, namespace `--sp-*`). I contenuti del progetto Turing (periodo, titolo, testo introduttivo e immagine di ciascun capitolo) sono definiti esclusivamente nel controller della pagina (`TuringPageController::defaultTimelineChapters()`), cosi' un futuro Special Project potra' definire i propri capitoli riusando gli stessi due componenti senza alcuna modifica al loro codice.

**Stato della Pull Request associata.** La Pull Request "feat(timeline): introduce narrative chapters and reusable chapter opener" (branch `claude/refactor-image-service-py7qtz`) implementa questa Decisione #003. È stata aperta per revisione e **non è stata mergiata**: resta in attesa di approvazione, come da processo descritto nella sezione 3.

**Validazione.** La skill `testing-special-project-timeline` è stata estesa con un nuovo controllo T7 (ordine Cover → Chapter Opener → Eventi, id univoci, nessun evento perso o duplicato dal raggruppamento in capitoli) e con criteri aggiuntivi nel controllo T1 (immagine del Chapter Opener contenuta e non a tutta sezione; contrasto del testo garantito indipendentemente dalla sezione precedente — un problema di contrasto di questo tipo è stato effettivamente individuato e corretto durante questa stessa validazione). La verifica end-to-end, riportata in `test-report.md`, si è conclusa con esito **"Merge Ready"** (controlli T1–T7 tutti PASS, `php artisan test` verde su 159 test, nessuna regressione rilevata su Hero, Legacy, blocchi editoriali e Cover della Timeline).

### Decision #004 – Componenti comuni degli Special Projects
Le sezioni Hero, Intro, Editorial, Legacy e Final saranno migrate progressivamente verso componenti Blade condivisi del namespace `<x-special.*>`, mantenendo la separazione tra struttura, contenuti e presentazione.

*(Nessuna attività su questa decisione in questa Pull Request: resta un elemento di roadmap futuro, come descritto di seguito.)*

### Roadmap aggiornata
- [x] Introduzione del modello dati era/chapter/events.
- [x] Realizzazione dei Chapter Opener.
- [x] Aggiornamento della skill di test (`testing-special-project-timeline`, nuovo controllo T7) e produzione del `test-report.md` di validazione.
- [ ] Timeline interattiva con card/modal in sovraimpressione per ogni evento — non affrontata in questa Pull Request per esplicita scelta di scope.
- [ ] Migrazione progressiva di Hero, Intro, Editorial, Legacy e Final verso componenti comuni `sp-*` (Decision #004) — non ancora avviata.
- [x] Test end-to-end — completati per l'ambito di questa Pull Request (Decisione #003).
- [ ] Rifinitura complessiva della pagina — resta un'attività futura.

## 13. Aggiornamento successivo alla Pull Request #38

### Decision #005 – Media Library per gli Special Projects
Gli Special Projects generano asset fotografici/grafici a ritmo crescente (Turing conta oggi 20+ immagini), ma
`public/assets/img/` è rimasta una cartella sostanzialmente piatta: nessuna organizzazione per soggetto o per
tipo di utilizzo, due convenzioni incompatibili per gli stessi asset Turing (alcuni alla radice con prefisso,
altri in `turing/` senza), naming bilingue incoerente, originali non ottimizzati mai rimossi dopo la conversione
in WebP, export grezzi di strumenti di generazione mai rinominati e almeno due riferimenti nel codice a filename
che non esistono più sul disco. Questi problemi sono documentati nel dettaglio, con evidenza diretta sul
contenuto della cartella, nel nuovo `docs/MEDIA_LIBRARY.md`.

La Decisione introduce una **Media Library riutilizzabile** con due assi ortogonali — categoria del soggetto
(`people`, `technology`, `history`, `science`, `abstract`, `ui`) e tipo di utilizzo (`hero`, `cover`, `chapter`,
`editorial`, `background`, `gallery`, `thumbnail`, `portrait`) — una convenzione di naming neutra e in inglese
che non lega più permanentemente un asset generico a un singolo Special Project, e una distinzione esplicita tra
`library/` (asset riusabili per definizione), `placeholders/` (segnaposto generici, cartella propria perché non
rappresentano un soggetto reale) e `special-projects/<slug>/` (asset intenzionalmente unici, come i ritratti
reali). Generalizza inoltre lo standard tecnico e la convenzione di crediti/licenza già
avviata solo per Turing in `docs/TURING_EDITORIAL_ASSETS.md`, rendendola la base condivisa per ogni Special
Project futuro — coerente con i principi di riusabilità già definiti nella sezione 2 di questo documento.

**Esplicitamente fuori scope di questa Pull Request** (rimandato alla roadmap di popolazione in
`docs/MEDIA_LIBRARY.md`, sezione 9): nessuna immagine viene scaricata, eliminata o rinominata; nessun file
viene spostato nella nuova struttura; nessun componente Blade, controller o foglio di stile viene modificato;
la tabella `media` (`app/Models/Media.php`) non viene estesa con le nuove colonne di categoria/tipo/credito.
Questa Pull Request è esclusivamente architetturale e documentale: definisce la struttura di destinazione e le
convenzioni, senza toccare alcun asset o componente esistente — a garanzia che non introduca alcuna regressione.

**Stato della Pull Request associata.** La Pull Request "docs(media): introduce reusable Media Library
foundation" implementa questa Decisione #005. È stata aperta per revisione e non è stata mergiata: resta in
attesa di approvazione, come da processo descritto nella sezione 3.

## 14. Aggiornamento successivo alla Pull Request #39

### Decision #006 – Feature Cards riutilizzabili
Un'analisi della sezione "01 · Bletchley Park / 02 · Macchine intelligenti / 03 · Eredità" (in coda all'Intro
di `/turing`) ha rilevato diversi problemi concreti, verificati empiricamente e non solo ipotizzati:

- **Nessuno stile proprio.** `.turing-route-card` non aveva alcuna regola CSS in tutto il progetto: le tre
  "card" erano testo semplice (span/h3/p con i soli stili di default del browser) sovrapposto a una fotografia
  sfocata, con contrasto affidato al caso e non garantito.
- **Una card non interattiva ma indistinguibile.** La terza card ("03 · Eredità") era un `<div>` privo di link:
  non raggiungibile da tastiera (verificato via Tab), pur essendo visivamente identica alle altre due, che sono
  invece link veri.
- **Nessuna semantica di gruppo.** Il contenitore era un `<div>` generico, non un `<ul>`, senza `aria-label` che
  ne comunicasse lo scopo (un gruppo di link di approfondimento correlati).
- **Markup duplicato.** Lo stesso scheletro (span/h3/p dentro `turing-route-card`) era ripetuto quasi
  identico fra un ciclo sui dati CMS e un fallback hard-coded specifico di Turing — lo stesso pattern già
  risolto per la Timeline dalla Decisione #002.
- Il modello dati sottostante (`label`, `title`, `text`, `url`, `image`, `style`) era già generico e già
  alimentabile da CMS: solo il markup e il fallback la legavano a Turing.

**Decisione.** La sezione diventa il componente riutilizzabile `<x-special.feature-cards>`
(`:cards`, `label`, `id`), che renderizza un `<ul aria-label>` di card, ciascuna un `<a>` (se dotata di URL
valido) o un `<div aria-disabled="true">` (altrimenti) — mai visivamente identiche: la card non interattiva
riceve un trattamento distinto (bordo tratteggiato, nessun sollevamento al passaggio del mouse) oltre alla
corretta esclusione dal tab order. Lo stile (`public/css/special-project.css`, namespace `.sp-feature-card*`)
riusa deliberatamente la stessa superficie/ombra/hover/focus-visible già validata da `.sp-timeline__card`
(Decisione #002): nessun nuovo linguaggio visivo viene introdotto, solo applicato a una sezione che non ne
aveva mai ricevuto uno.

**Nota su una scelta esplicita.** Correggere in modo affidabile il contrasto e lo stato di `:focus-visible`
richiede necessariamente di dare alle card uno stile visibile per la prima volta — la sezione passa da testo
piatto su foto a card bordate con superficie chiara, coerenti con `.sp-timeline__card`/`.sp-chapter` già
presenti nella stessa pagina. Data la tensione con il vincolo "non modificare il design generale della pagina",
questa scelta è stata sottoposta esplicitamente all'utente prima dell'implementazione, che ha confermato di
procedere con lo stile completo. Il resto della pagina (Hero, Editorial, Legacy, Timeline, Chapter Opener,
Final) non è stato toccato.

Coerentemente con il pattern già stabilito da `contentItemsOrFallback` per `editorial_blocks` e `timeline`, il
fallback di Turing (i tre percorsi attuali) è stato spostato da markup hard-coded nel Blade a dati in
`TuringPageController::defaultRouteCards()`: la vista si limita a invocare il componente con `:cards="$cards"`,
senza alcuna duplicazione. Il partial `turing/partials/route-grid.blade.php`, ridotto a una singola riga di
passthrough, è stato rimosso; la chiamata al componente vive direttamente in `intro-section.blade.php`. Le
regole CSS `.turing-route-grid`/`.turing-route-card` in `turing.css`, ormai morte, sono state rimosse.

**Esplicitamente fuori scope di questa Pull Request:** i form CMS di amministrazione (`admin/turing*.blade.php`)
conservano l'opzione "Stile" con valori Turing-specifici (Enigma/AI/Legacy) — non modificati, perché estranei
alla sezione front-end analizzata; il file legacy `resources/views/turing.blade.php`, non instradato da alcuna
rotta, contiene una propria copia inline dello stesso pattern — non toccato in quanto codice morto non
raggiungibile, la sua rimozione non è stata richiesta ed è segnalata solo come osservazione.

**Stato della Pull Request associata.** La Pull Request "refactor(turing): extract reusable feature cards
component" implementa questa Decisione #006. È stata aperta per revisione e non è stata mergiata: resta in
attesa di approvazione, come da processo descritto nella sezione 3.

### Decision #007 – Scoping dei CSS di Turing e consolidamento del trattamento `has-bg`
Prima di questa decisione, il trattamento scuro delle sezioni con sfondo (`.turing-section.has-bg` e
`.turing-final-card.has-bg`) era prodotto da **due fonti in conflitto**:

- `public/css/turing.css`, caricato solo dalle pagine Turing, che definiva per gli stessi selettori un
  trattamento **chiaro** (overlay chiaro, testo scuro), in parte con `!important`;
- `public/css/turing-overrides.css`, incluso **globalmente** da `resources/views/layouts/partials/head.blade.php`
  (quindi su **tutte** le pagine pubbliche, comprese quelle non-Turing), che ridefiniva i medesimi selettori
  con un trattamento **scuro** (overlay `rgba(3,7,18,.62)`, testo bianco, `text-shadow`), interamente in
  `!important`.

Poiché `turing-overrides.css` era incluso in `head.blade.php` **prima** di `@yield('head')`, mentre le viste
Turing caricano `turing.css` **dentro** `@section('head')`, l'ordine di cascata reale era
`turing-overrides.css` → `turing.css`. Il rendering effettivo non era quindi "l'override vince", ma
un'**interazione**: overlay scuro e testo bianco fuori dal pannello (da overrides), pannello chiaro dell'head
con testo scuro (da turing.css, che a parità di specificità e `!important` vinceva perché caricato dopo). Questa
duplicazione, oltre a essere fragile, imponeva un foglio di stile Turing su ogni pagina del sito.

**Decisione.** `public/css/turing.css` diventa la **fonte unica di verità** per il trattamento `has-bg` di
Turing. Le regole di `turing-overrides.css` sono state assorbite in `turing.css`, riconciliando i valori con il
risultato **calcolato** effettivamente reso prima della modifica (overlay scuro `rgba(3,7,18,.62)`; testo base
bianco; pannello chiaro dell'head con testo scuro, kicker teal e paragrafo slate; kicker liberi e copy-panel di
Editorial/Legacy bianchi; `text-shadow 0 2px 18px rgba(0,0,0,.45)` dove già presente; `opacity .96` sui
paragrafi di head e copy-panel; final card con lo stesso trattamento scuro). L'inclusione globale di
`turing-overrides.css` è stata rimossa da `head.blade.php` e il file `public/css/turing-overrides.css` è stato
eliminato. Tutte le pagine Turing (index, enigma, ai) caricano già `turing.css`, quindi il trattamento resta
applicato esattamente dove serve, senza aggiungere include altrove; le pagine pubbliche non-Turing non
ricevono più un foglio di stile Turing di cui non facevano uso.

**`!important` rimossi e mantenuti.** Venuto meno il conflitto con l'override globale, sono stati rimossi i
quattro `!important` sulle regole di colore del pannello dell'head (`.turing-section__head`, `… .turing-kicker`,
`… h2`, `… > p:not(.turing-kicker)`): la loro specificità è sufficiente a vincere senza `!important`, e la
parità è stata dimostrata (stili calcolati identici e screenshot pixel-identici, vedi sotto). Le altre regole
di overrides sono state riscritte **senza** `!important` (non più necessario dopo l'eliminazione della fonte
concorrente). Nessun `!important` è stato mantenuto per questi selettori.

**Verifica dell'area amministrativa.** `admin/turing.blade.php` usa `@extends('layouts.admin')`; il layout admin
ha un proprio `<head>` che carica solo `css/admin.css` e **non** include `layouts.partials.head`: non caricava
`turing-overrides.css` né usa le classi pubbliche `.turing-section`/`has-bg` (è un editor CMS a form, non una
preview del front-end; l'anteprima avviene aprendo la pagina pubblica reale). La rimozione dell'include globale
ha quindi impatto **nullo** sull'admin e nessun cambiamento visivo è stato introdotto.

**Parità visiva dimostrata.** La modifica non introduce **alcun cambiamento visivo intenzionale**. La parità è
stata verificata confrontando gli stili calcolati (`getComputedStyle`) delle sezioni `has-bg` di `/turing`
(0 proprietà differenti) e con screenshot a livello di elemento di tutte e sei le sezioni `has-bg` più la final
card, a desktop 1440 px e mobile 390 px (0 pixel differenti). Le pagine `/turing/enigma` e `/turing/ai` non
usano `.turing-section.has-bg`/`.turing-final-card.has-bg` (stili propri con namespace `enigma-*`/`ai-*`),
quindi non sono influenzate; la homepage e le pagine pubbliche non-Turing non usano le classi `.turing-*` e
restano invariate (pagina statica `chi-siamo` byte-identica prima/dopo). L'intera suite `php artisan test`
(159/159) resta verde.

**Esplicitamente fuori scope di questa Decisione.** Nessun componente Special Project, nessuna sezione
applicativa (Hero, Intro, Editorial Blocks, Legacy, Final CTA), nessun elemento di Timeline / Chapter Opener /
Feature Cards, nessuna integrazione della Media Library e nessuna modifica funzionale a Enigma/AI: gli unici
effetti su queste pagine derivano automaticamente dal consolidamento di `turing.css`. La più generale
proliferazione dei fogli di stile globali in `head.blade.php` resta un debito noto, non affrontato qui.

**Stato della Pull Request associata.** La Pull Request "refactor(turing): consolidate has-bg dark treatment
into turing.css and drop global override" implementa questa Decisione #007. È stata aperta per revisione e non
è stata mergiata: resta in attesa di approvazione, come da processo descritto nella sezione 3.

## 15. Aggiornamento successivo alla Pull Request #41

### Decision #008 – Section Header riutilizzabile
Lo scheletro "kicker + titolo + testo introduttivo" ricorreva come markup Blade grezzo, non componentizzato, in
almeno 11 punti del progetto: `turing/partials/intro-section.blade.php`, `editorial-blocks.blade.php` (una volta
per blocco, in loop), `legacy-section.blade.php`, `final-card.blade.php`, oltre a un namespace di componenti
`<x-turing.article.*>` (`hero`, `cta`, `callout`) risultato **codice morto** (mai istanziato da alcuna vista) e
alle pagine monolitiche `turing/enigma.blade.php`/`turing/ai.blade.php`, che replicano lo stesso schema con CSS
inline proprio (`enigma-*`/`ai-*`), non condiviso. Il modello dati sottostante (`kicker`/`title`/`text`) era già
identico ovunque, ma la resa tipografica variava in modo non dichiarato fra tre scale diverse a seconda del
contenitore in cui il markup veniva incollato (`.turing-section__head`, centrata e più grande;
`.turing-copy-panel`, allineata a sinistra e più piccola; `.turing-final-card`, centrata con una terza scala).

**Decisione.** Il pattern diventa il componente riutilizzabile `<x-special.section-header>`
(`kicker`, `title`, `text`, `level` default `h2`, `align` con valori `left`/`center`, `variant` con valori
espliciti `section`/`panel`/`final`, ciascuno corrispondente a una delle tre scale tipografiche già in uso).
`level`, `align` e `variant` sono normalizzati contro un insieme chiuso di valori ammessi prima di comporre tag
HTML o classi CSS: un valore non riconosciuto ricade sempre sul default sicuro (`h2`/`center`/`section`), così
un dato arbitrario (es. proveniente da un campo CMS mal configurato) non può mai iniettare un tag o una classe
non previsti. Lo stile (`public/css/special-project.css`, namespace `.sp-section-header*`) riproduce le tre
scale tipografiche esistenti sui token `--sp-*`/`--font-display`, ma **non dichiara alcun colore** su titolo e
testo: la cascata colore di `/turing` (incluso il trattamento `has-bg` consolidato dalla Decisione #007) resta
governata invariata da `public/css/turing.css`, poiché i wrapper legacy (`.turing-section__head`,
`.turing-copy-panel`, `.turing-final-card`) restano al loro posto nei partial migrati. Il kicker porta
intenzionalmente due classi (`turing-kicker` e la nuova `sp-section-header__kicker`): la prima preserva
esattamente la cascata colore già validata su Turing, la seconda offre una base leggibile autonoma (token
`--sp-accent`) per un futuro Special Project privo di `turing.css`.

**Migrazione.** Il componente sostituisce il markup grezzo in `intro-section.blade.php` (`variant="section"`),
`editorial-blocks.blade.php` e `legacy-section.blade.php` (`variant="panel"`), `final-card.blade.php`
(`variant="final"`). `hero.blade.php` (h1, struttura a griglia con ritratto affiancato — un pattern diverso, non
forzato in questo componente), `turing/enigma.blade.php`, `turing/ai.blade.php` e il namespace morto
`<x-turing.article.*>` sono rimasti **esplicitamente fuori scope**: i primi due sono pagine monolitiche con CSS
proprio non condiviso, la cui migrazione richiederebbe un refactor di scala nettamente maggiore (l'intera
pagina, non solo l'header); il namespace morto non è mai stato istanziato e la sua rimozione non è stata
richiesta.

**Verifica di parità visiva.** Come per la Decisione #007, la modifica è stata validata confrontando gli stili
calcolati (`getComputedStyle`) di kicker/titolo/testo nelle quattro sezioni migrate prima e dopo (via
`git stash`), tutte e quattro nel loro stato `has-bg` di default. Il primo tentativo ha rivelato due
discrepanze reali (non il solo rumore `start`/`left`, equivalente in un documento LTR): `font-weight: 900`
applicato per errore anche alle varianti `panel`/`final` (che nell'CSS originale restavano al bold 700 di
default dell'h2, non dichiarato esplicitamente) e una dimensione/interlinea propria assegnata al testo della
variante `final` (che nell'originale non aveva mai una regola dedicata, ereditando il corpo di default). Corrette
entrambe, la verifica ripetuta non mostra più alcuna differenza reale. `php artisan test` resta verde (169/169,
10 nuovi test dedicati al componente in `tests/Feature/SpecialSectionHeaderTest.php`).

**Esplicitamente fuori scope di questa Decisione:** nessuna modifica a `hero.blade.php`,
`turing/enigma.blade.php`, `turing/ai.blade.php` o `components/turing/article/*`; nessuna rimozione del
namespace di componenti morto (segnalata come lavoro futuro); nessun commit né push in questa fase, come da
richiesta esplicita.

## 16. Aggiornamento successivo alla Pull Request #43

### Decision #009 – Modal riutilizzabile per gli Special Projects
Un'analisi del codebase, condotta prima di qualunque implementazione, ha rilevato che **nessun modale
riutilizzabile esisteva nel progetto**. Erano presenti solo due precedenti, entrambi insufficienti come base
architetturale: `components/newsletter-popup.blade.php` (unico markup con semantica ARIA reale — `role="dialog"`,
`aria-modal`, `aria-labelledby` — ma completamente hardcoded, senza `@props` né slot, incluso globalmente in
`layouts/app.blade.php`) e `admin/ads.blade.php` (stile e `onclick` interamente inline, nessun attributo ARIA,
nessuna gestione di ESC, backdrop o focus). Il relativo controller (`layouts/partials/newsletter-scripts.blade.php`)
presenta inoltre un ESC handler globale e incondizionato — chiude il popup ad ogni pressione di ESC ovunque nel
sito, anche a popup chiuso — identificato come anti-pattern da non replicare. In nessun punto del progetto erano
presenti focus trap, focus restore o soppressione (`inert`/`aria-hidden`) del contenuto di sfondo. Questa analisi
esegue un item già annunciato dalla Roadmap (sezione 6: *"l'introduzione dei modal dedicati agli eventi della
Timeline"*) e dalla Roadmap aggiornata della Decisione #003 (*"Timeline interattiva con card/modal in
sovraimpressione per ogni evento"*).

**Decisione.** Viene introdotto il componente riutilizzabile `<x-special.modal>` (`id`, `title`, `size` con
valori `sm`/`md`/`lg`, `closeLabel`), che riproduce la struttura fixed+overlay+box già validata da
`.newsletter-popup*` ma costruita sui token `--sp-*` (namespace CSS `.sp-modal*` in
`public/css/special-project.css`), senza riusarne le classi. Coerentemente con il pattern di normalizzazione già
stabilito dalla Decisione #008, `size` è validato con `in_array(..., true)` contro un insieme chiuso prima di
comporre la classe CSS, con fallback sicuro a `md`. Il componente parte sempre chiuso (attributo `hidden`) e non
espone alcuna prop per aprirlo lato server: l'apertura è un'azione client, gestita dal nuovo controller
`public/js/special-modal.js`, generico e riutilizzabile (trigger via `[data-sp-modal-target]`, chiusura via
`[data-sp-modal-close]`, overlay o tasto ESC), che corregge esplicitamente il bug dell'ESC handler globale
osservato nel popup newsletter: l'handler agisce solo quando un modale è effettivamente aperto. Il controller
implementa inoltre focus trap (ciclo del `Tab` contenuto nel dialog) e focus restore (ritorno del focus
all'elemento che ha aperto il modale alla chiusura) — funzionalità assenti da qualunque precedente nel progetto.

**Esplicitamente fuori scope di questa Pull Request.** Nessuna pagina o componente esistente istanzia ancora
`<x-special.modal>`: né `turing/index.blade.php`, né `<x-special.timeline>`, né alcun'altra vista Turing sono
state modificate. Il cablaggio tra gli eventi della Timeline e un modale (che richiederebbe di estendere il
branching `<a>`/`<div>` di `timeline.blade.php` con un terzo caso `<button data-sp-modal-target>`) resta
esplicitamente rimandato a una Pull Request successiva, per non alterare l'API pubblica di `<x-special.timeline>`
(`events`, `kicker`, `title`, `background`, `id`) in questa fase. Il componente è quindi pronto ma non ancora
collegato, secondo l'obiettivo dichiarato di "preparare il terreno" senza anticipare l'integrazione.

**Test.** Il componente è validato da 10 test dedicati in `tests/Feature/SpecialModalTest.php` (pattern
`Blade::render()` già introdotto dalla Decisione #008): semantica ARIA e contenuto dello slot, stato nascosto di
default, omissione di `aria-labelledby` quando `title` è assente, label di chiusura personalizzabile e con
default sicuro, normalizzazione di `size`, merge delle classi del chiamante, escaping di titolo e contenuto,
oltre a un test di non regressione che conferma il rendering di `/turing` senza alcuna istanza del modale.

## 17. Aggiornamento successivo alla Pull Request #44

### Decision #010 – Pagina di dettaglio dedicata all'Eredità di Turing
La PR #42 aveva reso cliccabile la card "03 · Eredità" collegandola all'anchor `#eredita` della pagina
principale. Un'analisi successiva ha stabilito che il requisito corretto è una pagina di dettaglio autonoma,
analoga a `/turing/enigma` e `/turing/ai`, non un semplice anchor: la sezione sintetica esistente introduce il
tema ma non può ospitare un approfondimento reale su eredità scientifica, guerra e crittoanalisi, intelligenza
artificiale, processo e persecuzione, riabilitazione istituzionale e memoria culturale.

**Decisione.** Viene introdotta la route pubblica `/turing/legacy` (nome `turing.legacy`), gestita da un nuovo
metodo `TuringPublicController::legacy()`, coerente con `enigma()`/`ai()` (nessun dato passato alla vista, stessa
responsabilità minima già in uso). La nuova vista `resources/views/turing/legacy.blade.php` **non** replica
l'approccio monolitico di Enigma/AI (CSS bespoke `.enigma-*`/`.ai-*` interamente duplicato, nessun componente
condiviso): un confronto diretto tra le due pagine, condotto prima di ogni implementazione, ha verificato che
condividono solo una forma narrativa (hero → sezioni tematiche → CTA finale con link incrociati), non una riga di
CSS o un componente in comune. Riprodurre una terza volta quello schema avrebbe significato un terzo blocco di
CSS bespoke, in diretto contrasto con i principi di riuso della sezione 2 di questo documento.

**Componenti adottati.** La pagina attiva per la prima volta il namespace `<x-turing.article.*>`
(`breadcrumb`, `hero`, `body`, `callout`, `cta`), introdotto ma mai istanziato prima d'ora (segnalato come
codice morto dalla Decisione #008, validato solo per esistenza da `TuringArticleInfrastructureTest`). Non è stato
usato `quote`, perché nel repository non esiste alcuna citazione storica verificata da attribuire a Turing: il
nucleo di sintesi conclusivo usa invece `callout` con un testo editoriale parafrasato, evitando di presentare una
citazione non verificata come autentica. Non è stato usato `figure`, perché nessuna sezione richiede un'immagine
distinta da quella già usata nell'hero e la Decisione esclude l'introduzione di nuovi asset. All'interno di ogni
blocco `<x-turing.article.body>`, i titoli di sezione riusano `<x-special.section-header variant="panel"
align="left">`, lo stesso componente e la stessa combinazione di varianti già usata da `editorial-blocks.blade.php`
e `legacy-section.blade.php` — nessun nuovo trattamento tipografico introdotto.

**Correzione motivata a `<x-turing.article.body>`.** Il componente, mai istanziato prima, renderizzava un
elemento radice `<main>`. Poiché `layouts/app.blade.php` avvolge già `@yield('content')` in un proprio `<main>`,
usarlo per la prima volta avrebbe prodotto un `<main>` annidato — HTML non valido e un doppio landmark per la
lettura assistiva. L'elemento radice è stato cambiato in `<div>`: nessuna prop, slot o classe del componente è
stata alterata, solo il tag HTML di un bug reale e mai emerso finora perché il componente non era mai stato usato.

**Incoerenza CSS residua corretta.** `.turing-article-breadcrumb`, usata dalla nuova pagina, non aveva alcuna
regola in `turing.css` (verificato, non presunto): sarebbe stata visivamente non stilizzata, lo stesso
bug-pattern già trovato e corretto per le feature card dalla Decisione #006. Sono state aggiunte le sole regole
minime necessarie, sui token `--sp-*` già esistenti (`--sp-ink-soft`, `--sp-accent`) con fallback identici ai
valori già in uso altrove in `turing.css`, senza introdurre nuovi selettori globali, nuovi breakpoint o alcuna
modifica agli stili di Enigma/AI. `.turing-article-figure` non è stata toccata, perché il componente `figure` non
viene usato in questa Pull Request.

**Navigazione.** La card "03 · Eredità" in `TuringPageController::defaultRouteCards()` punta ora a
`/turing/legacy` invece di `#eredita` (stesso stile letterale già in uso per `/turing/enigma`/`/turing/ai` in
quello stesso array, non `route()`). La sezione sintetica `#eredita` su `/turing`
(`turing/partials/legacy-section.blade.php`) resta **invariata nel contenuto**: aggiunge solo una CTA
(`.turing-actions`, stesso pattern già usato altrove) verso `route('turing.legacy')`. È stato inoltre verificato e
corretto un valore predefinito `#eredita` in `resources/views/admin/turing.blade.php`, usato per precompilare il
campo URL della card "Eredità" nel form CMS quando non esiste ancora alcun record `SpecialPage`: se salvato
invariato da un admin, quel valore verrebbe persistito nel JSON `content.cards` e sovrascriverebbe silenziosamente
il fallback pubblico, reintroducendo `#eredita` in produzione. Aggiornato a `/turing/legacy` per lo stesso motivo.
Gli analoghi default `#enigma`/`#intelligenza-artificiale` per le altre due card in quello stesso file, disallineati
anch'essi dai reali `/turing/enigma`/`/turing/ai`, restano un'incoerenza pre-esistente non affrontata da questa
Decisione, perché estranea al suo scopo.

**Esplicitamente fuori scope di questa Pull Request.** Nessuna modifica a `/turing/enigma` o `/turing/ai`; nessun
refactoring dei due controller Turing in uno solo; nessuna estrazione generale della logica CMS duplicata nelle
viste Enigma/AI; nessun uso del componente `<x-special.modal>` (introdotto dalla Decisione #009, resta
infrastruttura non collegata); nessun nuovo JavaScript; nessun nuovo asset grafico (l'immagine dell'hero riusa
`turing-legacy-panel.webp`, già esistente e validata da `TuringEditorialAssetsTest`).

**Test.** Nuovo file `tests/Feature/TuringLegacyPageTest.php` (9 test): risposta 200, esistenza della route,
rendering dei contenuti principali di ciascuna sezione, presenza del breadcrumb con `aria-current="page"`, link
verso `/turing`, `/turing/enigma` e `/turing/ai`, rendering corretto in assenza di dati CMS opzionali.
`TuringPageFallbacksTest` aggiornato: il test sulla card Eredità ora verifica `href="/turing/legacy"` e l'assenza
di `href="#eredita"`, oltre alla permanenza di `id="eredita"` e della nuova CTA sulla pagina principale.
`TuringArticleInfrastructureTest` esteso con la registrazione di `turing.legacy` e un test che verifica la
risposta 200 di tutte e tre le pagine di approfondimento Turing.

### Roadmap aggiornata
- [x] Trasformazione della sezione "03 · Eredità" in una pagina di approfondimento dedicata (questa Decisione).
- [ ] Timeline interattiva con card/modal in sovraimpressione per ogni evento — resta un item separato e
  distinto, non affrontato da questa Pull Request; l'infrastruttura del modale (Decisione #009) resta pronta ma
  non collegata.

## 18. Aggiornamento successivo alla Pull Request #45

### Decision #011 – Pagina di dettaglio dedicata alla Computazione di Turing
La sezione editoriale "Computazione" della pagina principale (`editorial_blocks`, chiave `macchina-universale`,
kicker "Computazione", titolo "La macchina universale e l'idea moderna di programma") era, a differenza dei
blocchi "Enigma" e "AI moderna", priva di qualunque collegamento: introduceva il tema della macchina universale e
della computabilità senza offrire un reale approfondimento, né un percorso di continuazione per il lettore.

**Decisione.** Viene introdotta la route pubblica `/turing/computation` (nome `turing.computation`), gestita da
un nuovo metodo `TuringPublicController::computation()`, con la stessa responsabilità minima già stabilita da
`enigma()`/`ai()`/`legacy()` (nessun dato passato alla vista). La nuova vista
`resources/views/turing/computation.blade.php` segue esattamente il modello architetturale introdotto dalla
Decisione #010: nessuna vista monolitica con CSS bespoke, riuso completo del namespace `<x-turing.article.*>`
(`breadcrumb`, `hero`, `body`, `callout`, `cta`) e di `<x-special.section-header variant="panel" align="left">`
per i titoli interni. Nessun nuovo file CSS è stato introdotto: l'intera pagina si appoggia sulle classi e sui
componenti già validati da `/turing/legacy`.

**Contenuto.** Sei sezioni tematiche distinguono esplicitamente il modello teorico dai computer elettronici
reali (prima dei computer, la macchina di Turing come modello matematico, algoritmi e computabilità — incluso un
accenno prudente al problema dell'arresto —, la macchina universale, l'influenza reale ma non lineare
sull'informatica successiva, i limiti del calcolo). Il testo evita deliberatamente l'affermazione semplicistica
secondo cui "Turing avrebbe inventato da solo il computer moderno". In assenza di una citazione storica verificata
già presente nel repository, non è stato usato il componente `quote`: il secondo box editoriale (oltre al callout
di sintesi richiesto) usa `callout` con un testo chiaramente parafrasato, mai presentato come citazione diretta —
stessa scelta già motivata dalla Decisione #010.

**Correzione di un'incoerenza CMS realmente verificata.** L'analisi ha accertato che `resources/views/admin/turing.blade.php`,
corretto nella Decisione #010 per un default `#eredita` sulla card "Eredità", **non è in realtà instradato da
alcuna route**: `Admin\TuringController::edit()` renderizza `admin.turing-lite`, non `admin.turing`. La correzione
della Decisione #010 era quindi innocua ma basata su un file irraggiungibile; lo si segnala qui per trasparenza,
senza rimuovere il file morto, la cui pulizia non è stata richiesta. Il file realmente vivo,
`resources/views/admin/turing-lite.blade.php`, conteneva per il blocco `macchina-universale` un default
`link_url => '#macchina-universale'` con `link_label` vuota: reso innocuo dallo stesso guard già presente in
`editorial-blocks.blade.php` (confronto tra `link_url` e `'#'.$blockId`), ma **effettivamente rilevante** perché
quel blocco, in questa vista amministrativa "lite", non è editabile dall'interfaccia e viene sempre reinviato
invariato come campo nascosto a ogni salvataggio: se un record CMS esiste già, il suo contenuto prevale su
`TuringPageController::defaultEditorialBlocks()`. Il default è stato quindi aggiornato a `/turing/computation`
con un'etichetta reale ("Scopri la macchina universale"), identica alla CTA introdotta nel fallback pubblico.
Resta un limite noto, non affrontato da questa Decisione: un record `SpecialPage` già esistente, salvato prima di
questa modifica, continuerebbe a contenere il vecchio valore finché non viene risalvato dall'area admin — non è
un problema di codice, ma di dati già persistiti, fuori portata di una modifica applicativa.

**Osservazione fuori scope, non affrontata.** L'analisi ha inoltre rilevato che `App\Providers\TuringServiceProvider`
registra un secondo gruppo di route Turing (`/turing`, `/turing/enigma`, e `/turing/ia` con nome `turing.ai`,
duplicato rispetto a `/turing/ai` in `routes/web.php`) tramite `Route::view()`, indipendente da questa Pull
Request e preesistente a tutte le Decision di questo documento. Non introduce alcun conflitto con le route
introdotte qui (`turing.legacy` e `turing.computation` non vi sono duplicate) e non è stato toccato: la sua
razionalizzazione, se necessaria, è un intervento distinto e non richiesto da questa PR.

**Navigazione.** Il blocco `macchina-universale` in `TuringPageController::defaultEditorialBlocks()` guadagna
`link_label`/`link_url`, attivando lo stesso meccanismo di CTA a blocco intero già usato da `enigma`/`ai-moderna`
in `editorial-blocks.blade.php`, senza alcuna modifica al partial. Nessun'altra sezione Turing (Enigma, AI,
Legacy, Timeline, Hero) è stata toccata.

**Esplicitamente fuori scope di questa Pull Request.** Nessuna pagina `/turing/intelligence` (rimandata a una PR
successiva: il collegamento "Dal calcolo all'intelligenza" nella CTA finale punta a `/turing/ai`, l'unica route
esistente, non a una route non ancora creata); nessuna integrazione del componente `<x-special.modal>` con la
Timeline; nessun redesign generale; nessuna modifica a Enigma, AI o Legacy.

**Test.** Nuovo file `tests/Feature/TuringComputationPageTest.php` (13 test): risposta 200, esistenza della
route, titolo e concetti principali (macchina di Turing, computabilità, macchina universale, limiti del calcolo),
breadcrumb, link di ritorno a `/turing`, assenza di `<main>` annidati, collegamento dalla pagina principale con
rimozione del vecchio self-link, invarianza delle altre pagine Turing, assenza di link verso la non ancora
esistente `/turing/intelligence`, assenza di regressioni con contenuti CMS non correlati.
`TuringPageFallbacksTest` aggiornato: il test sul blocco `macchina-universale` ora verifica la presenza del link
reale oltre alla persistente assenza del self-link. `TuringArticleInfrastructureTest` esteso con la nuova route e
la relativa risposta 200.

### Roadmap aggiornata
- [x] Introduzione di una pagina di approfondimento dedicata per la sezione "Computazione" (questa Decisione).
- [ ] Pagina `/turing/intelligence` — pianificata per una Pull Request successiva.
- [ ] Timeline interattiva con card/modal in sovraimpressione per ogni evento — non affrontata da questa Pull
  Request.
