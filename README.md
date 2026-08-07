# Kairus

> **Una piattaforma editoriale moderna sviluppata con Laravel per la divulgazione scientifica e tecnologica.**

## Il progetto

Kairus è una piattaforma editoriale sviluppata con Laravel nata dall'idea che un CMS moderno debba fare molto più che pubblicare articoli.

L'obiettivo del progetto è costruire un ambiente di lavoro completo per una redazione digitale, capace di accompagnare ogni fase del ciclo editoriale: dalla nascita di un'idea fino alla pubblicazione e alla distribuzione dei contenuti.

Più che un semplice blog, Kairus vuole essere uno spazio in cui autori, editor e amministratori possano collaborare in modo naturale, condividendo un unico flusso di lavoro costruito attorno alla qualità dell'informazione.

Ogni funzionalità è stata progettata pensando prima alle esigenze della redazione e solo successivamente all'implementazione tecnica. La scrittura, la revisione, la gestione delle immagini, l'ottimizzazione SEO e la pubblicazione non sono moduli indipendenti, ma parti di un ecosistema integrato che rende il processo editoriale semplice, ordinato e facilmente estendibile.

Parallelamente alla crescita delle funzionalità, grande attenzione è stata dedicata anche alla qualità del codice. Fin dall'inizio il progetto è stato sviluppato seguendo principi architetturali ben definiti, privilegiando servizi riutilizzabili, logica centralizzata e componenti condivisi, con l'obiettivo di costruire una piattaforma solida e facilmente manutenibile nel tempo.

Kairus rappresenta oggi un progetto personale in continua evoluzione, sviluppato come portfolio tecnico e come laboratorio nel quale sperimentare buone pratiche di sviluppo software, progettazione di architetture Laravel e realizzazione di piattaforme editoriali moderne.

---

# La filosofia del progetto

La maggior parte dei CMS nasce per consentire la pubblicazione di contenuti. Kairus nasce invece per supportare il lavoro di una redazione.

Questa differenza influenza ogni scelta progettuale.

Nel corso dello sviluppo si è cercato di costruire un sistema che accompagnasse naturalmente il lavoro quotidiano degli autori, evitando procedure complicate o funzionalità isolate tra loro. Ogni componente dell'applicazione è stato progettato per integrarsi con gli altri, mantenendo un'esperienza coerente sia per chi scrive gli articoli sia per chi li revisiona e li pubblica.

Allo stesso modo, anche l'architettura del software è stata pensata per poter evolvere nel tempo. Nuove funzionalità vengono introdotte senza compromettere quelle esistenti, privilegiando componenti riutilizzabili, servizi condivisi e una netta separazione delle responsabilità.

L'obiettivo non è semplicemente sviluppare un'applicazione funzionante, ma costruire una piattaforma editoriale che possa crescere mantenendo ordine, qualità e coerenza.

---

# Cosa offre Kairus

Nel corso dello sviluppo Kairus si è trasformato in una piattaforma editoriale completa che integra strumenti dedicati sia agli autori sia agli editor.

Tra le principali funzionalità troviamo:

- un CMS completo per la gestione degli articoli e delle categorie;
- un'area Redazione riservata ai collaboratori, dedicata alla scrittura e alla modifica dei propri contenuti;
- un workflow editoriale che prevede la revisione degli articoli prima della pubblicazione;
- un pannello di amministrazione completo per la gestione dell'intero progetto;
- una Media Library centralizzata per la gestione delle immagini;
- un sistema SEO avanzato con metadati personalizzati, Open Graph, Twitter Cards, Sitemap XML, News Sitemap e Feed RSS;
- un sistema di newsletter con iscrizione pubblica, invio automatico e tracciamento delle aperture;
- strumenti di supporto alla redazione basati sull'API di Anthropic Claude per la generazione di bozze di articoli;
- una sezione dedicata ai progetti editoriali speciali, come **Turing**, completamente configurabile dal pannello di amministrazione;
- un sistema di monitoraggio delle attività amministrative attraverso l'Activity Log.

L'obiettivo non è accumulare funzionalità, ma costruire strumenti che lavorino insieme in maniera coerente e che semplifichino il lavoro quotidiano della redazione.

---

# Architettura

