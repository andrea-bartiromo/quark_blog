<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;

class ProjectDashboardController extends Controller
{
    /**
     * Pagina principale dell'Area Progettazione: solo liste, nessun grafico
     * decorativo. Sette liste — capire a colpo d'occhio cosa si sta facendo,
     * cosa e' bloccato, cosa deve avvenire dopo, cosa verra' pubblicato.
     */
    public function index()
    {
        return view('admin.projects.dashboard', [
            // Correzione #2 approvata in revisione: ordina per severita'
            // reale (critical > high > medium > low) invece che
            // alfabeticamente — vedi Project::scopeOrderByPrioritySeverity().
            'activeProjects' => Project::active()->with('responsible')->orderByPrioritySeverity()->limit(10)->get(),
            'blockedProjects' => Project::blocked()->with('responsible')->orderByDesc('updated_at')->get(),
            'dueSoonTasks' => ProjectTask::dueSoon()->with('project')->orderBy('due_date')->limit(10)->get(),
            'overdueTasks' => ProjectTask::overdue()->with('project')->orderBy('due_date')->limit(10)->get(),
            'upcomingPublications' => ProjectTask::publicationType()
                ->where('derived_status', ProjectTask::DERIVED_SCHEDULED)
                ->with(['project', 'article'])
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'recentDocuments' => ProjectDocument::with('project')->orderByDesc('updated_at')->limit(10)->get(),
            'lastActivity' => ProjectActivityLog::with(['project', 'user'])->orderByDesc('created_at')->limit(15)->get(),
            'priorityNextActions' => Project::highPriority()->active()->whereNotNull('next_action')->with('responsible')->orderByPrioritySeverity()->limit(10)->get(),
        ]);
    }
}
