<?php

/**
 * Kairus — Schedulazione comandi
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

use Illuminate\Support\Facades\Schedule;

// ── Newsletter settimanale ─────────────────────────────────────
// Ogni giovedì alle 9:00 — seleziona articoli e genera intro con AI
Schedule::command('newsletter:send')
    ->weeklyOn(4, '09:00')
    ->timezone('Europe/Rome')
    ->appendOutputTo(storage_path('logs/newsletter.log'));

// ── Automazione notizie ────────────────────────────────────────
// Raccoglie da feed RSS e genera bozze con AI
// Lunedì e giovedì alle 9:30 (dopo la newsletter)
Schedule::command('news:fetch')
    ->weeklyOn(1, '09:30')
    ->appendOutputTo(storage_path('logs/news-fetch.log'));

Schedule::command('news:fetch')
    ->weeklyOn(4, '09:30')
    ->appendOutputTo(storage_path('logs/news-fetch.log'));

// ── Backup automatico database ─────────────────────────────────
// Ogni giorno alle 2:00 di notte
Schedule::command('backup:database')
    ->dailyAt('02:00')
    ->appendOutputTo(storage_path('logs/backup.log'));

// ── Pulizia cache ──────────────────────────────────────────────
// Ogni domenica alle 3:00
Schedule::command('cache:prune-stale-tags')
    ->weeklyOn(0, '03:00');

// ── Sitemap: rigenerazione ─────────────────────────────────────
// La sitemap è generata dinamicamente, nessuna azione necessaria
Schedule::call(function () {})->dailyAt('04:00')->name('sitemap-refresh');

// ── Pubblicazione programmata articoli ──────────────────────────
// Ogni minuto: pubblica gli articoli 'scheduled' la cui data/ora è arrivata.
// withoutOverlapping() evita esecuzioni concorrenti se una run precedente
// è ancora in corso (es. rallentamenti I/O).
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/articles-publish.log'));

// ── Area Progettazione: riallineamento stati derivati ────────────
// Ogni 5 minuti: riallinea i task di Pubblicazione allo stato corrente
// dell'articolo collegato (idempotente: una run senza cambiamenti non fa nulla).
Schedule::command('projects:sync-derived-statuses')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-sync.log'));

// ── Area Progettazione: sync GitHub task di Sviluppo ─────────────
// Ogni 5 minuti: riallinea branch/PR dei task di Sviluppo (sola lettura,
// idempotente — una run senza cambiamenti non fa nulla). Se il token non
// è configurato o GitHub non è raggiungibile, il servizio non solleva
// eccezioni: il comando termina comunque con successo.
Schedule::command('projects:sync-github-tasks')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-github-sync.log'));
