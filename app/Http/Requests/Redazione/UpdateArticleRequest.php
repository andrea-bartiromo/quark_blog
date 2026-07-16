<?php

namespace App\Http\Requests\Redazione;

use App\Models\Article;

/**
 * Le regole di validazione per la creazione e l'aggiornamento di un
 * articolo Redazione sono identiche (il controller originale le
 * duplicava carattere per carattere tra store() e update()):
 * UpdateArticleRequest eredita `rules()` da StoreArticleRequest, cosi
 * che le regole abbiano un'unica fonte per quest'area, e ridefinisce
 * solamente `authorize()` per il vincolo reale (solo l'autore
 * proprietario può modificare, e non se l'articolo è già pubblicato)
 * che nel controller originale non esisteva in store().
 */
class UpdateArticleRequest extends StoreArticleRequest
{
    /**
     * Replica esattamente i due controlli che il controller eseguiva prima
     * di validare (solo l'autore proprietario può modificare, e non se
     * l'articolo è già pubblicato), cosi che l'esito e l'ordine restino
     * identici a quelli precedenti: l'autorizzazione viene verificata prima
     * della validazione, esattamente come i due `abort(403)` originali.
     */
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            && $article->user_id === auth()->id()
            && $article->status !== 'published';
    }
}
