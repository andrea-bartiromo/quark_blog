<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProjectCalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::createFromFormat('Y-m', $request->string('month')->value() ?: now()->format('Y-m'))->startOfMonth();

        $tasks = ProjectTask::query()
            ->with('project')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$month->clone()->startOfMonth()->toDateString(), $month->clone()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy(fn (ProjectTask $task) => $task->due_date->toDateString());

        $weeks = $this->buildWeeks($month, $tasks);

        return view('admin.projects.calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'prevMonth' => $month->clone()->subMonth()->format('Y-m'),
            'nextMonth' => $month->clone()->addMonth()->format('Y-m'),
        ]);
    }

    private function buildWeeks(Carbon $month, $tasksByDate): array
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
