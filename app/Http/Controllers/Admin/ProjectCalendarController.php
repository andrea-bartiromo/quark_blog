<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProjectCalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::createFromFormat('Y-m', $request->string('month')->value() ?: now()->format('Y-m'))->startOfMonth();
        $rangeStart = $month->clone()->startOfMonth()->toDateString();
        $rangeEnd = $month->clone()->endOfMonth()->toDateString();

        $tasks = ProjectTask::query()
            ->with('project')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$rangeStart, $rangeEnd])
            ->get()
            ->groupBy(fn (ProjectTask $task) => $task->due_date->toDateString());

        // Sola lettura: gli articoli programmati/pubblicati collegati a un
        // progetto alimentano il calendario con la loro data editoriale
        // (published_at), senza introdurre alcun nuovo collegamento — usa
        // project_article esattamente come già esiste (Blocco F).
        //
        // published_at è memorizzato in UTC: un articolo delle 00:30 ora di
        // Roma del giorno 1 è salvato come le 22:30/23:30 UTC del giorno
        // precedente (a seconda dell'ora legale). Confrontare/raggruppare
        // sulla data UTC grezza lo collocherebbe nel giorno (o mese) sbagliato
        // rispetto a come lo vede la redazione — stesso fuso già usato altrove
        // per gli articoli (Article::publishedAtForEditors()).
        $monthRangeStartUtc = Article::scheduledAtFromEditorialInput($rangeStart, '00:00');
        $monthRangeEndUtc = Article::scheduledAtFromEditorialInput($month->clone()->endOfMonth()->addDay()->toDateString(), '00:00');

        $articles = Article::query()
            ->whereIn('status', [Article::STATUS_SCHEDULED, Article::STATUS_PUBLISHED])
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $monthRangeStartUtc)
            ->where('published_at', '<', $monthRangeEndUtc)
            ->whereHas('projects')
            ->with('projects:id,title')
            ->get()
            ->groupBy(fn (Article $article) => $article->publishedAtForEditors()->toDateString());

        $weeks = $this->buildWeeks($month, $tasks, $articles);

        return view('admin.projects.calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'prevMonth' => $month->clone()->subMonth()->format('Y-m'),
            'nextMonth' => $month->clone()->addMonth()->format('Y-m'),
        ]);
    }

    private function buildWeeks(Carbon $month, $tasksByDate, $articlesByDate): array
    {
        $start = $month->clone()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->clone()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $week = [];
        $cursor = $start->clone();

        while ($cursor->lte($end)) {
            $week[] = [
                'date' => $cursor->clone(),
                'inMonth' => $cursor->month === $month->month,
                'isToday' => $cursor->isToday(),
                'tasks' => $tasksByDate->get($cursor->toDateString(), collect()),
                'articles' => $articlesByDate->get($cursor->toDateString(), collect()),
            ];

            if ($cursor->dayOfWeekIso === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        if (! empty($week)) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
