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
(`people`, `technology`, `history`, `science`, `abstract`, `ui`, `placeholders`) e tipo di utilizzo (`hero`,
`cover`, `chapter`, `editorial`, `background`, `gallery`, `thumbnail`) — una convenzione di naming neutra e in
inglese che non lega più permanentemente un asset generico a un singolo Special Project, e una distinzione
esplicita tra `library/` (asset riusabili per definizione) e `special-projects/<slug>/` (asset intenzionalmente
unici, come i ritratti reali). Generalizza inoltre lo standard tecnico e la convenzione di crediti/licenza già
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
