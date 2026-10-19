# Caldren

Caldren is a Laravel 13 energy intelligence SaaS for commercial building teams. It includes a public business site, multi-building customer workspace, CSV and bill history, AI-assisted seven-day forecasts, and a clearly simulated checkout.

## Local setup

Requires PHP 8.3+, Composer, Node 22+, SQLite, and PHP SQLite extensions.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
php artisan serve
```

The local `.env` uses SQLite. Add `OPENAI_API_KEY` to generate forecasts. `OPENAI_MODEL` defaults to `gpt-5.6-terra`. Without a key, usage, bills, and the rest of the dashboard remain available.

For CSV readings, use exactly `timestamp,kwh` as headers and local building timestamps formatted `YYYY-MM-DD HH:MM`, or ISO 8601 timestamps with an explicit offset for daylight-saving changes. Readings must be nonnegative kWh per interval; the file limit is 5 MB and 5,000 rows. Hourly or sub-hourly readings must cover at least 75% of each of the last 14 complete days for a forecast. Times are converted to UTC for storage.

Run checks with `php artisan test` and `npm run build`.

## Trial and demo billing

A new organization gets 30 days without a card. After trial expiry, historical data remains visible; imports and forecasts require an active demo subscription. The billing page accepts only the displayed `4242 4242 4242 4242` demo number, stores no card details, and simulates one month of access. There is no real processor, automatic renewal, or charge. Never enter a real card.

## cPanel deployment

The deployment and rollback callers use the same `A1-CM/.github` reusable workflows pinned to `v1.0.8` as LogicStrand. The `caldren` slug must be unique on the cPanel account. Set `APP_URL=https://caldren.net`, `CPANEL_HOST`, `CPANEL_USERNAME`, `CPANEL_HOME`, and optionally `CPANEL_DOMAIN` and `CPANEL_PUBLIC_DIR` as GitHub repository/organization variables. Provide `CPANEL_API_TOKEN` as an organization or repository secret. Put `OPENAI_API_KEY=...` in the repository `APP_ENV_EXTRA` secret. Set `PHP_VERSION` to the cPanel PHP version if different from 8.3.

The shared workflow creates and configures MySQL, builds assets, runs tests, applies migrations, checks `/up`, and retains release history for manual rollback. The application bootstrap honors `bootstrap/shared_storage.php` generated for persistent storage. Verify in cPanel that `caldren.net` has a dedicated document root before the first deploy. Do not enable `CPANEL_ALLOW_INDEX_REPLACE` without checking the current index. Database migrations are forward-only, so back up MySQL before schema changes.

## Forecast limits

Forecasts are estimates, not guarantees. The service sends bounded daily usage summaries and tariff settings to OpenAI, requests structured data, and rejects missing, negative, or implausibly large values. API failures leave historical data intact. Evaluate forecast accuracy against held-out building history before making accuracy claims.
