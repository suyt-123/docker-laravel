# Security Fix Plan

## Critical

- No Critical issue confirmed in this review.

## High

1. Restrict quotation attachment uploads.
   - Add MIME/extension allowlist to `QuotationController::storeAttachment`.
   - Add regression test rejecting executable/scriptable uploads.
   - Keep existing accepted PDF upload test passing.

2. Separate user role assignment from user create/update permission.
   - Require `security.roles.assign_capabilities.tenant` before roles are attached, removed, or changed.
   - Add regression tests proving a user with only `security.users.update.tenant` cannot escalate roles.
   - Preserve admin ability to create/update users with roles.

## Medium

1. Disable or gate public registration in production.
   - Add config flag or remove public register routes for production.

2. Add a consistent policy/object-authorization layer.
   - Prioritize tenant-owned models and route-model-bound actions.
   - Convert ad hoc scope checks into policies or reusable scoped binding methods.

3. Harden shared/staging Docker exposure.
   - Remove host-published infrastructure ports outside local development.
   - Require strong credentials for Postgres, Redis, MinIO, and Adminer access.

## Low

1. Production config hardening checklist.
   - `APP_DEBUG=false`
   - strong `APP_KEY`
   - strong database/storage credentials
   - secure cookie/session settings under HTTPS

2. Centralize safe pagination rendering.
   - Avoid repeated `dangerouslySetInnerHTML` use for pagination labels.
