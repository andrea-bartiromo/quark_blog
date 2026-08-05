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
        ]);
    }
}
