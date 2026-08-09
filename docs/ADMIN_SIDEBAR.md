# Sidebar admin — information architecture

Guida di riferimento alla struttura di navigazione del pannello admin
(`layouts/admin.blade.php`), nata dalla riorganizzazione gerarchica di
agosto 2026: la sidebar era cresciuta a ~26 voci sempre visibili sotto 8
intestazioni, alcune illeggibili (contrasto insufficiente) e una
("Pubblicità") senza nemmeno un'intestazione propria.

## Struttura

Due ancoraggi sempre visibili, mai raggruppati (`Dashboard` deve restare
raggiungibile senza aprire nulla; `Account` contiene il logout, sempre
rilevante):

- **Dashboard** — link diretto.
- **Account** — Profilo, Vedi sito (nuova scheda, `rel="noopener"`), Esci.

Tra questi due, otto gruppi collassabili (`<details>`/`<summary>` nativi,
nessun JavaScript richiesto per aprirli/chiuderli):

| Gruppo | Contiene | Criterio |
|---|---|---|
| Contenuti | Articoli, Categorie, Media, Commenti | Cosa produce/gestisce contenuto pubblicato |
| Redazione | Revisione, Fonti, Collaboratori | Workflow editoriale e persone |
| Progettazione | Panoramica, Progetti, Attività progetti, Calendario, Documenti | Area di project management interna, già cliente distinta nel codice (`admin.progettazione.*`) |
| Comunicazione | Dashboard, Campagne, Template, Mittenti, Newsletter, Anteprima newsletter | Tutto ciò che invia/prepara comunicazioni verso l'esterno (`admin.comunicazione.*` + Newsletter) |
| Strumenti | Turing, Assistente AI | Strumenti editoriali basati su AI, entrambi generano contenuto assistito |
| Monetizzazione | Pubblicità | Prima aveva zero intestazione propria; un solo elemento oggi, pronta a crescere |
| Analisi | Statistiche | Traffico/contenuti — prima infilata dentro "Strumenti" insieme a cose non correlate |
| Sistema | Attività (log di audit) | Prima anch'essa dentro "Strumenti"; qui è pronta a ricevere futuri strumenti di sistema (backup/diagnostica), se mai verranno esposti in UI |

**Nessuna voce è stata rimossa, nessuna route è cambiata.** Tre voci sono
state spostate rispetto alla vecchia sidebar (tutte precedentemente dentro
un unico gruppo "Strumenti" eterogeneo): Statistiche → Analisi, Attività →
Sistema, Anteprima newsletter → Comunicazione (accanto a Newsletter, da
cui era scorporata senza motivo).

## Comportamento active/open

Ogni gruppo si apre da solo, calcolato **lato server** ad ogni richiesta
(`request()->routeIs(...)` in `layouts/admin.blade.php`), quando la pagina
corrente gli appartiene — corretto già al primo render, prima che
qualunque JavaScript giri. Non c'è persistenza lato client dello stato
aperto/chiuso dei gruppi: dipende sempre da dove ti trovi, non da
un'interazione precedente.

L'attributo `open` di `<details>` non viene mai duplicato con
`aria-expanded`: il browser comunica da solo lo stato espanso/collassato
alle tecnologie assistive per l'elemento `<summary>`, e un `aria-expanded`
statico si disallineerebbe dal vero stato dopo un click (nessun listener
JS lo tiene sincronizzato, di proposito — vedi sotto).

## Componenti

`resources/views/components/admin/`:

- **`nav-link.blade.php`** — una voce (`route`, `active`, `icon`,
  `external`). Icona sempre `aria-hidden="true"` (l'etichetta testuale è
  già il nome accessibile). L'etichetta è sempre nel markup, mai
  rimossa — solo nascosta visivamente in modalità compatta (vedi sotto).
- **`nav-group.blade.php`** — un gruppo collassabile (`label`, `open`).

Estratti dal markup monolitico precedente per eliminare la ripetizione
del pattern `@class(['active'=>...]) @if(...) aria-current="page" @endif`
su 24 voci quasi identiche, e per centralizzare in un solo punto la
logica di accessibilità delle icone.

## Sidebar compatta (solo desktop)

Un pulsante «/» sotto il logo comprime la sidebar a soli 64px (solo
icone). Persistita in `localStorage` (`kairus-admin-sidebar-compact`),
applicata **prima del primo paint** da un piccolo script inline in testa
al `<body>` (evita il lampo "espansa poi si restringe"). Se
`localStorage` non è disponibile (privacy mode) o JavaScript è
disabilitato, la sidebar resta semplicemente estesa — nessuna
funzionalità di navigazione dipende da questo script.

In modalità compatta tutti i gruppi si aprono (senza testo, tenerli
chiusi non farebbe risparmiare spazio, solo nascondere icone); un CSS
dedicato mantiene comunque visibile il contenuto anche se un gruppo viene
richiuso per errore da tastiera. Le etichette testuali restano **sempre
nel DOM**, solo nascoste visivamente (stessa tecnica di `.sr-only`, mai
`display:none`): restano annunciate dalle tecnologie assistive
indipendentemente dallo stato visivo della sidebar.

Nascosto sotto i 901px: sotto quella soglia la sidebar è già un drawer a
scomparsa (invariato rispetto a prima di questa modifica — toggle,
overlay, focus trap, chiusura con Escape, tutto preesistente), comprimerla
non avrebbe senso.

## Leggibilità

Tre elementi di testo sulla sidebar avevano un contrasto insufficiente
contro lo sfondo `#111827` (misurato con la formula WCAG, non a occhio):
le intestazioni di gruppo (2.26:1), il sottotitolo "Pannello redazionale"
(2.69:1) e il ruolo utente in fondo (3.22:1) — tutti sotto la soglia AA di
4.5:1 per testo normale. Portati tutti a `rgba(255,255,255,0.55)` (6.06:1).

## Verifica manuale eseguita

Screenshot a 4 breakpoint (1920, 1366, 834, 390px) con un utente admin
reale, login incluso: nessun testo tagliato, nessuna sovrapposizione,
nessuno scroll orizzontale con il drawer mobile aperto, focus visibile
dopo Tab in modalità compatta, stato compatto persistito correttamente
dopo un reload di pagina, drawer mobile chiudibile con Escape.
