# Runbook: hosting cPanel e front controller pubblico

Cantiere 15 del programma "Kairus 100 cantieri" (vedi
`docs/KAIRUS_100_CANTIERI_TRACKING.md`). `docs/DEPLOYMENT.md` documenta già
l'architettura "due document root" e i suoi rischi lato contenuto/permessi;
questo runbook copre invece la parte che quel documento elenca
esplicitamente come **fuori scope** ("Web server configuration (Apache
vhost, `public_html` alias, `.htaccess`) is out of scope... nor validate
`.htaccess` rules") — il livello Apache/cPanel stesso, e il front
controller (`public/index.php`) che lo attraversa. Nessun codice
applicativo o dato di produzione è toccato da questo documento: è
operativo/di riferimento, per un umano che configura o verifica l'hosting.

## Architettura: due directory fisicamente separate

Come già descritto in `docs/DEPLOYMENT.md` ("Public asset deployment: two
document roots"):

- `~/kairus_app` — il checkout completo dell'applicazione Laravel (questo
  repository). `~/kairus_app/public` è la directory `public_path()` da cui
  legge tutto il codice PHP.
- `~/public_html` — la document root reale di Apache/cPanel. È l'unica
  directory che un browser raggiunge davvero via HTTP.

Questo runbook riguarda **esclusivamente** `~/public_html` e la sua
configurazione Apache (`.htaccess`): il contenuto applicativo dentro
`~/kairus_app` è già coperto da `docs/DEPLOYMENT.md` e dal drift detector
(`deploy:asset-drift`).

## Il front controller in `public_html`

Il front controller di Laravel (`public/index.php` in questo repository)
si aspetta di trovarsi *dentro* l'albero applicativo, un livello sotto la
radice del progetto:

```php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
```

Poiché `~/public_html` è una directory **fisicamente diversa** da
`~/kairus_app/public` (non un alias/symlink dell'una verso l'altra — se lo
fosse, il "content drift" che `deploy:asset-drift` verifica non potrebbe
mai verificarsi), il file `index.php` realmente presente in `~/public_html`
in produzione **non può essere una copia byte-per-byte** di
`public/index.php` di questo repository: i percorsi relativi
(`__DIR__.'/../vendor/autoload.php'`, `__DIR__.'/../bootstrap/app.php'`)
risolverebbero altrimenti dentro `~/public_html/../`, cioè `~/`, non dentro
`~/kairus_app/`.

Il front controller effettivamente pubblicato in `~/public_html/index.php`
deve quindi puntare esplicitamente all'albero applicativo, tipicamente con
percorsi assoluti — e non solo per le due righe più visibili: la riga 9 di
`public/index.php` (`if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php'))`)
usa lo stesso `__DIR__` relativo. Adattare solo `autoload.php` e
`bootstrap/app.php` e dimenticare questa terza riga lascia la modalità
manutenzione silenziosamente inefficace in produzione (il file cercherebbe
`~/storage/framework/maintenance.php`, dentro `~/`, non dentro
`~/kairus_app/`): un `artisan down` non avrebbe mai effetto sul sito
pubblico. Esempio completo, non solo un estratto parziale:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = '/home/<utente-cpanel>/kairus_app';

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

**Questo è il punto di configurazione più critico e più silenziosamente
fragile dell'intero hosting**: un `index.php` in `public_html` con il path
sbagliato (es. dopo una migrazione dell'account cPanel, un cambio di nome
utente, o una copia incauta di `public/index.php` così com'è da questo
repository) non genera un errore ovvio come un 404 — genera un fatal error
PHP ("Failed opening required ...") su **ogni singola richiesta pubblica**,
indistinguibile a prima vista da un'interruzione totale del sito. Nessun
codice o test in questo repository può verificarlo: gira interamente fuori
da `~/kairus_app`, che è l'unica directory che questo repository controlla.

**Verifica dopo ogni cambio di percorso, account, o migrazione cPanel:**

```bash
curl -sI https://kairus.it/ | head -1
```

Atteso: `HTTP/2 200`. Un 500 o un errore di connessione con nessun'altra
modifica recente al codice applicativo è il primo sospetto da escludere.

## `.htaccess`: cosa fa ogni blocco e perché