Kairus è organizzato come un insieme di moduli indipendenti ma strettamente integrati.

Il frontend pubblico rappresenta il punto di accesso ai contenuti editoriali e comprende homepage, pagine articolo, categorie, ricerca, pagine autore e pagine istituzionali.

L'area **Redazione** è dedicata agli autori e costituisce l'ambiente nel quale vengono scritti e aggiornati gli articoli. Ogni contenuto segue un processo di revisione prima di poter essere pubblicato, consentendo agli editor di verificare qualità, completezza e attendibilità delle informazioni.

L'area **Amministrazione** raccoglie invece tutti gli strumenti necessari alla gestione della piattaforma: articoli, categorie, collaboratori, commenti, newsletter, media, suggerimenti generati tramite AI, statistiche, pubblicità e registro delle attività.

Accanto a queste aree principali trovano posto alcuni sottosistemi specializzati che svolgono un ruolo fondamentale nell'architettura complessiva del progetto.

La **Media Library** centralizza la gestione delle immagini, evitando duplicazioni e offrendo un unico punto di accesso per l'intero patrimonio multimediale.

Il sistema **SEO** gestisce in modo centralizzato metadati, Open Graph, Twitter Cards, Sitemap XML, News Sitemap e Feed RSS, contribuendo a migliorare la visibilità dei contenuti sui motori di ricerca e sui social network.

Il modulo **Special Projects** permette invece di realizzare sezioni editoriali dedicate, completamente configurabili dal pannello amministrativo, senza modificare il codice dell'applicazione.

L'intera architettura privilegia servizi condivisi, componenti riutilizzabili e logica centralizzata, riducendo la duplicazione del codice e facilitando l'evoluzione del progetto nel tempo.

---

# Stack tecnologico

Kairus è sviluppato utilizzando tecnologie moderne e consolidate all'interno dell'ecosistema Laravel.

Il backend è realizzato con **Laravel 13** e **PHP 8.3**, mentre l'interfaccia utilizza **Blade** come motore di template.

Per lo sviluppo e i test viene utilizzato **SQLite**, mentre la compilazione degli asset è affidata a **Vite**. Il progetto include inoltre una pipeline frontend configurata con **Tailwind CSS 4**, pur adottando fogli di stile CSS personalizzati per l'interfaccia pubblica e per il pannello amministrativo.

La gestione delle immagini sfrutta l'estensione **GD**, mentre la generazione automatica delle bozze editoriali è affidata all'API di **Anthropic Claude**.

La qualità del codice viene garantita attraverso **PHPUnit**, **Playwright** e una pipeline di **GitHub Actions**, che esegue automaticamente i test ad ogni Push e Pull Request.

---

# Requisiti

Per eseguire il progetto sono necessari:

- PHP 8.3 o superiore
- Composer 2.x
- Node.js 18 o superiore
- npm
- SQLite

Sono inoltre consigliate le seguenti estensioni PHP:

- pdo
- pdo_sqlite
- mbstring
- openssl
- curl
- dom
- fileinfo
- libxml
- zip
- pcntl

L'estensione **GD** è facoltativa ma consigliata per il ridimensionamento e la compressione automatica delle immagini.

---

# Installazione

L'installazione del progetto richiede pochi passaggi.

```bash
# Clona il repository
git clone https://github.com/andrea-bartiromo/quark_blog.git

cd quark_blog

# Installa le dipendenze PHP
composer install

# Installa le dipendenze JavaScript
npm install

# Configura l'ambiente
cp .env.example .env

php artisan key:generate

# Crea il database SQLite
touch database/database.sqlite

# Esegui migration e seed
php artisan migrate --seed

# Compila gli asset
npm run dev

# Avvia il server di sviluppo
php artisan serve
```

L'applicazione sarà raggiungibile all'indirizzo:

```
http://127.0.0.1:8000
```

In alternativa è possibile utilizzare il comando:

```bash
composer run dev
```

che avvia contemporaneamente il server Laravel, il queue worker, il log viewer e Vite.

---

# Account demo

Per semplificare lo sviluppo locale il database viene popolato automaticamente con alcuni utenti dimostrativi.

Sono disponibili un account Editor e due account Autore, utilizzabili esclusivamente in ambiente locale.

