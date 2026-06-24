# API Productization and Deployment Architecture Plan

## Goal

Move from an internal API foundation to a deliberate API-ready deployment architecture that can support external integrations, future app or public-page frontends, selective edge services, and Cloudflare-backed static/object-storage infrastructure without destabilizing the current Laravel/Inertia ERP.

This is phase 2 after `service-action-plan.md` and follows the decision in `inertia-first-api-ready.md`.

## Current Baseline

- Backend is Laravel 12 / PHP 8.4 with Inertia, Sanctum, PostgreSQL, database queues, Redis config, S3-compatible storage, and Browsershot/Chromium PDF generation.
- Business logic for PDFs, uploads, reports, workflow actions, purchase receiving, inventory transactions, quotations, project change orders, materials, and purchase orders has started moving behind services/actions.
- Existing API routes are protected by both Sanctum authentication and route-level token abilities.
- Existing API responses mostly share presenter shapes with Inertia props.
- Current API resource coverage:
  - Read: quotations, project change orders, materials, purchase orders.
  - Write: quotation workflows, project change-order workflows, materials, purchase orders, purchase receiving, inventory transactions.

## Architecture Decision

Recommended path: **Inertia-first Laravel + API-ready services/actions + selective Cloudflare edge/static/storage**.

- Keep Laravel web routes, Inertia, and React as the primary ERP back-office surface.
- Keep Laravel as the source-of-truth workflow backend for both Inertia and future API consumers.
- Keep PostgreSQL as the source-of-truth relational database.
- Use Cloudflare for DNS, WAF, caching, Turnstile where useful, static/public-page hosting, and R2 object storage.
- Use Cloudflare Workers selectively as an edge gateway/BFF for API routing, request shaping, caching, signed upload mediation, webhook ingress, or read-optimized edge features.
- Do not migrate the ERP source-of-truth database to D1 in this phase.
- Do not make a pure API backend plus standalone React SPA the main development line.

Rationale:

- The ERP relies on PostgreSQL-style relational workflows, transactions, row visibility, finance/inventory consistency, queues, PDF rendering, and PHP/Laravel runtime features.
- Cloudflare D1 is a managed serverless database with SQLite semantics and Worker/HTTP access, which makes it useful for edge-local or lightweight relational data, but not a drop-in PostgreSQL replacement for this ERP.
- Cloudflare R2 supports S3-compatible APIs, so it fits the existing Flysystem S3 abstraction with far less application change than a database migration.
- Cloudflare Workers/Pages can serve static assets, public pages, or a future SPA slice, and Workers can build edge routes, but the current Laravel backend should remain on a PHP-capable runtime.

References:

- Cloudflare D1 overview: https://developers.cloudflare.com/d1/
- Cloudflare D1 limits: https://developers.cloudflare.com/d1/platform/limits/
- Cloudflare R2 S3 compatibility: https://developers.cloudflare.com/r2/api/s3/api/
- Cloudflare R2 S3 get started: https://developers.cloudflare.com/r2/get-started/s3/
- Cloudflare Workers overview: https://developers.cloudflare.com/workers/
- Cloudflare Workers static assets / SPA routing: https://developers.cloudflare.com/workers/static-assets/

## Deployment Options

### Option A: Inertia-first Laravel + Targeted API + Cloudflare R2/Static

Recommended default.

- Laravel Inertia backend runs on a PHP/container platform.
- PostgreSQL remains managed PostgreSQL.
- Queue worker runs alongside the API deployment.
- Chromium/Browsershot remains available in the container for PDFs.
- Files move from MinIO/S3-compatible local dev to Cloudflare R2 in production.
- Future app/public-page/SPA slices may deploy to Cloudflare Workers Static Assets or Pages.
- Targeted APIs remain at `api.example.com` or `/api` behind Cloudflare proxy.

Pros:

- Lowest migration risk.
- Keeps existing Laravel workflows, tests, FormRequests, actions, presenters, Sanctum tokens, and PDF pipeline.
- R2 adoption is natural because the app already uses `league/flysystem-aws-s3-v3`.
- Enables API-ready development without forcing a runtime rewrite or SPA migration.

Cons:

- Still requires a serverful/container PHP runtime.
- Operational split: Cloudflare for edge/static/storage, another provider for PHP/PostgreSQL/queues.

