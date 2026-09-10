# Runbook editoriale — immagine mancante o rotta (autore o articolo)

Prompt 283-286. Guida per la redazione (non richiede accesso SSH/server):
cosa fare quando una foto autore o una copertina articolo appare rotta
(icona "immagine non disponibile" del browser) o assente sul sito
pubblico. Per la diagnosi e i comandi lato server, vedi invece la sezione
"Incident runbook: a public CSS/JS asset returns 404 or is unreadable" in
`docs/DEPLOYMENT.md` — quella copre gli asset di release (CSS/JS/icone),
non le immagini caricate dalla redazione, che sono l'oggetto di questa
pagina.

## 1. Individuare l'articolo e l'autore coinvolti

1. Apri la pagina pubblica dove l'immagine appare rotta e annota l'URL
   (es. `https://kairus.it/articolo/il-mio-slug` oppure
   `https://kairus.it/autore/12`).
2. Distingui quale immagine è rotta, perché la correzione è diversa:
   - **Copertina articolo**: rotta in cima all'articolo, nella card
     dell'articolo in `/notizie`/`/ricerca`, o nella card autore
     nell'elenco dei suoi articoli.
   - **Foto autore**: rotta nel box "Autore" sotto il corpo
     dell'articolo, o nell'header della pagina `/autore/{id}`.
3. Nel pannello di redazione, apri **Redazione → Articoli** e cerca
   l'articolo per titolo/slug per identificare `article_id` e l'autore
   assegnato; oppure apri **Redazione/Admin → Collaboratori** per
   risalire direttamente all'utente autore dal nome mostrato in pagina.

## 2. Correggere in admin

### Copertina articolo

1. Apri l'articolo in modifica (Redazione o Admin, a seconda del ruolo).
2. Nel campo copertina, carica di nuovo l'immagine (anche lo stesso file,
   se il problema è un caricamento incompleto o corrotto) rispettando i
   formati richiesti (vedi `docs/ARTICLE_COVER_ASSETS.md` per dimensioni e
   pesi consigliati).
3. Salva. Il caricamento genera automaticamente le varianti responsive
   necessarie: non serve alcuna azione tecnica aggiuntiva.

### Foto autore

1. Apri **Redazione → Profilo** (per la propria foto) oppure
   **Admin → Profilo/Collaboratori** (per la foto di un altro autore, se
   il tuo ruolo lo consente).
2. Carica di nuovo la foto profilo (JPEG/PNG/WebP, max 2 MB).
3. Salva. Anche qui le varianti responsive vengono generate
   automaticamente al salvataggio.

Se il campo foto risulta già valorizzato ma l'immagine resta rotta dopo
un nuovo caricamento, non è un problema che la redazione può risolvere da
sola: segnala il caso (con URL della pagina e nome dell'autore/articolo)
per una verifica tecnica — potrebbe trattarsi di un dato storico che
punta a un percorso diverso da quello usato oggi dai caricamenti (vedi
`docs/MISSION_75_USER_PHOTO_PRODUCTION_PREFLIGHT.md`), un caso che
richiede di essere verificato sui dati reali di produzione, non
riproducibile né risolvibile ricaricando semplicemente il file.

## 3. Verificare l'URL

1. Ricarica la pagina pubblica coinvolta con un refresh "forzato"
   (`Ctrl/Cmd+Shift+R`, oppure apri la pagina in una finestra privata/
   incognito) per escludere che sia il browser a mostrare una copia
   vecchia della pagina.
2. Conferma che l'immagine sia ora visibile sia nel punto originale
   segnalato sia in eventuali altri punti dove la stessa foto/copertina
   compare (card articolo, pagina autore, elenco categoria).
3. Se l'immagine è visibile in incognito ma non nel browser normale, è un
   problema di cache locale del browser di chi segnala il problema, non
   del sito: nessuna azione lato redazione è necessaria.

## 4. Pulire la cache

Le immagini caricate dalla redazione **non** passano da una cache
applicativa lato server (non c'è un comando "svuota cache" da eseguire
in admin per questo): la pagina che le mostra viene generata al volo a
ogni richiesta e legge direttamente il file caricato. Se l'immagine
resta rotta anche dopo il refresh forzato del punto 3:

- Verifica di aver effettivamente salvato il nuovo caricamento (torna
  nella scheda di modifica e controlla che l'anteprima mostri la nuova
  immagine).
- Se il sito è servito dietro un CDN o un proxy con cache propria (da
  verificare con chi gestisce l'hosting), potrebbe essere necessario un
  suo svuotamento indipendente dal sito — non risolvibile dal pannello
  di redazione; segnala il caso specificando che hai già ricaricato il
  file e verificato in incognito.

## Cosa NON fare

- Non modificare a mano l'URL dell'immagine o il nome del file nel
  pannello admin: il nome file viene generato automaticamente al
  caricamento e non va inventato o corretto manualmente.
- Non eliminare l'autore o l'articolo per "ripartire da zero": non
  risolve il problema e fa perdere dati reali (bio, social, cronologia).
- Non richiedere interventi diretti sul database di produzione: nessuna
  correzione descritta in questa pagina lo richiede.
