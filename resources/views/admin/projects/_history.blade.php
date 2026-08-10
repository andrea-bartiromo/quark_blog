<h3 style="margin-top:0;">Cronologia</h3>

@if($activityLog->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">🕐</div>
    <p class="project-empty-state__text">Nessuna attività registrata ancora. Ogni modifica al progetto (stato, task, documenti, decisioni) comparirà qui.</p>
  </div>
@else
  <div class="admin-card">
    @foreach($activityLog as $log)
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:.75rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
        <div>
          <div style="font-weight:700;">{{ $log->action }}</div>
          <div style="font-size:.78rem;color:#6b7280;margin-top:.15rem;">
            {{ $log->user?->name ?? 'Sistema' }} · {{ match($log->source) {
              \App\Models\ProjectActivityLog::SOURCE_EDITORIAL_SYNC => 'Sync calendario',
              \App\Models\ProjectActivityLog::SOURCE_GITHUB => 'GitHub',
              \App\Models\ProjectActivityLog::SOURCE_SYSTEM => 'Automatico',
              default => 'Manuale',
            } }} · {{ $log->subject_title }}
          </div>
        </div>
        <div style="font-size:.72rem;color:#9ca3af;white-space:nowrap;">{{ $log->created_at->format('d/m/Y H:i') }}</div>
      </div>
    @endforeach
  </div>

  {{ $activityLog->links() }}
@endif
