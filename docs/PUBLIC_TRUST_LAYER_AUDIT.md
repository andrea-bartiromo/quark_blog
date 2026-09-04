# Trust Layer pubblico — Audit (Cantiere H, Prompt 183)

Perimetro di questo cantiere: la pagina **articolo** (`articolo.blade.php`
e le sue partial), unica superficie tra le sette del progetto che porta
attributi di fiducia editoriale. `/autore/{user}` **non è** tra le sette
superfici elencate nel Cantiere I (home, articolo, Notizie, categoria,
Percorsi index, Percorso detail, ricerca) — resta fuori perimetro,
mai toccata da questa sequenza di cantieri (già nell'elenco vietato del
test di isolamento sin dal Cantiere B).

## Dati reali già disponibili (da vestire, non da inventare)

| Attributo | Fonte | Renderizzato oggi | Componente Kairus |
|---|---|---|---|
| Nome autore | `Article::author->name` (`User`) | Sì (hero, author-card, JSON-LD `Person`) | `x-kairus.article-meta`, author-card (Cantiere E) |
| Foto autore | `User::photo` | Sì (author-card, fallback iniziali) | author-card (Cantiere E) |
| **Bio autore** | `User::bio` | **No** — colonna esiste, mai letta da `articolo.blade.php`/`author-card.blade.php` | Da aggiungere in questo cantiere (dato reale, nessuna query nuova: `$article->author` è già eager-loaded) |
| **Twitter/LinkedIn autore** | `User::twitter`/`linkedin` | **No** | Da aggiungere in questo cantiere, solo se valorizzati |
| Data pubblicazione | `Article::published_at` | Sì (hero, JSON-LD `datePublished`) | `x-kairus.article-meta` (Cantiere E) |
| Data aggiornamento | `Article::updated_at` | **No, deliberatamente** | Vedi invarianti — `updated_at` non è un segnale editoriale affidabile |
| Fonti | Testo libero, `explode('---', body)` | Sì | `x-kairus.trust-panel` (Cantiere E) |
| `primary_sources` (colonna strutturata) | `Article::primary_sources` | **No** (non renderizzata pubblicamente in questa catena di branch) | Non toccata — vedi sovrapposizione sotto |
| Credito/didascalia/fonte/licenza cover | `Article::cover_caption/credit/source/source_url/license` | Sì (`<details class="cover-info">` in `body.blade.php`) | Da rifinire con token in questo cantiere |
| Revisioni editoriali | `Article::revisions()` (`ArticleRevision`) | **No** — solo admin (`Admin\ArticleController`), mai interrogata da `ArticleController::show()` | Non renderizzabile senza una nuova query — fuori perimetro "solo Blade+CSS", escluso |
| Metodologia pubblica | — | **Nessuna route esiste** (`/metodologia` non è definita in `routes/web.php`) | Escluso — nessun contenuto/route reale da collegare |
| Disclosure (affiliazioni/sponsorizzazioni/AI) | — | **Nessun campo trovato** in `Article`/`User` (`grep -ri "sponsor\|disclosure\|affiliat"` senza risultati) | Escluso — nessun dato da presentare |
| Dati strutturati autore (`Person`) | JSON-LD in `structured-data.blade.php` | Sì, minimale (`name`+`url`, mai `sameAs`/credenziali) | Invariato — certificato da `ArticleStructuredDataTest::test_author_is_a_minimal_person_without_image_or_sameas` |

## Sovrapposizione — branch Trust Layer non mergiati (confermata, non cambiata dal Cantiere E)

Come già documentato in `docs/ARTICLE_REFRESH_FILEMAP.md` (Cantiere E):
`feat/public-article-sources-v1` (#516, fonti strutturate),
`feat/public-article-revision-transparency-v1` (#517, "aggiornato il..."
+ `dateModified`), `feat/public-author-pages-v1` (#518, pagina autore),
`feat/trust-policy-pages-v1` (#519, `/metodologia`) esistono con codice
reale ma **solo su branch non mergiati**, assenti da questa catena
`feat/kairus-*`. Nessun merge/rebase/cherry-pick eseguito (vietato).
Questo cantiere adotta i componenti Kairus sui dati Trust Layer
**realmente disponibili in questa catena di branch oggi** — non
anticipa né duplica quel lavoro.

## Divieto di invenzione (Prompt 184)

Nessuna vista di questo cantiere mostrerà: revisioni non tracciate
pubblicamente, verifiche/certificazioni non esistenti, qualifiche
professionali non registrate (il campo `User::role` è un permesso
interno — editor/autore — mai un titolo professionale pubblico: non
verrà mai renderizzato come tale), fonti non presenti nel testo
dell'articolo, disclosure non supportate da un campo reale.
