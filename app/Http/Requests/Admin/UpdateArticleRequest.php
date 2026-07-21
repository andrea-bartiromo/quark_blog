<?php

namespace App\Http\Requests\Admin;

/**
 * Le regole di validazione per la creazione e l'aggiornamento di un
 * articolo Admin sono identiche (il controller originale usava un unico
 * metodo condiviso per entrambe le azioni): UpdateArticleRequest eredita
 * `rules()` e `authorize()` da StoreArticleRequest senza ridefinirli,
 * cosi che le regole abbiano un'unica fonte per quest'area.
 */
class UpdateArticleRequest extends StoreArticleRequest {}
