<?php
/**
 * Il Laboratorio — Schedulazione comandi
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

// ── Automazione notizie ────────────────────────────────────────
// Raccoglie da feed RSS e genera bozze con AI
// Lunedì e giovedì alle 9:00
Schedule::command('news:fetch')->weeklyOn(1, '09:00')->appendOutputTo(storage_path('logs/news-fetch.log'));
Schedule::command('news:fetch')->weeklyOn(4, '09:00')->appendOutputTo(storage_path('logs/news-fetch.log'));

// ── Backup automatico database ─────────────────────────────────
// Ogni giorno alle 2:00 di notte
Schedule::command('backup:database')->dailyAt('02:00')->appendOutputTo(storage_path('logs/backup.log'));

// ── Pulizia cache ──────────────────────────────────────────────
// Ogni domenica alle 3:00
Schedule::command('cache:prune-stale-tags')->weeklyOn(0, '03:00');

// ── Sitemap: rigenerazione ─────────────────────────────────────
// Ogni giorno alle 4:00 (quando ci sono nuovi articoli)
Schedule::call(function () {
    // La sitemap è generata dinamicamente, nessuna azione necessaria
})->dailyAt('04:00')->name('sitemap-refresh');
