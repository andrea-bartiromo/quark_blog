# Mission 09 — VersionedAsset Hash Strategy Audit

**Stato: nessuna modifica spedita.** L'analisi conclude che il meccanismo
attuale (SHA di release con fallback su mtime) resta la scelta migliore
per questa specifica architettura a due alberi — cambiarlo introdurrebbe
un rischio reale senza un beneficio dimostrato, in violazione diretta del
vincolo esplicito della missione ("Do not change mechanism unless benefit
is clear and backward-safe").

## Le tre opzioni confrontate

| Strategia | Come funziona | Costo per richiesta |
|---|---|---|
| **mtime** (comportamento pre-fix, ancora il fallback) | `filemtime(public_path($file))` | 1 `stat()` |
| **SHA di release** (comportamento attuale, quando `REVISION` esiste) | Contenuto di `REVISION`, scritto da `deploy.sh` dopo un deploy verificato | 1 lettura di file di poche decine di byte |
| **Hash di contenuto** (`sha256`/`md5` del file) | Calcolato per-file dal contenuto reale | 1 `hash_file()` per asset per richiesta (o una cache dietro) |

## Perché l'hash di contenuto NON è un miglioramento qui

L'incidente reale che ha motivato il fix originale (`public-premium.css`,
notte del 24/08) era esattamente questo: **una versione calcolata dal
contenuto/mtime dell'albero applicativo (`kairus_app/public`, da cui
`VersionedAsset` legge) può non riflettere ciò che l'albero servito
(`public_html`) sta realmente restituendo al browser**, perché nulla
sincronizza automaticamente i due alberi per CSS/JS.

Un hash di contenuto calcolato da `VersionedAsset::url()` leggerebbe
*sempre* dallo stesso identico albero applicativo che mtime già legge
oggi — **la stessa identica classe di staleness**, solo con una firma
diversa (hash invece di timestamp) invece di eliminare il problema.
Per calcolare un hash che riflette davvero ciò che `public_html` serve,
`VersionedAsset` dovrebbe leggere ANCHE l'albero servito a ogni richiesta
— esattamente il confronto cross-tree che `PublicAssetDriftDetector` già
fa, ma quel servizio è deliberatamente pensato per un controllo
diagnostico/di release (`deploy:asset-drift`, sola lettura, eseguito una
volta per deploy), non per essere richiamato a ogni GET pubblica: il
codice del progetto è esplicito altrove sul principio "mai un costo extra
durante una GET pubblica normale" (vedi la docblock di
`ResponsiveImageVariantService::resolveForMarkup()`).

Il SHA di release evita interamente questo compromesso: non dipende dallo
stato di NESSUno dei due alberi, quindi non può mai essere stale rispetto
a nessuno dei due. Il prezzo pagato è che ogni asset della release cambia
querystring a ogni deploy anche se il suo contenuto non è cambiato — un
browser rifà una richiesta condizionale in più per gli asset invariati,
un costo trascurabile (decine di file statici, non migliaia) rispetto al
rischio eliminato.

## Conclusione

Per un'architettura a due alberi senza sincronizzazione automatica di
CSS/JS, il SHA di release è strutturalmente superiore sia a mtime sia a
un content-hash calcolato dall'albero applicativo: è l'unica delle tre
opzioni la cui correttezza non dipende dallo stato di un filesystem che
può divergere. Il fallback su mtime resta corretto per il suo scopo
attuale (sviluppo locale/CI, dove non esiste alcuno split a due alberi
da cui derivare staleness).

**Nessuna azione richiesta.** `VersionedAsset` resta invariato.