### Option B: Cloudflare Worker BFF + Laravel Origin

Useful after Option A is stable.

- SPA calls Worker routes.
- Worker handles edge concerns such as rate limiting, cache keys, request normalization, coarse routing, webhook verification, or signed R2 upload/session brokering.
- Worker forwards source-of-truth writes to Laravel.

Pros:

- Stronger edge control without rewriting Laravel.
- Good place for public integration endpoints, read caching, and tenant/domain routing.

Cons:

- Adds another API layer and failure mode.
- Must avoid duplicating authorization logic outside Laravel.

### Option C: Full Cloudflare-native Rewrite

Not recommended for this ERP in the near term.

- Rewrite backend into Workers/TypeScript or another Workers-supported stack.
- Replace PostgreSQL with D1 or a different Cloudflare data architecture.

Pros:

- Maximum edge-native deployment.

Cons:

- High rewrite risk.
- D1 is SQLite semantics, not PostgreSQL.
- Existing Laravel workflows, queues, PDF rendering, RBAC, tests, and Inertia pages would need major replacement.

## Phase 2 Execution Plan

### Batch 1: API Contract Standardization

Objective: make API responses reliable for external consumers before expanding coverage too far.

Changes:

- Introduce an API response envelope convention.
- Decide whether current resource keys stay as-is or move under a versioned envelope.
- Add a consistent error response format for validation, authorization, not found, and domain-state errors.
- Add route versioning strategy, likely `/api/v1/...`, while keeping existing `/api/...` routes temporarily.
- Add common pagination/filter/sort documentation.
- Add API feature tests for envelope/error shape.

Acceptance:

- Existing API tests still pass or are intentionally migrated.
- New API responses have documented `data`, `meta`, `links`, and `message` conventions.
- Domain errors such as invalid workflow states are predictable for clients.

Risk:

- Medium. Response shape changes can affect current consumers. Use parallel `/api/v1` if compatibility matters.

### Batch 2: OpenAPI and Developer Documentation

Objective: make the API usable outside the app.

Changes:

- Add an OpenAPI document or generation workflow.
- Document authentication with Sanctum personal access tokens.
- Document token abilities and RBAC interaction.
- Document pagination, filtering, date formats, money fields, decimal quantity fields, and file upload/download behavior.
- Add examples for quotation workflow, purchase receiving, inventory transactions, and material/purchase-order CRUD.

Acceptance:

- A developer can create a token and call the documented endpoints without reading Laravel code.
- API docs are versioned with the repo.

Risk:

- Low to medium. Documentation drift is the main risk.

### Batch 3: More Read API Coverage

Objective: cover dashboard/reporting and integration read use cases.

Recommended order:

1. Customers and customer contacts.
2. Suppliers.
3. Projects.
4. Financial records and invoices.
5. Inventory transactions read API.
6. Workers, work crews, dispatches, attendance/progress logs if mobile/field integration is planned.

Rules:

- Use presenters for shared output shape.
- Use `DataScope` for project-linked and field-linked resources.
- Add object-level visibility checks for direct model-bound show routes.
- Do not expose customer contact data unless the user has the existing contact-view capability.

Acceptance:

- Each resource has index/show tests.
- Token ability and RBAC capability are both required.
- IDOR tests exist for project-linked resources.

Risk:

- Medium to high for projects, finance, and field resources because of row visibility.

### Batch 4: More Write API Coverage

Objective: expose only stable, action-backed writes.

Recommended order:

1. Customers and contacts CRUD.
2. Suppliers CRUD.
3. Project create/update for back-office integrations.
4. Financial record create/update with strict project visibility.
5. Dispatch/progress/attendance writes only after field data-scope and mobile workflows are explicit.

Rules:

- New write endpoints should call actions or a small service when they do more than plain model CRUD.
- Always keep route capability, token ability, validation, object visibility, and feature tests together.
- Avoid allowing API clients to bypass workflow transitions by directly setting protected status fields.

Acceptance:

- Each write endpoint has success, missing token ability, missing RBAC, validation, and object-visibility tests.
- Money, stock, workflow status, and project-linked writes are transactionally safe.

Risk:

- High for finance, stock, project-linked, and workflow status fields.

### Batch 5: API Security and Operations

Objective: make the API production-grade.

Changes:

