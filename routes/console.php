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
// Il comando backup:database corrente copia esclusivamente SQLite.
// Manteniamo quindi il job per ambienti SQLite, ma non lo scheduliamo in
// production MariaDB/MySQL finché Backup V2 non offre un dump verificato.
if (config('database.default') === 'sqlite') {
    Schedule::command('backup:database')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/backup.log'));
}

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

// ── Area Progettazione: sync calendario editoriale ────────────────
// Ogni 5 minuti: collega automaticamente SOLO i match sicuri e non
// ambigui tra le voci del calendario editoriale e gli articoli reali
// (sola scrittura additiva — mai uno scollegamento, mai un match
// ambiguo applicato). --execute esplicito: senza, il comando sarebbe un
// dry-run che non farebbe nulla. Preferito a un hook sincrono su
// Article::booted() (stesso pattern già usato sopra per Progettazione):
// idempotente, a basso rischio, riusa l'infrastruttura già testata del
// comando manuale — vedi docs/PROJECT_EDITORIAL_AUTOMATION.md.
Schedule::command('project:sync-editorial-calendar --execute')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-editorial-sync.log'));
