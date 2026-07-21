<?php

/**
 * Il Laboratorio — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://www.illaboratorio.it
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\User;

class AuthorController extends Controller
{
    public function show(User $user)
    {
        $articles = Article::where('user_id', $user->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('autore', [
            'author' => $user,
            'articles' => $articles,
        ]);
    }
}
