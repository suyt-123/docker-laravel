# Tinhouse Engineering Backend

Laravel 12 + React/Inertia + PostgreSQL Docker development environment for the sheet-metal and steel-structure project management system.

## Services

| Service | URL / Port | Purpose |
| --- | --- | --- |
| App | http://localhost:8080 | Nginx entrypoint for Laravel |
| Vite | http://localhost:5173 | React/Inertia dev server |
| PostgreSQL | localhost:5432 | Main database, database `tinhouse` |
| Redis | localhost:6379 | Cache, queue, sessions, future broadcasts |
| MinIO | http://localhost:9001 | S3-compatible local storage console |
| Mailpit | http://localhost:8025 | Local email inbox |
| Adminer | http://localhost:8081 | Web database table viewer/editor |

Default MinIO credentials are `minioadmin` / `minioadmin`.

Default Adminer login:

- System: `PostgreSQL`
- Server: `postgres`
- Username: `tinhouse`
- Password: `secret`
- Database: `tinhouse`

## First Run

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose run --rm node npm install
```

Default development login:

- Email: `admin@example.com`
- Password: `password`

Run Vite when working on React/Inertia screens:

```bash
docker compose run --rm --service-ports node npm run dev -- --host 0.0.0.0
```

If the `node` service is already running from `docker compose up`, use:

```bash
docker compose logs -f node
```

## Common Commands

```bash
docker compose config
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan queue:work
docker compose exec app php artisan storage:link
docker compose run --rm node npm run build
```

PostgreSQL 18 stores its data under a versioned directory inside `/var/lib/postgresql`; the compose volume intentionally mounts that parent directory.

## PDF Smoke Test

Browsershot uses Chromium installed in the PHP image. After the app container is built:

```bash
docker compose exec app php -r "require 'vendor/autoload.php'; Spatie\\Browsershot\\Browsershot::html('<h1>Tinhouse PDF</h1>')->setChromePath('/usr/bin/chromium')->noSandbox()->save('/tmp/tinhouse-smoke.pdf'); echo file_exists('/tmp/tinhouse-smoke.pdf') ? 'PDF OK'.PHP_EOL : 'PDF failed'.PHP_EOL;"
```

PDF output can be tuned for smaller servers:

```env
# chromium: generate a real PDF with Browsershot/Chromium
# html: return a printable HTML page, so the browser can print/save without server-side Chromium
DOCUMENT_PDF_RENDERER=chromium

# inline: open generated PDFs in the browser
# attachment: download generated PDFs
DOCUMENT_PDF_DISPOSITION=inline
```

Progress logs can stay enabled while photo uploads are disabled for smaller servers:

```env
FEATURE_PROGRESS_PHOTOS=false
```

## MVP Schema

The first domain schema is in place for the money-and-operations path:

- `customers`, `customer_contacts`
- `projects`
- `quotations`, `quotation_items`
- `material_categories`, `materials`
- `inventory_transactions`
- `work_crews`, `workers`
- `dispatches`, `dispatch_worker`
- `financial_records`

This covers the first MVP flow: customer -> project -> quotation -> materials/inventory -> dispatch -> payment tracking.

## Project Structure

The codebase includes Laravel Breeze/Inertia authentication, the MVP domain models, and placeholder directories for the planned service/action layers:

- `app/Actions`
- `app/Services`
- `app/Jobs`
- `resources/js/Pages`
- `resources/js/Components`
- `resources/js/Hooks`
- `resources/js/lib`

Next implementation step: build the Inertia CRUD screens for customers and projects, then connect quotations and materials.
