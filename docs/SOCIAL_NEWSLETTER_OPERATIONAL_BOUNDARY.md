# Social e Newsletter — confine operativo di settembre

## Freshness (Prompt 51)

Il controllo canonico restituisce `NOT_APPLICABLE`: il dominio non definisce
una soglia unica. Policy proposta, da approvare prima del codice:

| tipologia | trigger di revisione | perché non è una scadenza automatica |
|---|---|---|
| notizia | evoluzione dell'evento o smentita | l'età sola non invalida il resoconto |
| evergreen | nuova evidenza sostanziale | può restare valido per anni |
| normativo | entrata in vigore/modifica fonte | dipende dalla giurisdizione |
| tecnologia | versione/supporto dichiarato | cicli diversi per prodotto |
| ricerca | replica, ritrattazione, consenso | pubblicazione nuova non implica superamento |

I test futuri devono congelare il tempo e provare trigger per tipologia, mai una
soglia globale.

## Workspace Social (Prompt 52–57)

Il database contiene `social_publications`, ma è un ledger di consegna
pending/processing/succeeded/retryable/failed, non un modello di bozza. Il tool
admin esistente genera URL UTM stateless per Facebook/LinkedIn e non persiste né
pubblica. Listener/job/provider esistono ma sono protetti dai flag production
certificati OFF.

V1 proposta senza credenziali:

- bozze separate per canale con testo, articolo, URL UTM, cover/alt, autore,
  revisore e data Europe/Rome;
- stati `draft → reviewed → approved → scheduled`, senza stato `published` e
  senza chiamata provider;
- preview locale server-rendered, accessibile, fixture fake e zero token;
- Command Center unico, fonti calendario separate e collisioni calcolate;
- autorizzazioni editor/reviewer, audit append-only e transizioni idempotenti.

Prompt 54–55 sono **DESIGN_BLOCKED**: sovraccaricare `social_publications`
confonderebbe bozze con tentativi di delivery; un modello persistente richiede
schema/migration e decisione umana, esplicitamente non autorizzati dal prompt di
discovery. Non è stata aperta una preview fittizia scollegata dal modello.

Runbook attivazione futura: secret solo in secret manager; staging e provider
test; approvazione separata; chiave idempotente; retry limitati; revoca token;
kill switch globale e per canale; incident log privacy-safe; rollback a flag OFF.
Nessuna chiamata Meta/LinkedIn prima di un gate umano dedicato.

## Newsletter (Prompt 58–60)

Communication 2.0 dispone di snapshot, freeze, eligibility ricalcolata prima
del provider, concorrenza/idempotenza e provider null/fake nei test. I report
aggregati non devono includere email. `unknown_legacy` e prima sorgente valida
restano contratti da verificare con fixture mirate.

Il test send reale (Prompt 59) è **AWAITING_SEPARATE_AUTHORIZATION**: serve un
unico destinatario verificato, conferma distinta, provider/preflight verde,
campagna frozen e prova zero duplicati. Nessun invio è stato eseguito.

È emerso un finding P1 nel legacy `SendNewsletterJob`: email e messaggio provider
finivano nel log. La correzione è isolata nel branch
`fix/newsletter-failure-log-privacy`; conserva solo subscriber ID e classe
errore, senza cambiare retry o invio.
