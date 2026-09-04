# Trust Layer pubblico — Invarianti (Cantiere H, Prompt 184)

Nessuna vista pubblica di Kairus può mostrare revisioni, verifiche,
qualifiche, fonti o disclosure **non sostenute da un dato reale
persistito o da una regola già esistente e verificata**. Ogni claim
pubblico di questo cantiere deve poter rispondere alla domanda "da
quale colonna/relazione viene questo testo?" con una risposta concreta
— mai "è plausibile", mai "lo aggiungiamo per completezza".

## Regole concrete

1. **Bio autore**: renderizzata solo se `User::bio` è valorizzato
   (`filled($article->author->bio)`); mai un testo segnaposto tipo
   "Scrive di scienza per Kairus" se il campo è vuoto.
2. **Social autore**: link Twitter/LinkedIn renderizzati solo se il
   relativo campo (`User::twitter`/`linkedin`) è valorizzato, ciascuno
   indipendentemente dall'altro.
3. **`User::role`** non è mai renderizzato come titolo professionale
   pubblico — è un permesso interno (`editor`/`author`/...), non un
   dato editoriale.
4. **Data di aggiornamento**: mai mostrata. `Article::updated_at` è
   toccata anche da `$article->increment('views')` (ogni singola
   visualizzazione) e dal salvataggio del flusso di verifica editoriale
   (`verification_status`/`verified_at`/`verified_by`) senza alcuna
   modifica al contenuto — non è un segnale editoriale affidabile.
   Stessa motivazione già documentata per `article:modified_time`
   (meta tag) e `dateModified` (JSON-LD) in
   `docs/ARTICLE_REFRESH_INVARIANTS.md` (Cantiere E) — questo cantiere
   la riconferma, non la cambia.
5. **Revisioni pubbliche**: non introdotte. `ArticleController::show()`
   non interroga `Article::revisions()` oggi — aggiungerlo
   richiederebbe una query nuova, fuori dal perimetro "solo Blade+CSS"
   di questo cantiere quando la superficie può essere completata senza.
6. **Metodologia pubblica**: non collegata. Nessuna route `/metodologia`
   esiste in questa catena di branch.
7. **Disclosure** (affiliazioni/sponsorizzazioni/contenuto generato da
   AI): non introdotte. Nessun campo reale esiste in `Article`/`User`
   per rappresentarle.
8. **Fonti**: solo il pannello testuale legacy (`explode('---', body)`),
   mai `primary_sources` (non renderizzato pubblicamente in questa
   catena — vedi sovrapposizione in `PUBLIC_TRUST_LAYER_AUDIT.md`).
9. **Link esterni** (fonti, social autore): `target="_blank"` sempre
   accompagnato da `rel="noopener noreferrer"` — nessuna eccezione.
10. **Dati strutturati**: `Person` dell'autore resta minimale
    (`name`+`url`), mai `sameAs`/`jobTitle`/credenziali aggiunte senza
    un campo reale a supportarle. `datePublished` invariato, mai
    `dateModified` (vedi punto 4).
11. **Privacy**: mai esposti — ID interni (`user_id` numerico grezzo),
    email dell'autore, note di revisione interne
    (`verification_notes`), stato di verifica (`verification_status`),
    snapshot di revisione, `verified_by`.
