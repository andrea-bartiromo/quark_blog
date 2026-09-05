<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;

class AuthorController extends Controller
{
    public function show(User $user)
    {
        $articles = Article::where('user_id', $user->id)
            ->published()
            ->paginate(12);

        // Trust Layer V1 — eleggibilità pubblica: prima di questa modifica
        // qualunque User esistente (incl. un account admin mai pensato per
        // essere pubblico) era raggiungibile via /autore/{id}, confermando
        // la sua esistenza anche senza alcun articolo pubblicato. L'unico
        // segnale disponibile senza inventare un campo nuovo è "ha almeno
        // un articolo pubblicato" — un utente senza articoli pubblici
        // riceve un 404 comune, indistinguibile da uno slug inesistente.
        abort_unless($articles->total() > 0, 404);

        return view('autore', [
            'author' => $user,
            'articles' => $articles,

            // DB-first (stessa fonte di Category::options() usata
            // altrove): il badge categoria sotto ogni articolo deve
            // riconoscere anche una categoria creata dall'admin dopo il
            // deploy, non solo lo snapshot statico di config().
            'categoryOptions' => Category::options(false),
        ]);
    }
}
