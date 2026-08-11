<?php

/**
 * Kairus — Schedulazione comandi
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

use Illuminate\Support\Facades\Schedule;

Schedule::command('newsletter:send')
    ->weeklyOn(4, '09:00')
    ->timezone('Europe/Rome')
    ->appendOutputTo(storage_path('logs/newsletter.log'));

Schedule::command('news:fetch')
    ->weeklyOn(1, '09:30')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/news-fetch.log'));

Schedule::command('news:fetch')
    ->weeklyOn(4, '09:30')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/news-fetch.log'));

Schedule::command('backup:database')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('cache:prune-stale-tags')
    ->weeklyOn(0, '03:00');

Schedule::call(function () {})->dailyAt('04:00')->name('sitemap-refresh');

Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/articles-publish.log'));

Schedule::command('projects:sync-derived-statuses')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-sync.log'));

Schedule::command('projects:sync-github-tasks')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-github-sync.log'));

Schedule::command('project:sync-editorial-calendar --execute')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/projects-editorial-sync.log'));