| Ruolo | Area | Email | Password |
|--------|------|--------|----------|
| Editor | Amministrazione | m.esposito@kairus.it | password123 |
| Autore | Redazione | s.ricci@kairus.it | password123 |
| Autore | Redazione | e.romano@kairus.it | password123 |

Gli URL di accesso alle aree di Amministrazione e Redazione sono volutamente non prevedibili e possono essere visualizzati eseguendo:

```bash
php artisan route:list --name=login
```

Questi account non devono essere utilizzati in ambienti pubblici o di produzione.

# Gestione delle immagini

Le immagini rappresentano un elemento fondamentale di qualsiasi progetto editoriale e, per questo motivo, Kairus dedica particolare attenzione alla loro gestione.

L'intero processo di caricamento e ottimizzazione è centralizzato all'interno di `ImageService`, un servizio applicativo che si occupa del naming dei file, del salvataggio sul disco, del ridimensionamento e della compressione delle immagini quando l'estensione GD è disponibile.

Questa scelta consente di evitare duplicazioni di codice tra i diversi controller e garantisce un comportamento uniforme in ogni punto dell'applicazione.

Ogni articolo può inoltre essere arricchito con un insieme di metadati editoriali dedicati alle immagini di copertina, tra cui testo alternativo, didascalia, crediti, fonte, URL della fonte e licenza. Queste informazioni migliorano non solo l'accessibilità dei contenuti, ma anche la qualità editoriale complessiva e l'ottimizzazione per i motori di ricerca.

Quando un articolo, una categoria o un autore non dispongono di un'immagine dedicata, il sistema utilizza automaticamente placeholder SVG generati internamente, evitando dipendenze esterne e mantenendo un aspetto grafico coerente in tutto il sito.

---

# Activity Log

La gestione di una redazione richiede la possibilità di ricostruire le principali operazioni eseguite dagli amministratori.

Per questo motivo Kairus include un sistema di registrazione delle attività che memorizza automaticamente gli eventi più significativi, come la creazione, la modifica o l'eliminazione di articoli, la gestione dei collaboratori e altre operazioni amministrative.

L'Activity Log rappresenta uno strumento utile sia per il monitoraggio dell'attività della redazione sia per facilitare eventuali operazioni di verifica o manutenzione.

---

# Sicurezza

La sicurezza costituisce uno degli aspetti considerati fin dalle prime fasi di progettazione.

La configurazione dell'applicazione è basata sul file `.env`, che non viene mai versionato all'interno del repository. Ogni installazione genera autonomamente una nuova chiave applicativa tramite `php artisan key:generate`, evitando la condivisione accidentale di informazioni sensibili.

L'invio della posta elettronica supporta server SMTP esterni, come Gmail mediante App Password dedicate, senza richiedere l'utilizzo delle credenziali dell'account principale.

L'applicazione applica inoltre header HTTP di sicurezza attraverso middleware dedicati e utilizza sistemi di rate limiting per proteggere le principali funzionalità pubbliche, come newsletter, commenti, modulo contatti e autenticazione.

Le linee guida complete relative alla gestione delle credenziali, delle chiavi applicative e delle configurazioni sensibili sono raccolte nel documento `SECURITY.md`, che rappresenta il riferimento ufficiale per gli aspetti legati alla sicurezza del progetto.

---

# Testing e qualità del codice

La qualità del software rappresenta uno degli obiettivi principali dello sviluppo di Kairus.

Ogni nuova funzionalità viene accompagnata da test automatici che permettono di verificare il corretto funzionamento dell'applicazione e ridurre il rischio di regressioni.

La suite di test comprende Unit Test e Feature Test realizzati con PHPUnit, ai quali si affiancano test end-to-end sviluppati con Playwright per verificare il comportamento dell'interfaccia utente.

L'intero processo viene eseguito automaticamente tramite GitHub Actions ad ogni Push e Pull Request verso il branch principale, garantendo che ogni modifica venga verificata prima di essere integrata nel progetto.

Per eseguire la suite di test in locale è sufficiente utilizzare il comando:

```bash
php artisan test
```

I test utilizzano un database SQLite dedicato e completamente separato dall'ambiente di sviluppo, assicurando un'esecuzione ripetibile e indipendente.

---

# Workflow di sviluppo

