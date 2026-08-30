<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateArticleSourcesRequest;
use App\Models\Article;
use App\Services\EditorialSources\ArticleSourceService;
use Illuminate\Http\RedirectResponse;

/**
 * EDITORIAL TRUST (Missione 27) — salvataggio dell'editor delle fonti,
 * ospitato nella pagina di modifica articolo ma con una rotta e un form
 * propri.
 *
 * Perché NON dentro UpdateArticleRequest / ArticleController::update():
 * un errore di validazione su una fonte non deve poter far ricaricare il
 * form articolo perdendo il corpo appena scritto (o azzerando l'editor
 * TinyMCE). Form separato = ogni superficie fallisce da sola, e il
 * requisito "nessuna perdita del contenuto esistente" è garantito dalla
 * struttura, non dalla disciplina di chi scrive il prossimo commit.
 *
 * Autorizzazione: nessun controllo per-articolo oltre al middleware
 * ['auth','editor'] già applicato all'intero gruppo admin.* — stesso
 * ragionamento già documentato per Admin\ArticleRevisionController.
 */
class ArticleSourceController extends Controller
{
    public function __construct(
        private readonly ArticleSourceService $sourceService,
    ) {}

    public function update(UpdateArticleSourcesRequest $request, Article $article): RedirectResponse
    {
        $rows = array_values((array) $request->validated('sources', []));

        // Il warning duplicati si calcola sul payload PRIMA del salvataggio
        // (stesse righe che l'utente ha appena inviato) e non blocca nulla:
        // vedi ArticleSourceService::duplicateWarnings().
        $duplicates = $this->sourceService->duplicateWarnings($rows);

        $count = $this->sourceService->sync($article, $rows);

        $message = $count === 0
            ? 'Fonti aggiornate: nessuna fonte collegata a questo articolo.'
            : 'Fonti aggiornate: '.$count.' '.($count === 1 ? 'fonte collegata' : 'fonti collegate').'.';

        $flash = ['success' => $message];

        if ($duplicates !== []) {
            // Chiave 'warning': il banner è già gestito da layouts/admin,
            // nessun canale di notifica nuovo solo per questa schermata.
            $flash['warning'] = 'Attenzione: alcune fonti puntano allo stesso riferimento ('
                .implode(', ', array_map(fn ($t) => '"'.$t.'"', $duplicates))
                .'). Le fonti sono state salvate comunque: verifica se la ripetizione è voluta.';
        }

        return redirect()
            ->route('admin.articles.edit', $article)
            // Ancora sulla sezione fonti: dopo il redirect l'utente deve
            // ritrovarsi dove stava lavorando, non in cima a un form lungo.
            ->withFragment('fonti-articolo')
            ->with($flash);
    }
}
