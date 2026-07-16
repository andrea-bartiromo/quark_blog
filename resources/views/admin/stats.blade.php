@extends('layouts.admin')
@section('title', 'Statistiche')

@section('content')

<style>
  .stats-page {
    --card-bg: #ffffff;
    --card-border: #e5e7eb;
    --text-main: #111827;
    --text-muted: #6b7280;
    --soft-bg: #f8fafc;
    --accent: #0d9488;
    --accent-soft: #ccfbf1;
    --danger: #ef4444;
  }

  @media (prefers-color-scheme: dark) {
    .stats-page {
      --card-bg: #111827;
      --card-border: #1f2937;
      --text-main: #f9fafb;
      --text-muted: #9ca3af;
      --soft-bg: #0f172a;
      --accent-soft: rgba(13, 148, 136, .16);
    }
  }

  .dark .stats-page,
  body.dark .stats-page {
    --card-bg: #111827;
    --card-border: #1f2937;
    --text-main: #f9fafb;
    --text-muted: #9ca3af;
    --soft-bg: #0f172a;
    --accent-soft: rgba(13, 148, 136, .16);
  }

  .stats-page {
    color: var(--text-main);
  }

  .stats-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1.5rem;
  }

  .stats-eyebrow {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: var(--accent);
    margin-bottom: .35rem;
  }

  .stats-subtitle {
    font-size: .85rem;
    color: var(--text-muted);
    margin-top: .25rem;
  }

  .stats-live {
    background: var(--accent-soft);
    color: var(--accent);
    border: 1px solid rgba(13, 148, 136, .22);
    border-radius: 999px;
    padding: .45rem .75rem;
    font-size: .75rem;
    font-weight: 800;
    white-space: nowrap;
  }

  .stats-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .stats-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
    padding: 1.25rem;
  }

  .stats-kpi-label {
    font-size: .72rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .1em;
  }

  .stats-kpi-value {
    margin-top: .6rem;
    font-size: 1.75rem;
    font-weight: 950;
    color: var(--text-main);
    line-height: 1;
  }

  .stats-kpi-note {
    margin-top: .5rem;
    font-size: .78rem;
    color: var(--text-muted);
  }

  .stats-chart-grid {
    display: grid;
    grid-template-columns: 1.35fr .9fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .stats-chart-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .stats-card-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1rem;
  }

  .stats-card-title h2 {
    font-size: .82rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--text-muted);
    margin: 0;
  }

  .stats-badge {
    background: var(--soft-bg);
    border: 1px solid var(--card-border);
    color: var(--text-muted);
    border-radius: 999px;
    padding: .32rem .6rem;
    font-size: .7rem;
    font-weight: 800;
  }

  .stats-chart-box {
    position: relative;
    height: 320px;
  }

  .stats-chart-box.small {
    height: 280px;
  }

  .stats-table-wrap {
    overflow-x: auto;
  }

  .stats-empty {
    text-align: center;
    color: var(--text-muted);
    font-size: .85rem;
    padding: 2rem 1rem;
  }

  .stats-list-item {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: .7rem 0;
    border-bottom: 1px solid var(--card-border);
  }

  .stats-list-item:last-child {
    border-bottom: 0;
  }

  .stats-rank {
    width: 1.8rem;
    text-align: center;
    font-size: 1.1rem;
    font-weight: 950;
    color: #d1d5db;
    flex-shrink: 0;
  }

  .stats-link {
    color: var(--text-main);
    font-size: .86rem;
    font-weight: 750;
    text-decoration: none;
  }

  .stats-link:hover {
    color: var(--accent);
  }

  .stats-muted {
    color: var(--text-muted);
    font-size: .78rem;
  }

  @media (max-width: 1100px) {
    .stats-kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stats-chart-grid,
    .stats-chart-grid-3 {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .stats-header {
      flex-direction: column;
    }

    .stats-kpi-grid {
      grid-template-columns: 1fr;
    }

    .stats-card {
      padding: 1rem;
      border-radius: 14px;
    }

    .stats-chart-box,
    .stats-chart-box.small {
      height: 240px;
    }
  }
</style>

@php
  $totalViews = $articles->sum('views');
  $totalArticles = $articles->count();
  $totalComments = $topCommented->sum('comments_count');
  $topArticle = $articles->sortByDesc('views')->first();
@endphp

<div class="stats-page">

  <div class="stats-header">
    <div>
      <div class="stats-eyebrow">Analytics newsroom</div>
      <h1 class="admin-page-title" style="margin:0;">Statistiche</h1>
      <div class="stats-subtitle">Monitoraggio editoriale, crescita newsletter e performance contenuti.</div>
    </div>
    <div class="stats-live">● Live data</div>
  </div>

  <div class="stats-kpi-grid">
    <div class="stats-card">
      <div class="stats-kpi-label">Views totali</div>
      <div class="stats-kpi-value">{{ number_format($totalViews, 0, ',', '.') }}</div>
      <div class="stats-kpi-note">Somma articoli indicizzati</div>
    </div>

    <div class="stats-card">
      <div class="stats-kpi-label">Articoli</div>
      <div class="stats-kpi-value">{{ number_format($totalArticles, 0, ',', '.') }}</div>
      <div class="stats-kpi-note">Contenuti analizzati</div>
    </div>

    <div class="stats-card">
      <div class="stats-kpi-label">Commenti</div>
      <div class="stats-kpi-value">{{ number_format($totalComments, 0, ',', '.') }}</div>
      <div class="stats-kpi-note">Engagement editoriale</div>
    </div>

    <div class="stats-card">
      <div class="stats-kpi-label">Top article</div>
      <div class="stats-kpi-value">
        {{ $topArticle ? number_format($topArticle->views, 0, ',', '.') : '0' }}
      </div>
      <div class="stats-kpi-note">
        {{ $topArticle ? Str::limit($topArticle->title, 34) : 'Nessun articolo' }}
      </div>
    </div>
  </div>

  <div class="stats-chart-grid">
    <div class="stats-card">
      <div class="stats-card-title">
        <h2>📈 Views nel tempo</h2>
        <span class="stats-badge">Chart.js</span>
      </div>
      <div class="stats-chart-box">
        <canvas id="viewsChart"></canvas>
      </div>
    </div>

    <div class="stats-card">
      <div class="stats-card-title">
        <h2>🧭 Categorie</h2>
        <span class="stats-badge">Distribuzione</span>
      </div>
      <div class="stats-chart-box">
        <canvas id="categoriesChart"></canvas>
      </div>
    </div>
  </div>

  <div class="stats-chart-grid-3">
    <div class="stats-card">
      <div class="stats-card-title">
        <h2>📧 Crescita newsletter</h2>
        <span class="stats-badge">Subscribers</span>
      </div>
      <div class="stats-chart-box small">
        <canvas id="newsletterChart"></canvas>
      </div>
    </div>

    <div class="stats-card">
      <div class="stats-card-title">
        <h2>💬 Più commentati</h2>
        <span class="stats-badge">Engagement</span>
      </div>

      @forelse($topCommented as $i => $art)
        <div class="stats-list-item">
          <span class="stats-rank">{{ $i + 1 }}</span>
          <div style="flex:1;min-width:0;">
            <a class="stats-link" href="{{ route('admin.articles.edit', $art->id) }}">
              {{ Str::limit($art->title, 52) }}
            </a>
          </div>
          <span class="stats-muted" style="font-weight:800;">{{ $art->comments_count }} 💬</span>
        </div>
      @empty
        <div class="stats-empty">Nessun commento ancora.</div>
      @endforelse
    </div>
  </div>

  <div class="stats-card">
    <div class="stats-card-title">
      <h2>🏆 Top articoli per visualizzazioni</h2>
      <span class="stats-badge">Top 10</span>
    </div>

    <div class="stats-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Titolo</th>
            <th>Categoria</th>
            <th>Views</th>
            <th>Lettura</th>
            <th>Pubblicato</th>
          </tr>
        </thead>
        <tbody>
          @foreach($articles->take(10) as $i => $art)
            <tr>
              <td style="font-weight:950;color:#d1d5db;font-size:1.1rem;">{{ $i + 1 }}</td>
              <td>
                <a href="{{ route('articolo', $art->slug) }}" target="_blank" class="stats-link">
                  {{ Str::limit($art->title, 60) }}
                </a>
              </td>
              <td>
                <span class="badge badge--{{ $art->category }}">
                  {{ config('laboratorio.categories.' . $art->category) }}
                </span>
              </td>
              <td style="font-weight:850;color:#0d9488;">
                {{ number_format($art->views, 0, ',', '.') }}
              </td>
              <td class="stats-muted">{{ $art->read_minutes }} min</td>
              <td class="stats-muted">{{ optional($art->published_at)->format('d/m/Y') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function () {
  'use strict';

  const endpoint = '/admin/stats/charts';
  const chartInstances = {};

  const isDarkMode = () => {
    return document.documentElement.classList.contains('dark')
      || document.body.classList.contains('dark')
      || window.matchMedia('(prefers-color-scheme: dark)').matches;
  };

  const cssVar = (name, fallback) => {
    const value = getComputedStyle(document.querySelector('.stats-page')).getPropertyValue(name).trim();
    return value || fallback;
  };

  const safeArray = (value) => Array.isArray(value) ? value : [];

  const normalizePoint = (item, labelKeys, valueKeys) => {
    const labelKey = labelKeys.find(key => item && Object.prototype.hasOwnProperty.call(item, key));
    const valueKey = valueKeys.find(key => item && Object.prototype.hasOwnProperty.call(item, key));

    return {
      label: labelKey ? String(item[labelKey]) : '',
      value: valueKey ? Number(item[valueKey] || 0) : 0
    };
  };

  const getBaseOptions = () => {
    const dark = isDarkMode();
    const textColor = cssVar('--text-muted', dark ? '#9ca3af' : '#6b7280');
    const gridColor = dark ? 'rgba(156, 163, 175, .14)' : 'rgba(107, 114, 128, .14)';

    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          labels: {
            color: textColor,
            usePointStyle: true,
            boxWidth: 8
          }
        },
        tooltip: {
          padding: 12,
          cornerRadius: 12
        }
      },
      scales: {
        x: {
          ticks: { color: textColor },
          grid: { color: gridColor }
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: textColor,
            precision: 0
          },
          grid: { color: gridColor }
        }
      }
    };
  };

  const destroyChart = (id) => {
    if (chartInstances[id]) {
      chartInstances[id].destroy();
      delete chartInstances[id];
    }
  };

  const createLineChart = (id, labels, values, label) => {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return;

    destroyChart(id);

    const accent = cssVar('--accent', '#0d9488');

    chartInstances[id] = new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: values,
          borderColor: accent,
          backgroundColor: 'rgba(13, 148, 136, .12)',
          fill: true,
          tension: .38,
          pointRadius: 3,
          pointHoverRadius: 5
        }]
      },
      options: getBaseOptions()
    });
  };

  const createBarChart = (id, labels, values, label) => {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return;

    destroyChart(id);

    const accent = cssVar('--accent', '#0d9488');

    chartInstances[id] = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: values,
          backgroundColor: 'rgba(13, 148, 136, .72)',
          borderColor: accent,
          borderWidth: 1,
          borderRadius: 10
        }]
      },
      options: getBaseOptions()
    });
  };

  const createDoughnutChart = (id, labels, values) => {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return;

    destroyChart(id);

    const textColor = cssVar('--text-muted', '#6b7280');

    chartInstances[id] = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: [
            'rgba(13, 148, 136, .85)',
            'rgba(59, 130, 246, .85)',
            'rgba(245, 158, 11, .85)',
            'rgba(168, 85, 247, .85)',
            'rgba(239, 68, 68, .85)',
            'rgba(34, 197, 94, .85)'
          ],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: textColor,
              usePointStyle: true,
              boxWidth: 8
            }
          },
          tooltip: {
            padding: 12,
            cornerRadius: 12
          }
        }
      }
    });
  };

  const renderFallbackCharts = () => {
    createLineChart('viewsChart', ['Nessun dato'], [0], 'Views');
    createLineChart('newsletterChart', ['Nessun dato'], [0], 'Iscritti');
    createDoughnutChart('categoriesChart', ['Nessun dato'], [1]);
  };

  const renderCharts = (payload) => {
    const views = safeArray(payload && payload.views).map(item => {
      return normalizePoint(item, ['date', 'day', 'month', 'label'], ['views', 'count', 'total']);
    });

    const newsletter = safeArray(payload && payload.newsletter).map(item => {
      return normalizePoint(item, ['date', 'day', 'month', 'label'], ['subscribers', 'count', 'total']);
    });

    const categories = safeArray(payload && payload.categories).map(item => {
      return normalizePoint(item, ['label', 'category', 'name'], ['views', 'count', 'total', 'total_views']);
    });

    createLineChart(
      'viewsChart',
      views.length ? views.map(item => item.label) : ['Nessun dato'],
      views.length ? views.map(item => item.value) : [0],
      'Views'
    );

    createBarChart(
      'newsletterChart',
      newsletter.length ? newsletter.map(item => item.label) : ['Nessun dato'],
      newsletter.length ? newsletter.map(item => item.value) : [0],
      'Nuovi iscritti'
    );

    createDoughnutChart(
      'categoriesChart',
      categories.length ? categories.map(item => item.label) : ['Nessun dato'],
      categories.length ? categories.map(item => item.value) : [1]
    );
  };

  const loadCharts = async () => {
    if (typeof Chart === 'undefined') return;

    try {
      const response = await fetch(endpoint, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        renderFallbackCharts();
        return;
      }

      const data = await response.json();
      renderCharts(data || {});
    } catch (error) {
      console.warn('Errore caricamento analytics:', error);
      renderFallbackCharts();
    }
  };

  document.addEventListener('DOMContentLoaded', loadCharts);
})();
</script>

@endsection
