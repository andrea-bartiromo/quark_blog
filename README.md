# Kairus

## Browser regression tests

The public frontend regression harness runs Playwright against a local Laravel server and a deterministic SQLite fixture. It never targets production and does not submit public forms.

### Windows PowerShell

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database/database.sqlite | Out-Null
$env:DB_CONNECTION = "sqlite"
$env:DB_DATABASE = (Resolve-Path database/database.sqlite).Path
$env:APP_URL = "http://127.0.0.1:8000"
php artisan migrate:fresh --force
php artisan db:seed --class="Database\Seeders\BrowserTestSeeder" --force
npm ci
npx playwright install chromium
npm run test:browser
```

`npm run test:browser` starts and stops `php artisan serve` automatically through Playwright. No separate server terminal is required. The suite uses Chromium only; traces and screenshots are retained when a test fails.
