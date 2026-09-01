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
            'isThinAuthorProfile' => $articles->total() === 0 && blank($user->bio),

            // DB-first (stessa fonte di Category::options() usata
            // altrove): il badge categoria sotto ogni articolo deve
            // riconoscere anche una categoria creata dall'admin dopo il
            // deploy, non solo lo snapshot statico di config().
            'categoryOptions' => Category::options(false),
        ]);
    }
}