Lo sviluppo di Kairus segue un processo incrementale che privilegia modifiche piccole, facilmente revisionabili e accompagnate da test.

Ogni nuova funzionalità viene sviluppata all'interno di un branch dedicato, implementata seguendo gli standard architetturali del progetto e successivamente verificata attraverso Laravel Pint, PHPUnit e Playwright.

Solo dopo il completamento dei controlli automatici viene aperta una Draft Pull Request, sottoposta a revisione tramite CodeRabbit e, infine, integrata nel branch principale.

Questo approccio permette di mantenere il progetto stabile durante l'intero ciclo di sviluppo e rende ogni modifica facilmente tracciabile.

---

# Principi progettuali

Nel corso dello sviluppo sono stati adottati alcuni principi che guidano ogni decisione architetturale.

Il primo consiste nell'affrontare sempre la causa di un problema, evitando soluzioni temporanee o workaround che potrebbero complicare l'evoluzione futura del software.

Grande importanza viene inoltre attribuita alla separazione delle responsabilità, alla centralizzazione della logica condivisa e alla realizzazione di componenti riutilizzabili, con l'obiettivo di mantenere il codice semplice da comprendere e facilmente estendibile.

Ogni nuova funzionalità viene introdotta attraverso Pull Request di dimensioni contenute, accompagnate da test automatici e da una documentazione aggiornata. Questo processo favorisce una crescita ordinata del progetto e consente di preservarne la qualità anche nel lungo periodo.

---

# Roadmap

Kairus è un progetto in continua evoluzione.

Negli ultimi mesi sono stati completati alcuni dei moduli più importanti della piattaforma, tra cui il workflow editoriale, la Media Library centralizzata, il sistema SEO avanzato, la newsletter, l'Activity Log, l'integrazione con Anthropic Claude e la sezione editoriale dedicata ai progetti speciali.

Lo sviluppo prosegue attualmente con il consolidamento della documentazione tecnica, l'estensione della copertura dei test automatici e l'introduzione di nuove funzionalità dedicate all'esperienza editoriale, come la generazione automatica dell'indice degli articoli e ulteriori miglioramenti dell'interfaccia amministrativa.

Guardando al futuro, gli obiettivi principali riguardano il completamento della versione 1.0 della piattaforma, il miglioramento delle prestazioni, l'evoluzione della Media Library, l'ottimizzazione dell'esperienza mobile e la realizzazione di una demo pubblica del progetto.

---

# Documentazione

La documentazione accompagna l'intero ciclo di vita del progetto e viene aggiornata parallelamente allo sviluppo del software.

Questo README rappresenta il punto di partenza per comprendere finalità, funzionamento e modalità di installazione della piattaforma.

Per una descrizione approfondita dell'architettura, delle decisioni progettuali e dell'evoluzione del sistema è disponibile il **Project Book**, che costituisce il documento di riferimento dell'intero progetto.

Gli aspetti legati alla sicurezza sono invece documentati all'interno di `SECURITY.md`, mentre eventuali ulteriori documenti tecnici verranno raccolti nella cartella dedicata alla documentazione del repository.

---

# Copyright e licenza

**Copyright © 2026 Andrea Bartiromo. Tutti i diritti riservati.**

Questo è un progetto proprietario sviluppato come portfolio tecnico e come laboratorio personale per la progettazione di piattaforme editoriali moderne basate su Laravel.

Il repository è reso pubblicamente consultabile esclusivamente a scopo dimostrativo, di studio e di valutazione professionale. La pubblicazione del codice non costituisce in alcun modo una rinuncia ai diritti dell'autore né implica la concessione di una licenza open source.

Salvo ove diversamente indicato, il codice sorgente, la documentazione, il design dell'interfaccia, la struttura dell'applicazione e i contenuti editoriali sono protetti dalla normativa sul diritto d'autore.

Non è consentita la copia, la modifica, la redistribuzione, la pubblicazione o qualsiasi altro utilizzo del progetto, in tutto o in parte, senza il preventivo consenso scritto dell'autore.

Se desideri utilizzare Kairus come riferimento tecnico, approfondire alcune soluzioni architetturali o discutere il progetto, puoi contattare direttamente l'autore attraverso i canali indicati nel profilo GitHub.
