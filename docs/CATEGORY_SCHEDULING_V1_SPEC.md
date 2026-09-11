# Category Scheduling V1 — pianificazione categorie (Prompt 1-5)

## Stato implementato

`Category` ha ora `status` (`draft|scheduled|published`, default `published`) e `published_at` (nullable, UTC storage, editing/display Europe/Rome), aggiunti dalla migration `2026_09_11_090000_add_scheduled_publication_to_categories_table`. Le 7 categorie pre-esistenti sono state backfillate a `status=published` con `published_at = created_at`: restano pubbliche esattamente come prima (nessuna regressione, vedi `CategorySourceDbFirstTest::test_all_six_original_categories_remain_visible_everywhere_after_the_db_first_switch`).

## Contratto unico di visibilità pubblica

`Category::scopePubliclyVisible()` / `Category::isPubliclyVisible()` (mirror del pattern già in produzione per `ContentCluster`):

- `is_active = false` → mai pubblica, qualunque `status`;
- `status = draft` → mai pubblica (`published_at` sempre `null`, imposto dal `booted()` hook);
- `status = scheduled` + `published_at > now()` → non ancora pubblica;
- `status = scheduled` + `published_at <= now()` → pubblica (istante esatto incluso, confine `<=`);
- `status = published` → sempre pubblica, indipendentemente da `published_at` (che resta comunque popolato per il `lastmod` di sitemap).

Copertura test: `tests/Feature/CategoryScheduledPublicationTest.php` (le 5 combinazioni richieste, inclusi il confine esatto e la conversione DST-safe già validata per `ContentCluster`).

## Superfici pubbliche coperte da `publiclyVisible()`/`publicOptions()`

- `ArticleController::category()` — 404 esplicito su categoria non pubblica;
- header/category-bar/sidebar/footer (composer condiviso in `AppServiceProvider`);
- home (`HomeController`) e `/notizie` (pill-row);
- `SearchController` (dropdown filtro);
- `TrovaEntitySearchService::searchCategories()`;
- `SeoController::sitemap()` — esclusione URL + `lastmod` da `published_at` (segnale editoriale affidabile, mai `updated_at`);
- breadcrumb visibile e `BreadcrumbList` JSON-LD di un articolo già pubblicato (`ArticleController::show()` calcola `categoryPubliclyVisible`, consumato da `articles/partials/breadcrumb.blade.php` e `articles/partials/structured-data.blade.php`);
- hero dell'articolo (`articles/partials/hero.blade.php`): il kicker categoria diventa testo semplice, non più un link, quando la categoria non è pubblica;
- `ArticleDiscoveryAuditService`: una categoria bozza/programmata non conta come percorso di discovery valido per gli articoli che la usano.

Restano deliberatamente su `options(false)`/query non filtrata (solo lookup di etichetta storica, mai un link): badge su contenuto già pubblicato, `Admin\StatsController`, HTML newsletter, `AuthorController`, `ContentClusterController` (label), `SeoController::feed()`/`newsSitemap()` (categoria come testo `<news:genres>`/`<dc:creator>` di un articolo già pubblico, non come URL), `EditorialQualityChecker`. `Category::options()` (form editoriali) resta invariato: una categoria bozza/programmata deve restare selezionabile in anticipo.

## Admin — pubblicazione (Prompt 3)

`Admin\CategoryController` espone `status` + `scheduled_date`/`scheduled_time` (input Europe/Rome, convertiti in UTC via `Category::scheduledAtFromEditorialInput()`, stesso pattern di `Article`/`ContentCluster`). Regola di `published_at` quando lo status diventa/resta `published`:

- nuova creazione, o transizione da bozza/programmata → `published_at = now()` (pubblica subito);
- salvataggio che lascia `published` invariato → `published_at` **non** si sposta mai in avanti, per non invalidare il `lastmod` di sitemap ad ogni piccola modifica editoriale.

L'editor di categoria mostra un'anteprima di sola lettura (stato effettivo, data Europe/Rome, URL pubblico reale o non-ancora-raggiungibile, checklist di readiness) — non pubblica né modifica mai automaticamente la categoria o gli articoli collegati. Copertura: `tests/Feature/Admin/CategoryAdminSchedulingTest.php`.

## Readiness audit (Prompt 4)

`App\Services\CategoryPublicationReadiness` (riusato sia dall'anteprima admin sia dal comando) segnala, senza mai bloccare: descrizione/immagine/colore mancanti, nessun articolo pubblicato/programmato assegnato, nessun Percorso collegato. Comando Artisan di sola lettura: `php artisan category:publication-readiness` (opzione `--json`), limitato alle categorie `status=scheduled`. Copertura: `tests/Feature/CategoryPublicationReadinessTest.php`.

## Non-esposizione (Prompt 2)

Suite dedicata `tests/Feature/CategoryScheduledPublicationTest.php`: route categoria (404), header/footer/category-bar, sidebar, home, `/notizie`, dropdown ricerca, TROVA, sitemap (esclusione + `lastmod` sulla categoria pubblica), breadcrumb/JSON-LD (nessun link verso una categoria non pubblica su un articolo già pubblicato, mentre una categoria pubblica resta linkata), selezionabilità nei form editoriali (`Category::options()` invariato vs `publicOptions()` filtrato).
