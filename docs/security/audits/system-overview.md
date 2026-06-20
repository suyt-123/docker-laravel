# System Overview

## Project Architecture

- Framework: Laravel 12 backend with Inertia + React frontend.
- Runtime: PHP 8.4 target, Vite/React 18, PostgreSQL, Redis, MinIO/S3-compatible storage, Nginx + PHP-FPM Docker stack.
- Entry points:
  - Web routes: `routes/web.php`
  - Auth routes: `routes/auth.php`
  - Console routes: `routes/console.php`
  - API routes: no `routes/api.php` found.
- Main domains:
  - CRM customers
  - Projects and change orders
  - Quotations and quotation templates
  - Inventory, purchase orders, suppliers
  - Equipment and equipment transactions
  - Dispatches, attendance, progress logs/photos
  - Users, roles, activity logs, system settings

## Authentication

- Uses Laravel session authentication through Breeze-style controllers in `app/Http/Controllers/Auth`.
- Login throttling exists in `app/Http/Requests/Auth/LoginRequest.php` with 5 attempts per email/IP throttle key.
- Password reset and email verification routes are present.
- Public registration is enabled through `routes/auth.php`.

## Authorization

- Route-level capability middleware is registered in `bootstrap/app.php` as `capability`.
- Capability checks are implemented in:
  - `app/Http/Middleware/EnsureUserHasCapability.php`
  - `app/Auth/CapabilityAuthorizer.php`
- Data-scope authorization exists for selected resources in `app/Auth/DataScope.php`:
  - Projects
  - Dispatches
  - Workers
  - Progress logs
  - Attendance records
- No Laravel Policy classes found in `app/Policies`.
- Most authorization is route middleware plus selected controller-level scope checks.

## API

- No dedicated API route file was found.
- No Passport/JWT API authentication flow was found.
- `laravel/sanctum` is installed, but this codebase appears to primarily use web session auth.

## Database

- Database: PostgreSQL by default in `.env`/Docker.
- Eloquent models use explicit `$fillable` properties.
- Raw SQL scan found no direct string-concatenated raw SQL injection candidates.
- Raw usage found:
  - `DB::transaction(...)`
  - constant `DB::raw('count(*) as total')`
  - constant `orderByRaw('due_date is null')`

## File Upload

- Public disk is configured at `storage/app/public` and exposed under `/storage`.
- Upload surfaces found:
  - Quotation attachments: `QuotationController::storeAttachment`
  - Progress photos: `ProgressLogController::storePhotos`
  - Attendance photos: `AttendanceRecordController::store`
- Progress/attendance photo requests use Laravel `image` validation.
- Quotation attachments currently validate only `file` and `max`.

## External Services

- S3-compatible object storage through `league/flysystem-aws-s3-v3`.
- MinIO is provided in Docker for local S3-compatible storage.
- Browsershot/Chromium is used for PDF rendering.
- Mail configuration is present; Mailpit is used in Docker.

## Security-Relevant Configuration

- `.env` exists locally and is not tracked by Git.
- `.env.example` is tracked and contains local defaults.
- Current local `.env` observed:
  - `APP_ENV=local`
  - `APP_DEBUG=true`
  - `SESSION_ENCRYPT=false`
  - `FILESYSTEM_DISK=s3`
- Docker publishes PostgreSQL, Redis, MinIO, Mailpit, Adminer, Nginx, and Vite ports for local development.