`public/.htaccess` (già git-tracked in questo repository, quindi con
storia e review — a differenza di `~/public_html/index.php` sopra, che non
lo è) deve essere copiato così com'è in `~/public_html/.htaccess` a ogni
deploy che lo modifica. Nessun meccanismo in questo repository verifica
che le due copie siano allineate (stesso "known limit" già dichiarato in
`docs/DEPLOYMENT.md`); questa sezione documenta cosa perdere se non lo
sono, cosicché un operatore possa riconoscere il sintomo.

- **Canonicalizzazione host/protocollo** (`RewriteCond %{HTTPS} off`,
  `www\.kairus\.it`) — redirect 301 permanente verso `https://kairus.it`.
  `%{HTTPS}` riflette la terminazione TLS reale di Apache/cPanel
  (AutoSSL), non un header inoltrato da un proxy — se in futuro un
  CDN/proxy venisse introdotto davanti ad Apache, questa condizione va
  rivalutata insieme a `X-Forwarded-Proto` (nota già presente nel file).
  Se questo blocco viene perso, `kairus.it` e `www.kairus.it` restano
  entrambi raggiungibili come pagine distinte — duplicazione di contenuto
  lato SEO, lo stesso problema di canonicalizzazione che
  `tests/Feature/HttpsCanonicalizationTest.php` copre lato applicativo
  (canonical/OG sempre https), ma quel test non può in alcun modo
  verificare che il redirect Apache stesso esista.
- **Blocco file sensibili** (`\.env$`, `\.git`, `storage/`,
  `bootstrap/cache/`, più il `<FilesMatch>` con `.sqlite`, `.sql`, `.bak`,
  ecc.) — 403 su richiesta diretta. Se perso, un file come `.env`
  (credenziali, `APP_KEY`) diventa scaricabile da chiunque conosca l'URL.
  Questo è il singolo rischio più grave dell'intero file.
- **Front controller** (`RewriteRule ^ index.php [L]`, condizionato a
  "non è un file o directory reale") — instrada ogni richiesta che non
  corrisponde a un file statico esistente verso `index.php`, cioè verso
  Laravel. Se perso, ogni rotta applicativa (articoli, categorie, admin)
  restituisce un 404 di Apache invece che una risposta Laravel.
- **Header di sicurezza** (`X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`) — deliberatamente *non* include HSTS: HSTS resta
  gestito da `SecurityHeaders` lato Laravel (solo in produzione, solo
  sulle risposte servite da Laravel) per non allargare la superficie di
  questa modifica — commento già presente nel file stesso.
- **Cache-busting statico** (`mod_expires`/`mod_headers`, 30 giorni per
  CSS/JS/font, 7 giorni per immagini) — policy di caching browser
  conservativa. CSS/JS restano comunque versionati via query-string
  (`App\Support\VersionedAsset`, vedi `docs/DEPLOYMENT.md`), quindi un
  `max-age` lungo qui non rischia di servire una versione stale dopo un
  deploy: cambia l'URL, non solo l'header.

**Verifica minima dopo ogni deploy che tocca `.htaccess`:**

```bash
curl -sI http://kairus.it/qualsiasi/path        # atteso: 301 -> https://kairus.it/...
curl -sI https://www.kairus.it/                 # atteso: 301 -> https://kairus.it/
curl -sI https://kairus.it/.env                 # atteso: 403
curl -sI https://kairus.it/.git/config          # atteso: 403
curl -s https://kairus.it/articolo/uno-slug-qualunque | head -5   # vedi sotto
```

L'ultima verifica richiede il **corpo** della risposta, non solo gli
header (`curl -s`, senza `-I`): sia Apache sia Laravel possono rispondere
con status 404 e `Content-Type: text/html` a uno slug inesistente, quindi
`curl -I` da solo non distingue i due casi proprio quando servirebbe di
più — quando il rewrite verso `index.php` è rotto. Un 404 Apache "nudo"
(il layout di errore predefinito del server, non il markup di questo
sito) nel corpo della risposta indica che il rewrite verso `index.php` non
sta avvenendo — sintomo tipico di un `.htaccess` mancante/non allineato o
di `mod_rewrite` non attivo sull'hosting. In alternativa, per un controllo
univoco senza ispezionare il corpo, usare una rotta nota per rispondere
sempre `200` (es. la homepage) e confrontarne lo status con quello di uno
slug inesistente: se entrambi restituiscono lo stesso 404 "nudo" invece di
un 200/404 Laravel distinti, il rewrite non sta funzionando.

