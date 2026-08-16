import { defineConfig } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: true,
    timeout: 30_000,
    expect: {
        timeout: 5_000,
    },
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL,
        browserName: 'chromium',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    outputDir: 'test-results/playwright',
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
