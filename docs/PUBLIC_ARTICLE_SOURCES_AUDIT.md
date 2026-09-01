# Missioni 18–19 — fonti pubbliche degli articoli

## Esito audit

`articles.primary_sources` è il campo canonico usato dal pannello di verifica,
da Content Health e dall'Editorial Quality Gate. Prima di questo intervento
non aveva alcun consumer pubblico. La pagina articolo mostrava invece soltanto
un formato legacy diverso: testo collocato nel corpo dopo un separatore `---`.

L'assenza non è una misura di privacy: il campo contiene riferimenti
bibliografici editoriali, non dati personali. È una funzione rimasta
incompleta e già segnalata in `GROWTH_S3_DISCOVER_READINESS_AUDIT.md`.

Il numero reale di articoli production coinvolti deve essere misurato con una
query aggregata read-only; non viene dedotto dalle fixture e nessun dato
production è stato letto durante l'implementazione.

## Contratto pubblico

- fonte primaria: `primary_sources`;
- fallback retrocompatibile: segmento legacy del body dopo `---`;
- separazione per riga, senza interpretare virgole presenti nei titoli;
- URL: soltanto `http` e `https` validi;
- DOI: collegamento normalizzato a `https://doi.org/{doi}`;
- testo non riconosciuto: preservato integralmente come testo;
- HTML e schemi non sicuri: sempre escapati, mai inseriti come markup;
- duplicati esatti tra campo canonico e legacy: rimossi in presentazione;
- nessuna fonte: nessuna sezione vuota.

La sezione usa un heading esplicito e un elenco ordinato. I collegamenti
esterni dichiarano `external noopener noreferrer` e una nota per screen
reader. Il JSON-LD non deriva `citation` da testo libero: il test esistente
che vieta questa inferenza resta invariato.

## Perimetro

Nessuna migration, riscrittura di `primary_sources`, chiamata di rete, nuova
dipendenza, cache persistente o modifica ai contenuti. La presentazione non
effettua query e mantiene visibile il formato legacy.
