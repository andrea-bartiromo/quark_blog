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

        return view('autore', [
            'author' => $user,
            'articles' => $articles,

            // DB-first (stessa fonte di Category::options() usata
            // altrove): il badge categoria sotto ogni articolo deve
            // riconoscere anche una categoria creata dall'admin dopo il
            // deploy, non solo lo snapshot statico di config().
            'categoryOptions' => Category::options(false),

            // EDITORIAL TRUST (Missione 24) — "NON pubblicare profili
            // privi di contenuto sufficiente". Prima di questa modifica
            // OGNI /autore/{user} era indicizzabile (robots di default
            // ereditato dal layout), incluso un account editor/admin
            // appena creato senza ancora un solo articolo pubblicato e
            // senza bio: una pagina vuota, indicizzabile, con solo il
            // titolo. "Sottile" richiede il fallimento SIA della
            // dimensione "contenuti" (nessun articolo pubblicato) SIA
            // della dimensione "profilo" (nessuna bio): un profilo con
            // una bio reale ma senza ancora articoli propri (es. un
            // editor il cui lavoro è la revisione, non la scrittura) ha
            // comunque un contenuto genuino da indicizzare.
            'isThinAuthorProfile' => $articles->total() === 0 && blank($user->bio),
        ]);
    }
}
