<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE 11, missione Dashboard Automation V2: la dashboard e la pagina
 * progetto compongono più segnali derivati (ProjectNextActionResolverV2,
 * ProjectHealthResolver, EditorialCalendarReconciliationService) per un
 * insieme di progetti — un dataset realistico (10 progetti, ~50 articoli,
 * decine di task, dipendenze, un calendario editoriale, un log corposo)
 * verifica che il costo resti limitato e non cresca in modo incontrollato,
 * non che sia pari a zero: un numero fisso di query per un insieme
 * DELIBERATAMENTE LIMITATO di progetti (vedi
 * ProjectDashboardController::attentionItems()) non è un N+1 nel senso
 * classico (crescita con l'intera tabella), è un costo bounded — la
 * soglia qui è una rete di sicurezza contro regressioni future, non un
 * obiettivo "zero query aggiuntive".
 */
class ProjectDashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDays(random_int(1, 30)),
        ], $overrides));
    }

    private function seedRealisticDataset(): void
    {
        for ($a = 0; $a < 50; $a++) {
            $this->article();
        }

        for ($p = 0; $p < 10; $p++) {
            $project = Project::factory()->create([
                'operational_status' => $p % 4 === 0 ? Project::STATUS_BLOCKED : Project::STATUS_IN_PROGRESS,
            ]);

            $previousTask = null;
            for ($t = 0; $t < 6; $t++) {
                $task = ProjectTask::factory()->for($project)->create([
                    'manual_status' => match (true) {
                        $t === 0 => ProjectTask::STATUS_TODO,
                        $t === 1 => ProjectTask::STATUS_IN_PROGRESS,
                        default => ProjectTask::STATUS_COMPLETED,
                    },
                    'due_date' => $t === 0 ? now()->subDay() : ($t === 1 ? now()->addDays(2) : null),
                    // Dipendenza valida (stesso progetto, mai un ciclo):
                    // ogni task dipende dalla precedente dello stesso
                    // progetto, verificando che ProjectTask::
                    // guardAgainstInvalidDependency() non rallenti in modo
                    // percepibile un dataset con molte dipendenze reali.
                    'depends_on_task_id' => $previousTask?->id,
                ]);
                $previousTask = $task;
            }

            ProjectTask::factory()->development()->for($project)->create([
                'manual_status' => ProjectTask::STATUS_BLOCKED,
                'derived_status' => ProjectTask::DERIVED_GH_PR_MERGED,
                'status_source' => ProjectTask::SOURCE_DERIVED,
                'github_pr_number' => 100 + $p,
            ]);

            for ($l = 0; $l < 5; $l++) {
                ProjectActivityLog::record($project, 'project', $project->id, $project->title, "Evento di prova {$l}", null, source: ProjectActivityLog::SOURCE_SYSTEM);
            }
        }

        $calendarProject = Project::factory()->create(['operational_status' => Project::STATUS_IN_PROGRESS]);
        $articleForCalendar = $this->article(['title' => 'Titolo pianificato collegato']);
        ProjectDocument::factory()->create([
            'project_id' => $calendarProject->id,
            'is_editorial_calendar' => true,
            'content' => "28/08/2026 — Titolo pianificato collegato\n29/08/2026 — Titolo ancora da scrivere\n",
        ]);
        $calendarProject->articles()->attach($articleForCalendar->id);
    }

    public function test_the_dashboard_query_count_stays_bounded_on_a_realistic_dataset(): void
    {
        $this->seedRealisticDataset();

        DB::enableQueryLog();
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThan(
            120,
            $queryCount,
            "La dashboard ha eseguito {$queryCount} query su un dataset di 11 progetti — soglia di sicurezza contro una regressione N+1 futura."
        );
    }

    public function test_a_single_project_page_query_count_stays_small_regardless_of_dataset_size(): void
    {
        $this->seedRealisticDataset();
        $project = Project::first();

        DB::enableQueryLog();
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThan(
            30,
            $queryCount,
            "La pagina di un singolo progetto ha eseguito {$queryCount} query — indipendente dal numero totale di progetti nel database."
        );
    }

    /**
     * Percorso più costoso: un progetto con calendario editoriale carica
     * ProjectNextActionResolverV2 / ProjectHealth su OGNI tab (non solo la
     * panoramica, per il badge sempre visibile — vedi ProjectController::
     * show()), che a sua volta esegue una riconciliazione completa del
     * calendario (Article::all() su tutto il catalogo). Soglia più larga
     * di proposito: verificato a parte per non nascondere questo costo
     * dentro la soglia generica sopra.
     */
    public function test_a_calendar_linked_project_page_stays_bounded_on_any_tab(): void
    {
        $this->seedRealisticDataset();
        $calendarProject = Project::whereHas('documents', fn ($q) => $q->where('is_editorial_calendar', true))->firstOrFail();

        DB::enableQueryLog();
        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$calendarProject, 'tab' => 'tasks']));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertLessThan(
            60,
            $queryCount,
            "La pagina di un progetto con calendario (tab non-overview) ha eseguito {$queryCount} query."
        );
    }
}
