<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EditorialOperations\EditorialOperationsDashboardService;

/**
 * Editorial Operations Dashboard V1 (Mission 09) — un'unica pagina admin
 * read-only che riassume in card ciò che richiede attenzione editoriale
 * ADESSO, riusando esclusivamente audit/servizi già esistenti (mai una
 * seconda implementazione di una regola già espressa altrove). Nessuna
 * azione di correzione automatica: solo card riassuntive con link di
 * drill-down verso gli strumenti che già permettono di intervenire
 * (calendario articoli, editor articolo, editor Percorso).
 */
class EditorialOperationsDashboardController extends Controller
{
    public function __construct(private readonly EditorialOperationsDashboardService $dashboard) {}

    public function index()
    {
        return view('admin.editorial-operations-dashboard', [
            'snapshot' => $this->dashboard->snapshot(),
        ]);
    }
}
