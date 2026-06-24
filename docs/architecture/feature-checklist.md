# Feature Checklist

Use this before adding a new ERP feature, module, workflow, or API endpoint.

## Architecture Direction

- Is this a normal back-office ERP feature?
  - Build it Inertia-first with Laravel web routes, controller/FormRequest, Inertia React page, capability middleware, and feature tests.
- Is there a real API consumer now?
  - Add a targeted `/api/v1` endpoint only for that consumer and contract.
- Is it only a future possibility?
  - Keep the feature API-ready by moving reusable business logic to services/actions/presenters, but do not add an endpoint yet.

## Backend

- Route is in the correct file: `routes/web.php` for Inertia, `routes/api.php` for API.
- Controller stays thin and delegates reused or transaction-heavy logic.
- FormRequest validation exists for non-trivial writes.
- Model fillable, casts, and relationships are updated.
- Workflow writes use services/actions when status, stock, finance, files, or notifications are involved.

## Authorization And Data Scope

- Capability string follows `{domain}.{resource}.{action}.{scope}`.
- Route capability is present.
- Object-level visibility is checked for direct model-bound actions.
- Project-linked child resources verify linked project visibility.
- Assigned/own scopes use `DataScope` or an equivalent explicit check.
- Frontend authorization constants are updated when new capability strings are introduced.

## API

- API endpoint has a real consumer.
- Endpoint is versioned under `/api/v1`.
- Response follows `docs/api/contract.md`.
- OpenAPI is updated in `docs/api/openapi.yaml`.
- Token ability is required in addition to RBAC.
- API tests cover unauthenticated, missing RBAC, missing token ability, validation, success envelope, and IDOR where relevant.

## Frontend

- Page lives under the relevant `resources/js/Pages/{Module}` folder.
- UI follows the existing Index/Create/Edit/Show module pattern.
- Buttons and links are hidden by frontend capability checks, but backend still enforces authorization.
- Validation errors are shown through existing form patterns.

## Database

- Foreign keys have explicit delete behavior.
- Common filters have indexes.
- Nullable/default choices are intentional.
- Project/user/worker/crew links are reflected in visibility rules.

## Tests

- Add or update the module Feature test.
- Add IDOR tests for model-bound or project-linked paths.
- Run `DataScopeTest` when assigned/own visibility changes.
- Run API contract tests when `/api/v1` changes.
- Run frontend build when React or Vite code changes.
