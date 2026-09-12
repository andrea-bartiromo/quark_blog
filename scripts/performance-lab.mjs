#!/usr/bin/env node
/**
 * Cantiere 27 (programma 100-cantieri Kairus). Laboratorio prestazioni
 * ripetibile: cattura metriche di navigazione reali (Navigation Timing
 * + Paint Timing, API standard del browser, mai stimate) su un set
 * fisso di superfici pubbliche, alle stesse larghezze di viewport già
 * usate da tests/browser/public-regression.spec.js — riusa la stessa
 * fixture deterministica (Database\Seeders\BrowserTestSeeder) e la
 * stessa infrastruttura Playwright/Chromium già presente nel progetto,
 * cosi' da produrre un confronto prima/dopo attendibile invece di un
 * numero isolato senza contesto (vedi docs/PERFORMANCE_CWV_S3_AUDIT_PLAN.md,
 * che aveva rimandato questo lavoro per mancanza di un browser
 * affidabile in quella sessione — non piu' il caso qui).
 *
 * Sola lettura: nessuna scrittura sull'applicazione. Avvia un proprio
 * `php artisan serve` (mai il server di produzione), lo interroga con
 * richieste GET reali, lo termina alla fine. L'unico output persistito
 * e' il report in docs/performance-lab/.
 *
 * AVVERTENZA (stessa di docs/PERFORMANCE_BASELINE.md): misure raccolte
 * con `php artisan serve` (mono-thread, nessuna cache di produzione,
 * nessuna CDN) su una macchina non dedicata — utili SOLO per confronto
 * relativo prima/dopo nello stesso ambiente, mai come dato di
 * produzione assoluto.
 *
 * Uso: npm run performance:lab
 *      npm run performance:lab -- --runs=5 --out=docs/performance-lab/mio-report.json
 */

import { chromium } from '@playwright/test';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

// Alcuni ambienti (vedi questo container) preinstallano solo il binario
// Chromium "pieno" e non la variante chrome-headless-shell che
// chromium.launch() sceglie di default nelle Playwright recenti: se
// esiste, si usa esplicitamente quel binario invece di richiedere un
// download che potrebbe non essere permesso/necessario.
const KNOWN_CHROMIUM_PATHS = ['/opt/pw-browsers/chromium'];

function resolveChromiumExecutable() {
    if (process.env.PLAYWRIGHT_CHROMIUM_PATH) return process.env.PLAYWRIGHT_CHROMIUM_PATH;

    return KNOWN_CHROMIUM_PATHS.find(path => existsSync(path));
}

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = dirname(__dirname);

const HOST = '127.0.0.1';
const PORT = 8199; // Porta dedicata, diversa da quella di playwright.config.js (8000): non deve mai competere con un `test:browser` in corso sulla stessa macchina.
const BASE_URL = `http://${HOST}:${PORT}`;

// Stesse route e larghezze di tests/browser/public-regression.spec.js:
// stessa fixture deterministica (BrowserTestSeeder), cosi' un confronto
// tra due esecuzioni di questo script misura solo il cambiamento reale
// dell'applicazione, mai una differenza di contenuto.
const SURFACES = {
    home: '/',
    ricerca: '/ricerca?q=turing',
    articolo: '/articolo/browser-turing-article',
    autore: '/autore/1',
    categoria: '/categoria/intelligenza-artificiale',
    notizie: '/notizie',
};

const VIEWPORT_WIDTHS = [390, 768, 1440];
const VIEWPORT_HEIGHT = 900;

function parseArgs(argv) {
    const args = { runs: 3, out: null };
    for (const arg of argv) {
        const [key, value] = arg.replace(/^--/, '').split('=');
        if (key === 'runs') args.runs = Math.max(1, parseInt(value, 10) || 3);
        if (key === 'out') args.out = value;
    }
    return args;
}

function median(values) {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
}

async function waitForServer(url, timeoutMs) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);
            if (response.status < 500) return;
        } catch {
            // Server non ancora pronto: si ritenta fino al timeout.
        }
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw new Error(`Il server di sviluppo non ha risposto entro ${timeoutMs}ms su ${url}`);
}

async function measureOnce(browser, url, width) {
    const context = await browser.newContext({ viewport: { width, height: VIEWPORT_HEIGHT } });
    const page = await context.newPage();

    await page.goto(url, { waitUntil: 'load' });
    // Piccola attesa dopo 'load': in Chromium headless le entry di paint
    // (first-paint/first-contentful-paint) a volte non sono ancora
    // disponibili nel preciso istante in cui l'evento 'load' si dispara —
    // senza questa attesa risulterebbero nulle in modo intermittente,
    // non perche' il paint non sia avvenuto.
    await page.waitForTimeout(100);

    const metrics = await page.evaluate(() => {
        const [nav] = performance.getEntriesByType('navigation');
        const paints = performance.getEntriesByType('paint');
        const firstPaint = paints.find(p => p.name === 'first-paint');
        const firstContentfulPaint = paints.find(p => p.name === 'first-contentful-paint');

        return {
            domContentLoadedMs: nav ? Math.round(nav.domContentLoadedEventEnd) : null,
            loadMs: nav ? Math.round(nav.loadEventEnd) : null,
            ttfbMs: nav ? Math.round(nav.responseStart) : null,
            transferSizeBytes: nav ? nav.transferSize : null,
            firstPaintMs: firstPaint ? Math.round(firstPaint.startTime) : null,
            firstContentfulPaintMs: firstContentfulPaint ? Math.round(firstContentfulPaint.startTime) : null,
        };
    });

    await context.close();

    return metrics;
}

async function main() {
    const args = parseArgs(process.argv.slice(2));

    console.log(`Avvio del server di sviluppo su ${BASE_URL} (mai il server di produzione)...`);
    const server = spawn('php', ['artisan', 'serve', `--host=${HOST}`, `--port=${PORT}`, '--no-reload'], {
        cwd: repoRoot,
        stdio: 'ignore',
    });

    let browser;
    const report = { collected_at: new Date().toISOString(), base_url: BASE_URL, runs_per_surface: args.runs, surfaces: [] };

    try {
        await waitForServer(BASE_URL, 20_000);
        browser = await chromium.launch({ executablePath: resolveChromiumExecutable() });

        for (const [surfaceName, path] of Object.entries(SURFACES)) {
            for (const width of VIEWPORT_WIDTHS) {
                const samples = [];
                for (let i = 0; i < args.runs; i++) {
                    // eslint-disable-next-line no-await-in-loop
                    samples.push(await measureOnce(browser, `${BASE_URL}${path}`, width));
                }

                const medians = {};
                for (const key of Object.keys(samples[0])) {
                    const values = samples.map(s => s[key]).filter(v => v !== null);
                    medians[key] = values.length > 0 ? median(values) : null;
                }

                report.surfaces.push({ surface: surfaceName, path, viewport_width: width, median: medians, samples });

                console.log(
                    `${surfaceName.padEnd(10)} @${width}px  DCL=${medians.domContentLoadedMs}ms  Load=${medians.loadMs}ms  FCP=${medians.firstContentfulPaintMs}ms`
                );
            }
        }
    } finally {
        if (browser) await browser.close();
        server.kill();
    }

    const outPath = args.out ?? `${repoRoot}/docs/performance-lab/latest.json`;
    await mkdir(dirname(outPath), { recursive: true });
    await writeFile(outPath, JSON.stringify(report, null, 2));
    console.log(`\nReport scritto in ${outPath}`);
}

main().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