## Configurazione cPanel non coperta da nessun file di questo repository

Questi punti vivono interamente nel pannello cPanel, non in alcun file
versionato — documentati qui perché altrimenti esisterebbero solo nella
memoria di chi ha configurato l'hosting la prima volta:

- **Document root del dominio** deve puntare a `~/public_html` (non a
  `~/kairus_app/public`) nella sezione "Domains"/"Aliases" di cPanel —
  coerente con l'architettura a due root già descritta.
- **Versione PHP** selezionata in "MultiPHP Manager" deve soddisfare il
  vincolo `"php": "^8.3"` di `composer.json`; questo repository esegue CI
  su PHP 8.4 (`.github/workflows/*.yml`), quindi 8.4 è la versione di
  riferimento raccomandata, non solo il minimo tecnico.
- **Estensioni PHP richieste da cPanel** (tipicamente già abilitate di
  default in MultiPHP: `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`,
  `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`) devono essere attive
  per l'utente/dominio specifico — cPanel le gestisce per versione PHP,
  non globalmente.
- **Entry crontab** per `php artisan schedule:run` — già segnalato come
  "esterno" in `docs/DEPLOYMENT.md` ("Cron/scheduler registration is
  external"): va registrato in "Cron Jobs" di cPanel **ogni minuto**, non
  a una cadenza più larga — `schedule:run` valuta solo gli eventi già
  scaduti al momento in cui viene invocato, e `routes/console.php`
  programma eventi a cadenza di un minuto (`articles:publish-scheduled`)
  e di cinque minuti; un cron orario o giornaliero farebbe perdere la
  quasi totalità delle finestre di pubblicazione/sincronizzazione, in
  silenzio. Espressione crontab completa:

  ```
  * * * * * cd ~/kairus_app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
  ```

  dove il binario PHP deve essere quello selezionato in MultiPHP per
  questo dominio (il percorso varia da hosting a hosting — verificarlo in
  cPanel, non assumere `/usr/local/bin/php`), non necessariamente
  `/usr/bin/php` di sistema. Un cron mancante, a cadenza troppo larga, o
  che punta al PHP CLI sbagliato non produce alcun errore visibile: i
  comandi schedulati (incluso `newsletter:reconfirmation-cleanup`)
  semplicemente non girano mai, in silenzio.
- **AutoSSL/certificato TLS** deve coprire sia `kairus.it` che
  `www.kairus.it` — il redirect canonico in `.htaccess` presuppone che
  entrambi gli host abbiano un certificato valido *prima* del redirect
  stesso (altrimenti il browser mostra un errore di certificato su
  `www.kairus.it` ancora prima che Apache possa reindirizzare altrove).

## Gate automatico su public/.htaccess (Cantiere 16)

`php artisan deploy:verify-front-controller` (`App\Services\Deploy\FrontControllerHtaccessAudit`)
verifica che `public/.htaccess` di QUESTA release contenga ancora ogni
direttiva critica descritta sopra — blocco file sensibili, front
controller, canonicalizzazione host/protocollo, header di sicurezza.
Solo lettura, mai scrittura. `deploy.sh` lo esegue automaticamente come
gate fail-closed: una direttiva persa (riscrittura accidentale, merge
risolto male) blocca il rilascio invece di essere scoperta solo dopo che
un operatore ha copiato il file rotto in `~/public_html/.htaccess`.

Eseguibile anche manualmente in qualunque momento:

```bash
php artisan deploy:verify-front-controller
```

## Cosa questo gate NON copre

Questo comando verifica solo il file git-tracked di QUESTA release, MAI
la copia realmente servita da Apache. `~/public_html/index.php` e
`~/public_html/.htaccess` restano fuori dalla portata di qualunque
comando o test di questo repository — nello stesso senso già dichiarato
in `docs/DEPLOYMENT.md` ("Known limits"): nessun codice qui ha mai
raggiunto l'host reale. Le verifiche via `curl` documentate sopra restano
l'unico modo per confermare che `public_html` rifletta davvero questo
file dopo che un operatore lo ha copiato manualmente.
