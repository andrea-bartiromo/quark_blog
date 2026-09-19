<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EditorialOperations\CategoryCommandCenterService;

/**
 * Cantiere 54 (programma "100 cantieri Kairus"): pagina admin di sola
 * lettura, stesso pattern di Admin\EditorialOperationsDashboardController
 * (Mission 09) — riassume in card ciò che richiede attenzione per
 * ciascuna categoria, riusando esclusivamente servizi già esistenti.
 * Nessuna azione di correzione automatica: solo card riassuntive con
 * link di drill-down verso l'editor categoria/articolo già esistenti.
 */
class CategoryCommandCenterController extends Controller
{
    public function __construct(private readonly CategoryCommandCenterService $commandCenter) {}

    public function index()
    {
        return view('admin.category-command-center', [
            'snapshot' => $this->commandCenter->snapshot(),
        ]);
    }
}
