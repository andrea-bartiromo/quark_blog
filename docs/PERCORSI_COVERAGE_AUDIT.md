# Percorsi Editorial Coverage Audit

Missione 13 — audit diagnostico, sola lettura.

## Scopo

`PercorsoCoverageAuditService` fotografa la copertura reale dei Percorsi senza assegnare, riordinare o pubblicare articoli.

Segnala fatti deterministici:

- articoli `published` senza alcun Percorso;
- articoli `scheduled` senza alcun Percorso;
- Percorsi con un solo membro;
- posizioni duplicate nella pivot;
- membri `draft`/`review` dentro un Percorso;
- pillar configurato ma esterno al Percorso, mancante o non pubblicabile;
- articoli presenti in più Percorsi, come informazione editoriale.

## Decisioni conservative

L'assenza di pillar **non è un errore**: `ContentCluster::pillar_article_id` è nullable e il dominio corrente non impone un pillar a ogni Percorso.

Anche l'appartenenza a più Percorsi non viene classificata automaticamente come errore: il repository non definisce una soglia massima. Il servizio espone il conteggio e lascia la decisione alla redazione.

## Confini

- nessuna UI Admin in questa PR;
- nessuna modifica a `Article.php` o `ContentCluster.php`;
- nessuna mutation o auto-assignment;
- nessuna migration;
- nessuna integrazione con #280 Content Health finché #280 non è realmente su `main`.

## Gate

La sessione che ha creato questa branch opera via GitHub online e non può dichiarare `LOCAL GREEN`. Prima del merge: focused test, full PHP suite, Pint e `git diff --check`.
