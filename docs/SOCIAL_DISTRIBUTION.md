# Growth S3 — Social Distribution Foundation

## Contratto UTM: campagna ufficiale vs. condivisione organica

Due categorie di link verso lo stesso articolo, che non devono mai essere
confuse in analytics:

- **Condivisione organica** — un lettore clicca "Condividi" sulla pagina
  articolo (`resources/views/articles/partials/share-card.blade.php`: X,
  WhatsApp, LinkedIn, Copia link). Questi restano **sempre senza parametri
  UTM**, esattamente come prima di questa missione — verificato leggendo
  il partial, non modificato qui.
- **Campagna ufficiale** — un link generato deliberatamente per un post
  pubblicato dall'account social di Kairus (es. Facebook). Solo questi
  portano `utm_source`/`utm_medium`/`utm_campaign`.

Attribuire una condivisione spontanea a una campagna mai esistita (o
viceversa) renderebbe i report analytics inaffidabili — da qui la
distinzione netta: nessun pulsante pubblico genera mai un link con UTM,
solo lo strumento admin dedicato (`/admin/distribuzione-social`) lo fa, e
richiede un'azione esplicita del redattore.

## Perché non un'integrazione API diretta

v1 è deliberatamente un **generatore di link**, non un pubblicatore
automatico: il redattore copia il link e lo incolla manualmente nel post
Facebook. Nessuna integrazione OAuth/API Facebook, nessuna credenziale da
gestire — coerente con l'approccio già scelto per Search Console
(Missione 5 di questo batch) di preferire un flusso manuale verificabile
a una dipendenza esterna non disponibile in questo ambiente.

## v1 = un solo canale, architettura estensibile

`App\Services\SocialDistribution\UtmLinkGenerator::CHANNELS` è una mappa
`canale => utm_source`. Aggiungere un secondo canale (es. LinkedIn
ufficiale) significa aggiungere una entry, non riscrivere il contratto —
ma questa missione implementa **solo Facebook**, come richiesto dal
brief.

Parametri fissi per il canale Facebook:

```
utm_source=facebook
utm_medium=social
utm_campaign={slug definito dal redattore o generato automaticamente}
```

## Convenzione di naming campagna

Nessuna convenzione di slug esisteva già nel sistema Communication
(`CommunicationCampaign` usa un UUID + titolo libero, non uno slug —
verificato prima di inventarne una seconda incompatibile). Per le
campagne social si introduce quindi una convenzione dedicata:

- Alfabeto: solo `[a-z0-9]` e trattino singolo tra i gruppi (stesso
  alfabeto già usato per gli slug di Article/ContentCluster).
- Se il redattore non specifica nulla: `fb-{slug articolo}-{YYYYMMDD}` —
  sempre univoca per articolo+giorno, sempre decifrabile in un report
  analytics senza dover risalire altrove al contesto.
- Un'etichetta esplicita (stesso alfabeto) ha priorità, utile per
  raggruppare più articoli sotto un'unica spinta editoriale (es.
  `lancio-fisica-2026`).

## Nessun PII nei parametri

Il generatore non accetta mai un identificativo di lettore/iscritto — a
differenza del tracking newsletter esistente
(`NewsletterTrackingController`, che usa ID numerici in path + hash SHA-256
dell'IP, mai in query string pubblica), qui non esiste alcun destinatario
da tracciare: il link è pubblico e identico per chiunque lo clicchi.
L'alfabeto ristretto del campo campagna (solo lettere minuscole/cifre/
trattini) scoraggia inoltre l'incollamento accidentale di un nome o
indirizzo email nel parametro pubblico.

## Canonical non influenzato

`Article::metaCanonicalUrl()` non include mai una query string (né
`canonical_url` esplicito né il fallback `route()` bare) — verificato
anche dai test di regressione SEO preesistenti
(`ArchivePaginationCanonicalTest`, `OrganicGrowthSeoRegressionTest`), che
già affermano che un `utm_*` in query non finisce mai nel canonical.
Nessuna modifica necessaria lì.

## Interfaccia admin

`/admin/distribuzione-social` — form con slug articolo (deve esistere ed
essere pubblicato) e nome campagna opzionale, genera il link e lo mostra
con un pulsante "Copia". Nessuna persistenza: un GET stateless, ripetibile
e condivisibile via URL con querystring propria.
