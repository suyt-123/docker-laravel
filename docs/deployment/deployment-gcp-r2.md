# Google Cloud + Cloudflare R2 Deployment

This deployment keeps the app portable with Docker Compose, while using Google Cloud for compute and Cloudflare R2 for object storage and off-server backups.

## Recommended Phase 1 Architecture

```text
Users
  |
Cloudflare DNS/CDN
  |
Google Compute Engine VM
  |
Nginx container
  |
Laravel PHP-FPM container
  |
PostgreSQL container
Redis container

Uploads and backups
  |
Cloudflare R2
```

This is the recommended first production step before moving PostgreSQL to Cloud SQL.

## Google Cloud Resources

Start with:

- Compute Engine VM
- Region: `asia-east1` Taiwan, `asia-northeast1` Tokyo, or `asia-east2` Hong Kong
- Machine: 2 vCPU / 8 GB RAM minimum
- OS: Ubuntu 24.04 LTS
- Boot disk: 100 GB balanced persistent disk or SSD persistent disk
- Firewall: allow `80`, `443`, and locked-down `22`

If budget allows, prefer 4 vCPU / 16 GB RAM once PDF generation, imports, or concurrent usage grows.

## Cloudflare R2 Buckets

Create:

```text
tinhouse-files
tinhouse-backups
```

Use `tinhouse-files` for uploaded photos, PDFs, and attachments.

Use `tinhouse-backups` for PostgreSQL dumps.

Production `.env` storage configuration:

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

If files need public URLs, add a custom domain to the R2 bucket and set `AWS_URL` to that domain.

## VM Setup

Install base tools:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git unzip ufw awscli
```

Install Docker Engine and the Docker Compose plugin from Docker's official repository.

Then:

```bash
sudo usermod -aG docker $USER
```

Log out and back in.

## App Deployment

```bash
git clone <repo-url> /opt/tinhouse/backend
cd /opt/tinhouse/backend
cp .env.example .env
```

Edit `.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tinhouse
DB_USERNAME=tinhouse
DB_PASSWORD=<strong_password>

CACHE_STORE=redis
REDIS_HOST=redis

QUEUE_CONNECTION=database
```

Start:

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

For production, do not expose the Vite dev server publicly.

## Cloudflare DNS

Create DNS records:

```text
app.example.com      A      GCP_VM_EXTERNAL_IP
monitor.example.com  A      GCP_VM_EXTERNAL_IP
```

Enable Cloudflare proxy for web traffic.

Use SSL mode:

```text
Full strict
```

Install a valid origin certificate or Let's Encrypt certificate on the VM.

## Database Backup To R2

Configure AWS CLI profile:

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

Test:

```bash
aws s3 ls s3://tinhouse-backups --profile r2 --endpoint-url https://<account_id>.r2.cloudflarestorage.com
```

Run backup:

```bash
R2_ENDPOINT_URL=https://<account_id>.r2.cloudflarestorage.com \
R2_BACKUP_BUCKET=tinhouse-backups \
scripts/backup-postgres-to-r2.sh
```

Cron:

```cron
30 2 * * * cd /opt/tinhouse/backend && R2_ENDPOINT_URL=https://<account_id>.r2.cloudflarestorage.com R2_BACKUP_BUCKET=tinhouse-backups scripts/backup-postgres-to-r2.sh >> storage/logs/db-backup.log 2>&1
```

## Uptime Kuma

Run Uptime Kuma on the VM:

```bash
docker run -d \
  --restart=always \
  -p 3001:3001 \
  -v uptime-kuma:/app/data \
  --name uptime-kuma \
  louislam/uptime-kuma:1
```

Put it behind Nginx as:

```text
monitor.example.com
```

Monitor:

- `https://app.example.com`
- SSL certificate expiry
- Backup log freshness
- Disk usage
- PostgreSQL container health
- Redis container health

## Upgrade Path

When the system becomes business-critical, move in this order:

1. Move PostgreSQL from container to Cloud SQL for PostgreSQL.
2. Keep uploads and backups on R2.
3. Move Redis to Memorystore only if queue/cache load grows.
4. Split queue workers into their own VM or service.
5. Add Google Cloud Monitoring alerts.

The first important upgrade is Cloud SQL, because the database is the most valuable state in the system.

