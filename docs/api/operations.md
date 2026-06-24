# API Operations Checklist

Use this checklist before exposing an API endpoint to an external integration, app, public page, automation, or edge service.

## Required Controls

- Route is versioned under `/api/v1` unless it is intentionally kept as an internal unversioned foundation route.
- Route requires `auth:sanctum`.
- Route requires RBAC capability middleware.
- Route requires matching `token_ability` middleware.
- Direct model-bound routes perform object visibility checks with `DataScope` or an explicit parent-resource check.
- Write routes reuse the same FormRequest validation and services/actions as Inertia.
- Workflow/status fields cannot be bypassed by direct API writes.
- Stock-affecting writes go through inventory transaction or purchase receiving actions.
- Finance-affecting writes use existing finance/project visibility rules.
- File/PDF routes use authorized download/export paths and do not expose storage URLs casually.

## Rate Limits

Before broad external use, add rate limits by token, user, and IP for the route group.

Recommended first pass:

- Read endpoints: higher sustained limit.
- Write endpoints: lower sustained limit.
- Workflow transitions, imports, exports, PDF generation, and file upload routes: stricter limit.

Document any route-specific limits in OpenAPI.

## Idempotency

Add idempotency keys before exposing create or workflow endpoints that external systems may retry.

Good candidates:

- create quotation;
- create purchase order;
- receive purchase order;
- create inventory transaction;
- create financial record;
- webhook ingestion;
- file upload session creation.

## CORS

Do not open CORS broadly.

Define allowed origins for:

- first-party app or SPA;
- public/customer pages;
- trusted partner integrations.

Keep personal access token integrations server-to-server when possible.

## Logging And Redaction

Critical writes should create activity logs or API audit records with:

- actor user id;
- token id or token metadata, never plaintext token or token hash;
- route/action;
- subject type and id;
- result status;
- request correlation id when available.

Do not log:

- plaintext tokens;
- token hashes;
- passwords;
- uploaded file contents;
- sensitive customer contact payloads unless explicitly required and redacted.

## Token Governance

Token creation should require a clear name, allowed abilities, and optional expiration.

For high-value integrations, consider:

- short expiration;
- IP allowlist;
- revocation owner;
- last-used visibility;
- audit event on create/revoke;
- separate read/write tokens.

## Release Gate

Before shipping an API route externally:

- Feature tests cover success, unauthenticated, missing RBAC, missing token ability, validation, and IDOR where relevant.
- OpenAPI is updated.
- `docs/api/contract.md` is still accurate.
- Staging smoke test has been run with a real Sanctum token.
- Logs are checked to ensure no sensitive payloads are written.
