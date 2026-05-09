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

Default MinIO credentials are `minioadmin` / `minioadmin`.

## First Run

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose run --rm node npm install
```

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

## Project Structure

The initial codebase is intentionally an application baseline, not the full ERP schema. It includes Laravel Breeze/Inertia authentication and placeholder directories for the planned modules:

- `app/Actions`
- `app/Services`
- `app/Jobs`
- `resources/js/Pages`
- `resources/js/Components`
- `resources/js/Hooks`
- `resources/js/lib`

Next implementation step: create the MVP domain migrations and models for customers, projects, quotations, materials, dispatches, progress photos, and payments.
