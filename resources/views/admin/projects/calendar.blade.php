@extends('layouts.admin')
@section('title','Calendario — Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Calendario — {{ $month->translatedFormat('F Y') }}</h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a href="{{ route('admin.progettazione.calendar', ['month' => $prevMonth]) }}" class="btn btn--secondary">← Precedente</a>
    <a href="{{ route('admin.progettazione.calendar', ['month' => now()->format('Y-m')]) }}" class="btn btn--secondary">Oggi</a>
    <a href="{{ route('admin.progettazione.calendar', ['month' => $nextMonth]) }}" class="btn btn--secondary">Successivo →</a>
    <a href="{{ route('admin.progettazione.tasks.create-pick-project') }}" class="btn btn--primary">+ Nuova attività</a>
  </div>
</div>

<div class="admin-card" style="padding:0;overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
    <thead>
      <tr>
        @foreach(['Lun','Mar','Mer','Gio','Ven','Sab','Dom'] as $day)
          <th style="padding:.6rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;border-bottom:1px solid #e5e7eb;">{{ $day }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($weeks as $week)
        <tr>
          @foreach($week as $day)
            <td style="vertical-align:top;height:110px;padding:.4rem;border:1px solid #f1f5f9;
                       background:{{ $day['isToday'] ? '#f0fdfa' : ($day['inMonth'] ? '#fff' : '#fafafa') }};">
              <div style="font-size:.75rem;font-weight:{{ $day['isToday'] ? '800' : '600' }};color:{{ $day['inMonth'] ? '#111827' : '#d1d5db' }};margin-bottom:.3rem;">
                {{ $day['date']->day }}
              </div>
              @foreach($day['tasks']->take(3) as $task)
                <a href="{{ route('admin.progettazione.projects.tasks.edit', [$task->project, $task]) }}"
                   title="{{ $task->title }} — {{ $task->project->title }}"
                   style="display:block;font-size:.68rem;padding:.15rem .35rem;margin-bottom:.2rem;border-radius:5px;
                          background:{{ $task->derived_status === 'invalid_link' ? '#fee2e2' : '#e0e7ff' }};
                          color:{{ $task->derived_status === 'invalid_link' ? '#991b1b' : '#3730a3' }};
                          text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  {{ $task->due_time ? $task->due_time->format('H:i').' ' : '' }}{{ $task->title }}
                </a>
              @endforeach
              @if($day['tasks']->count() > 3)
                <span style="font-size:.65rem;color:#9ca3af;">+{{ $day['tasks']->count() - 3 }} altre</span>
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endsection
