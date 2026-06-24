# Inertia-first, API-ready Architecture

## Decision

The ERP architecture direction is **Inertia-first, API-ready**.

Do not make a pure API backend plus standalone React SPA the main development line. Keep the current Laravel `web.php` + Inertia + React application as the primary product surface, while preparing reusable backend layers so future API consumers can be added deliberately.

## Why

This project is an internal ERP with authorization-sensitive workflows, project-linked data visibility, finance/inventory consistency, file uploads, PDFs, activity logs, and many form-heavy back-office screens.

For this shape of product, Inertia keeps development fast because Laravel remains responsible for routing, session authentication, validation, authorization, redirects, and server-owned workflow decisions. A full SPA rewrite would add duplicated client/server contracts before there is enough external-consumer pressure to justify that cost.

At the same time, the system should not trap business logic inside Inertia controllers or React pages. Reusable workflows, calculations, presenters, file handling, and report logic should be shaped so API controllers, mobile apps, public pages, or edge services can call the same source-of-truth code later.

## Rules

- Keep the ERP back-office UI on Laravel web routes, Inertia, and React by default.
- Do not create API endpoints for every feature just because a controller exists.
- Add API endpoints only for a real consumer: external integrations, mobile app workflows, public/customer pages, automation, or edge service needs.
- Keep controllers thin. Controllers validate and authorize the request, check object visibility, call a service/action when needed, then return Inertia, redirect, JSON, or file responses.
- Put reusable business workflows in services or actions when they are shared, transaction-heavy, calculation-heavy, or likely to be called by both Inertia and API paths.
- Use presenters/resources when response shapes need to be shared between Inertia props and API JSON.
- Keep Laravel as the source of truth for RBAC capabilities, object-level visibility, workflow transitions, stock movement, finance data, file permissions, and audit logging.
- API access must not bypass FormRequests, `DataScope`, object visibility checks, workflow actions, or activity logging.
- External API token abilities are additive gates only. They never replace RBAC capabilities or row-level visibility checks.

## Future Paths Kept Open

### Mobile app

A future app can use Sanctum-backed API routes that call the same services/actions and authorization checks used by the Inertia app.

### Public or customer-facing pages

Standalone public pages may use narrow purpose-built APIs. Public/customer access should be modeled explicitly instead of reusing broad internal ERP permissions casually.

### React SPA

A standalone SPA is allowed as a future option, but it should start with a small proven slice after API contracts, authentication, authorization, and shared presenters are stable. It should not become the default rewrite plan.

### Workers and edge services

Cloudflare Workers or other edge services may be used for routing, caching, webhook ingress, signed upload mediation, request shaping, or read-optimized public surfaces. They should not duplicate source-of-truth authorization, workflow, inventory, finance, or database transaction logic.

## Practical Default

When adding a normal ERP feature, build it as:

1. Laravel route in `routes/web.php`.
2. Controller or invokable controller returning Inertia.
3. FormRequest for validation when appropriate.
4. Eloquent model relationships and database constraints.
5. Capability middleware plus object-level visibility checks.
6. Inertia React page under the relevant module.
7. Feature tests for success, forbidden access, validation, and IDOR-sensitive paths.

Add API routes only after identifying the API consumer and the contract it needs.

## Non-goals

- Do not rewrite the ERP as a pure API backend plus React SPA.
- Do not remove Inertia as the primary UI layer.
- Do not move source-of-truth ERP workflows to Workers or edge services.
- Do not expose broad write APIs before workflow actions, data scope, and tests are ready.
- Do not duplicate authorization logic in a frontend, SPA, Worker, or public page and treat it as authoritative.