- Add rate limiting per token/user/IP.
- Add optional token naming conventions and expiration policy.
- Add token last-used visibility and audit events for critical write endpoints.
- Add API request logging for external tokens, with sensitive payload redaction.
- Consider IP allowlists per token for high-value integrations.
- Add CORS policy for the future SPA origin.
- Add idempotency keys for create endpoints that external systems may retry.

Acceptance:

- Security behavior is tested.
- Audit logs avoid plaintext tokens, token hashes, passwords, attachments, and sensitive customer payloads.

Risk:

- Medium. Logging and rate limits can create privacy or operability issues if designed casually.

### Batch 6: React SPA Extraction Path

Objective: prepare for React SPA without forcing a full rewrite.

Recommended path:

1. Keep Inertia as the production UI while API stabilizes.
2. Build a small SPA shell against `/api/v1/me`, `/api/v1/navigation`, and one low-risk module.
3. Use cookie/session auth for first-party SPA if hosted on the same parent domain; keep PATs for external integrations.
4. Gradually move module by module only after API coverage and authorization tests are strong.
5. Keep shared authorization constants or generate frontend permissions from API/me.

Acceptance:

- SPA can authenticate, list current user/capabilities, and render one API-backed module.
- Inertia still works during transition.

Risk:

- Medium. The main risk is duplicating UI authorization and drifting from server-side object visibility.

### Batch 7: Cloudflare Deployment Preparation

Objective: move suitable infrastructure to Cloudflare without breaking Laravel runtime assumptions.

Changes:

- Add production R2 filesystem configuration documentation:
  - endpoint
  - bucket
  - access key
  - secret
  - region/URL behavior
  - private object access strategy
- Verify uploads, attachments, document versions, and PDF cache behavior on S3-compatible storage.
- Decide SPA hosting target:
  - Cloudflare Workers Static Assets for future Worker/BFF integration.
  - Cloudflare Pages if keeping it as a simple static deploy target.
- Put Laravel API behind Cloudflare DNS/proxy/WAF.
- Decide queue/cache production services:
  - database queue can work at small scale.
  - Redis or managed queue is better for heavier notifications/PDF work.
- Keep PostgreSQL on a managed PostgreSQL provider.
- Keep Chromium available in the Laravel runtime for PDF rendering.

Acceptance:

- Staging can use R2 for attachments.
- Staging API is reachable through Cloudflare.
- SPA static deployment can call staging API with correct CORS/session/token behavior.
- PDF generation works in the selected PHP/container runtime.

Risk:

- Medium. Most risk is environment/configuration, not application logic.

## Recommended Next Implementation Slice

Start with **Batch 1: API Contract Standardization** before adding many more endpoints.

Why:

- Existing API coverage is now broad enough to reveal response patterns.
- Customers/suppliers/projects APIs will be easier to add once envelope, pagination, error shape, and versioning are fixed.
- It reduces churn for future SPA and external consumers.

Concrete next steps:

1. Add `docs/api/contract.md` with envelope, errors, auth, pagination, filtering, and ability conventions. Done: initial target contract exists for future `/api/v1` routes.
2. Introduce an `ApiResponse` helper or responder class. Done: `App\Support\ApiResponse`.
3. Add `/api/v1` route group in parallel with existing routes. Done: materials v1 routes added.
4. Move one low-risk resource, such as materials, to the v1 response envelope. Done: materials v1 index/show/store/update/delete.
5. Add tests proving old API stays stable and v1 has the new contract. Done for v1 materials contract; keep old unversioned API tests for legacy presenter shape.

## API Ability Naming Direction

Keep the current readable pattern:

- `read:quotations`
- `write:quotations`
- `read:materials`
- `write:materials`
- `read:purchase-orders`
- `write:purchase-orders`

For new resources:

- `read:customers`
- `write:customers`
- `read:suppliers`
- `write:suppliers`
- `read:projects`
- `write:projects`
- `read:financial-records`
- `write:financial-records`

RBAC remains separate from token ability. A token ability never grants a capability by itself.

## Non-Goals For Phase 2

- Do not replace PostgreSQL with D1.
- Do not rewrite the Laravel backend to Workers.
- Do not remove Inertia until the SPA has proven module parity.
- Do not expose every write endpoint just because a controller exists.
- Do not bypass existing FormRequest validation, DataScope, workflow actions, or presenters.
