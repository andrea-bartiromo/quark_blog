# Security configuration

This repository must not contain real secrets, credentials, tokens, API keys, webhook URLs, or generated Laravel application keys.

## Environment setup

Copy the example file and keep real values only in the local or server `.env` file:

```bash
cp .env.example .env
php artisan key:generate
```

`APP_KEY` must stay empty in committed example files. Generate it only in the real `.env` file with `php artisan key:generate`.

## Required variables

Set these values in the real `.env` for production or local integrations:

```env
APP_URL=https://www.example.com
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@example.com
ANTHROPIC_API_KEY=your-anthropic-api-key
```

Do not commit the generated values.

## Gmail SMTP

For Gmail SMTP, enable 2-Step Verification on the Google account and create a Google App Password. Use the App Password as `MAIL_PASSWORD`. Never use or commit the main Google account password.

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@example.com
```

## Manual action required

A real Gmail SMTP credential was previously present in `.env.example`. Rotate or revoke that Gmail App Password immediately in the Google account security settings.
