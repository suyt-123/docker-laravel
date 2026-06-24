# API Contract

## Scope

This document defines the target contract for future versioned API routes, starting with `/api/v1`.

The current unversioned `/api/*` routes are an internal API foundation. They may keep their existing presenter-aligned response shapes until each endpoint is deliberately migrated to `/api/v1`.

The product direction remains **Inertia-first, API-ready**. Add API routes only for real consumers such as external integrations, mobile apps, public/customer pages, automation, or edge services.

## Route Versioning

- New public or integration-facing endpoints should live under `/api/v1`.
- Existing unversioned `/api/*` routes should not be removed until consumers are known and migrated.
- Do not introduce a new version for ordinary additive fields.
- Introduce a new version when removing fields, renaming fields, changing meaning, changing auth behavior, or changing pagination/error envelope semantics.

## Authentication

API routes use Sanctum personal access tokens unless a first-party cookie/session flow is explicitly designed for a future first-party frontend.

Every external-token route must require both:

- Laravel RBAC capability middleware, for example `capability:sales.quotations.view.tenant`.
- Token ability middleware, for example `token_ability:read:quotations`.

Token abilities never grant RBAC by themselves. RBAC never replaces token abilities for external-token routes.

## Authorization And Data Scope

Route capability checks are not object-level authorization.

For direct model-bound routes such as `show`, `update`, `destroy`, workflow transitions, PDF export, upload, receive, approve, convert, or reopen:

- Check the route capability.
- Check the token ability.
- Validate the request with the same FormRequest or equivalent rules used by Inertia.
- Verify object visibility through `DataScope` or an explicit parent-resource visibility check.
- Keep workflow, stock, finance, file permission, and audit decisions in Laravel.

Project-linked child resources must verify linked project visibility unless they are intentionally tenant-wide and documented.

## Response Envelope

Future `/api/v1` success responses should use:

```json
{
  "data": {},
  "meta": {},
  "links": {},
  "message": null
}
```

Rules:

- `data` contains the primary resource or collection.
- `meta` contains pagination, filters, statuses, options, or request metadata.
- `links` contains pagination or related action links when useful.
- `message` is a human-readable result message for mutations, or `null` for simple reads.

Single-resource example:

```json
{
  "data": {
    "id": 123,
    "quotation_no": "Q-2026-0001",
    "status": "draft"
  },
  "meta": {
    "statuses": {
      "draft": "草稿"
    }
  },
  "links": {},
  "message": null
}
```

Collection example:

```json
{
  "data": [
    {
      "id": 123,
      "name": "API 鍍鋅角鐵"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 25,
      "total": 1
    },
    "filters": {
      "search": "",
      "stock": "low"
    }
  },
  "links": {
    "first": "https://example.com/api/v1/materials?page=1",
    "last": "https://example.com/api/v1/materials?page=1",
    "prev": null,
    "next": null
  },
  "message": null
}
```

Mutation example:

```json
{
  "data": {
    "id": 123,
    "status": "reviewing"
  },
  "meta": {},
  "links": {},
  "message": "報價單已送審。"
}
```

## Error Envelope

Future `/api/v1` error responses should use:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": {},
  "meta": {}
}
```

Recommended codes:

- `unauthenticated`
- `forbidden`
- `not_found`
- `validation_failed`
- `token_ability_missing`
- `domain_state_invalid`
- `rate_limited`
- `server_error`

Validation example:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": {
    "name": ["The name field is required."]
  },
  "meta": {}
}
```

Domain-state example:

```json
{
  "message": "此採購單狀態不可再編輯。",
  "code": "domain_state_invalid",
  "errors": {},
  "meta": {
    "current_status": "completed"
  }
}
```

## Pagination

Collection endpoints should support:

- `page`
- `per_page`

Default `per_page` should be `25` for `/api/v1` unless a resource documents a different default. Maximum `per_page` should be `100`.

Pagination metadata should be returned under `meta.pagination`.

## Filtering And Sorting

Filters should be query parameters with explicit documented names. Avoid accepting arbitrary column names.

Common filters:

- `search`
- `status`
- `type`
- `date_from`
- `date_to`

Sorting should use:

- `sort`, for example `sort=created_at` or `sort=-created_at`.

Only documented sort fields are allowed. Unknown filters or sort fields should return `422 validation_failed` for `/api/v1`.

## Field Formats

- Dates use `YYYY-MM-DD`.
- Date-times use ISO 8601 with timezone.
- Money amounts should be decimal strings when exact precision matters.
- Quantities should be decimal strings when preserving database precision matters.
- IDs are integer database IDs unless a resource explicitly exposes a stable public identifier.
- Status values should be machine-readable strings, with labels placed in `meta.statuses` when needed.

## Write Semantics

Write endpoints should call the same actions/services used by Inertia when the operation affects:

- workflow transitions;
- stock movement;
- purchase receiving;
- quotation/project conversion;
- financial records;
- files, PDFs, or document versions;
- notifications;
- activity logs.

Do not let API clients set protected workflow fields directly when an action exists.

For external systems that may retry create requests, add idempotency keys before exposing the endpoint broadly.

## Token Ability Naming

Use readable resource abilities:

- `read:quotations`
- `write:quotations`
- `read:project-change-orders`
- `write:project-change-orders`
- `read:materials`
- `write:materials`
- `read:purchase-orders`
- `write:purchase-orders`
- `write:inventory-transactions`

Future abilities should follow the same pattern:

- `read:customers`
- `write:customers`
- `read:suppliers`
- `write:suppliers`
- `read:projects`
- `write:projects`
- `read:financial-records`
- `write:financial-records`

## Testing Requirements

Each `/api/v1` endpoint should include feature tests for:

- unauthenticated request;
- missing RBAC capability;
- missing token ability;
- successful response envelope;
- validation error envelope for writes;
- object visibility / IDOR for model-bound resources;
- domain-state failure when the operation has workflow rules.

For project-linked resources, include an assigned-scope user that can authenticate but cannot access an unassigned project.
