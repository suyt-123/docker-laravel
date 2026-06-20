# Phase 1 Deployment: NVMe 8 VPS

This guide is for the first production stage:

- NVMe 8 VPS
- Docker Compose
- Nginx
- PostgreSQL
- Redis
- Cloudflare R2 for uploads and database backups
- Cloudflare DNS/CDN
- Uptime Kuma monitoring

## 1. VPS Baseline

Recommended server baseline:

- Ubuntu 24.04 LTS
- 4 vCPU / 8 GB RAM or better
- 100 GB+ NVMe disk
- SSH key login only
- Firewall open only for `22`, `80`, and `443`

Install packages:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git unzip ufw
```

Install Docker Engine and the Compose plugin from Docker's official repository.

After Docker is installed:

```bash
sudo usermod -aG docker $USER
```

Log out and log back in before running Docker without `sudo`.

## 2. Cloudflare DNS

In Cloudflare DNS:

```text
example.com      A      VPS_PUBLIC_IP
www              CNAME  example.com
monitor          A      VPS_PUBLIC_IP
```

Use proxied records for public web traffic. For the first deployment, keep SSH outside Cloudflare and protected by firewall/key auth.

## 3. Cloudflare R2

Create two buckets:

```text
tinhouse-files
tinhouse-backups
```

Create an R2 API token with read/write access to those buckets.

Production `.env` file storage values should look like this:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_r2_access_key
AWS_SECRET_ACCESS_KEY=your_r2_secret_key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=tinhouse-files
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

If files need public URLs, configure a Cloudflare R2 custom domain for the files bucket and set `AWS_URL` to that domain.

## 4. Production Environment

Start from `.env.example`, then change the production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tinhouse
DB_USERNAME=tinhouse
DB_PASSWORD=<strong_password>

CACHE_STORE=redis
REDIS_HOST=redis

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=<production_smtp_host>
MAIL_PORT=587
MAIL_USERNAME=<production_smtp_user>
MAIL_PASSWORD=<production_smtp_password>
MAIL_FROM_ADDRESS=noreply@example.com

DOCUMENT_PDF_RENDERER=chromium
FEATURE_PROGRESS_PHOTOS=true
```

Keep the real production `.env` off Git.

## 5. Deploy The App

Clone the repo:

```bash
git clone <repo-url> /opt/tinhouse/backend
cd /opt/tinhouse/backend
cp .env.example .env
```

Edit `.env`, then build and start:

```bash
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

For production, the Vite dev server should not be exposed. Build assets with `npm run build` and serve the Laravel app through Nginx.

## 6. Queue Worker And Scheduler

At minimum, run:

```bash
docker compose exec app php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

For production, manage this with either:

- a dedicated Compose service for queue workers, or
- Supervisor on the host, or
- systemd.

Laravel scheduler should run once per minute:

```cron
* * * * * cd /opt/tinhouse/backend && docker compose exec -T app php artisan schedule:run >> /dev/null 2>&1
```

## 7. Database Backup To R2

Install AWS CLI on the VPS:

```bash
sudo apt install -y awscli
```

Create an AWS profile for R2:

```bash
aws configure --profile r2
```

Use:

```text
AWS Access Key ID: R2 access key
AWS Secret Access Key: R2 secret key
Default region name: auto
Default output format: json
```

Test access:

```bash
aws s3 ls s3://tinhouse-backups --profile r2 --endpoint-url https://<account_id>.r2.cloudflarestorage.com
```

Run the backup script manually:

```bash
R2_ENDPOINT_URL=https://<account_id>.r2.cloudflarestorage.com \
R2_BACKUP_BUCKET=tinhouse-backups \
scripts/backup-postgres-to-r2.sh
```

Add cron:

```cron
30 2 * * * cd /opt/tinhouse/backend && R2_ENDPOINT_URL=https://<account_id>.r2.cloudflarestorage.com R2_BACKUP_BUCKET=tinhouse-backups scripts/backup-postgres-to-r2.sh >> storage/logs/db-backup.log 2>&1
```

Retention recommendation:

- Daily backups: 14 days
- Weekly backups: 8 weeks
- Monthly backups: 12 months

R2 lifecycle rules can delete old objects automatically.

## 8. Uptime Kuma

Run Uptime Kuma separately from the app stack:

```bash
docker run -d \
  --restart=always \
  -p 3001:3001 \
  -v uptime-kuma:/app/data \
  --name uptime-kuma \
  louislam/uptime-kuma:1
```

Put it behind Nginx as `monitor.example.com`, then add monitors for:

- `https://example.com`
- Laravel health endpoint, when added
- SSL certificate expiry
- Database backup log freshness, if you add a small health check script

## 9. Nginx For Multiple Systems

Use different subdomains:

```text
example.com          -> Tinhouse Laravel
other.example.com    -> another system
monitor.example.com  -> Uptime Kuma
```

Nginx can route by `server_name`, and each system can run in its own Docker Compose project.

## 10. Go-Live Checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- Strong database password
- R2 file upload tested
- Database backup tested
- Restore test completed at least once
- SSL enabled
- Firewall enabled
- SSH key login only
- Queue worker running
- Scheduler running
- Uptime Kuma alert configured
- `php artisan optimize` or config/route/view cache completed

