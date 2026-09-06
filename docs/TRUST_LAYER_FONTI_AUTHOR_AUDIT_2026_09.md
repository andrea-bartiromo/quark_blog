# Audit editoriale Fonti / Trust Layer / pagina autore — 2026-09 (Prompt 071-080)

Audit read-only di Fonti (legacy e strutturate), Trust Layer e pagina
pubblica autore (`/autore/{user}`), la superficie esplicitamente esclusa
dal perimetro dei 7 "canonical surfaces" (vedi
`docs/PUBLIC_SURFACES_QA_MATRIX.md`, riga 5-7) e quindi mai controllata
finora per gli stessi criteri.

## Metodo

Confrontate le date degli ultimi commit che hanno toccato ciascuna vista
pubblica rilevante con le date dei documenti di audit che le riguardano
(`docs/PUBLIC_TRUST_LAYER_AUDIT.md`, `_QA.md`, `_INVARIANTS.md`,
`docs/KAIRUS_TRUST_RECONCILIATION_HANDOFF.md`) per individuare drift reale,
non presunto — stesso metodo usato per il Measurement Closeout
(Prompt 041-060).

## Cosa era già coperto e resta valido

`docs/PUBLIC_TRUST_LAYER_AUDIT.md`/`_QA.md` (commit `f995064`,
2026-09-05 14:09) sono più recenti degli ultimi commit che hanno toccato
`resources/views/autore.blade.php` (`fec47ca`, 09:07) e
`resources/views/metodologia.blade.php` (`e27bbec`, 09:09) — nessun drift
lì. `App\Services\ArticlePrimarySourcesParser` (riletto per intero):
riconosce solo una riga interamente un URL http(s) assoluto o un DOI, mai
un'estrazione parziale via regex da testo misto; valida lo schema
(`http`/`https` soli) prima di promuovere qualunque riga a link — nessuno
schema pericoloso (`javascript:`, `data:`) può mai raggiungere un `href`.
Confermato con `ArticlePublicPrimarySourcesTest::test_hostile_markup_in_primary_sources_is_escaped_not_executed`
(gia' verde).

## Cosa era già stato corretto in questa sessione (Measurement Closeout)

L'unico commit successivo a tutti questi audit che tocca una vista
pubblica rilevante è `eb2cad1` (riconciliazione Fonti primarie +
Revisioni qualificate, PR #532) — la regressione reale che ha introdotto
(`<h3>` invece di `<h2>` su `x-article.primary-sources`) è già stata
trovata e corretta nel cantiere Prompt 041-060
(`docs/MEASUREMENT_CLOSEOUT_2026_09.md`). La parte "Revisioni qualificate"
della stessa PR (`$lastEditorialUpdate`, usato in
`articles/partials/hero.blade.php` per un `<li>` di `x-kairus.article-meta`
e in `dateModified` del JSON-LD) non introduce alcun elemento heading —
verificato qui, nessun problema.

## Trovato e corretto in questo cantiere

`/autore/{user}` pagina (`AuthorController::show()`, `paginate(12)`) esattamente
come Notizie/Categoria, ma **non emetteva `rel="prev"`/`rel="next"`** —
la stessa classe di lacuna SEO già trovata e corretta su quelle due
superfici, mai controllata qui perché la pagina autore è fuori dal
perimetro dei 7 "canonical surfaces". Corretto con la stessa convenzione
di canonical già in uso in questa vista (mai `?page=1` esplicito). Nuovo
test: `PublicAuthorPageEligibilityTest::test_author_page_exposes_rel_prev_next_across_pages`.

Verificata anche la struttura heading della pagina autore (mai controllata
prima): `<h1>` (nome autore) → `<h2>` ("Articoli pubblicati") → `<h3>`
(titolo di ciascun articolo in lista) — nessun salto, nessuna correzione
necessaria.

## Cosa questo audit non ha fatto

- Non ha ri-verificato da zero `docs/PUBLIC_TRUST_LAYER_AUDIT.md`/`_QA.md`/
  `_INVARIANTS.md` (confermati non-stale per data commit, non
  ri-eseguiti riga per riga).
- Non ha toccato alcun articolo pubblicato/programmato, alcuna migration,
  alcun dato editoriale.
- Non ha esteso il perimetro a `turing` (esplicitamente fuori scope, come
  per il Measurement Closeout).

## Esito

Un solo gap reale trovato e corretto (pagination SEO sulla pagina autore),
coerente con la stessa classe di lacuna già chiusa su Notizie/Categoria.
Nessuna regressione di sicurezza o di dati trovata in Fonti primarie/
Trust Layer. Nessuna PR aperta.
