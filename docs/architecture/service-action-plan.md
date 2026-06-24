# Service and Action Refactor Plan

## Goal

Keep the current Laravel/Inertia application stable while moving reusable business logic out of controllers. Controllers should stay responsible for HTTP input and output, while services and actions own reusable workflows that future API controllers can call.

This supports the project architecture direction in `inertia-first-api-ready.md`: the ERP remains Inertia-first, and APIs are added deliberately when there is a real external, app, public-page, automation, or edge-service consumer.

Phase 2 continues in `docs/architecture/api-productization-deployment-plan.md`.

## Layer Boundaries

- Controllers validate request shape through FormRequests or inline validation, call one service/action, then return Inertia, redirect, JSON, or file responses.
- Services handle reusable infrastructure-oriented logic such as PDF rendering, document versions, file storage, reports, and settings-backed calculations.
- Actions handle transaction-heavy domain workflows such as purchase receiving, inventory movement, quotation conversion, quotation reopening, and change-order conversion.
- Resources or presenters should be introduced once a response shape needs to be shared by Inertia and API endpoints.

## Refactor Sequence

1. Extract PDF generation into document services.
2. Extract uploads and attachment storage into file/document services.
3. Extract reports and dashboard metrics into report services.
4. Extract workflow transactions into domain actions.
5. Introduce Resources/Presenters for API-ready output shapes.
6. Add external API token management for read-only API access.
7. Expand API token governance with audit logs and admin revocation.
8. Start write-side API endpoints by attaching existing workflow actions.
9. Move notifications behind events/listeners and queued notifications.

## Current Progress

- PDF generation is now routed through `PdfRenderer`, `QuotationPdfService`, `InvoicePdfService`, and `DocumentVersionRecorder`.
- Upload storage is now routed through `UploadedFileStorage`, with domain services for document attachments, progress photos, and attendance photos.
- Work-hours and dashboard reporting are now routed through `WorkHoursReportService` and `DashboardMetricsService`.
- Inventory transaction create/update/delete now route through `CreateInventoryTransaction`, `UpdateInventoryTransaction`, `DeleteInventoryTransaction`, and `InventoryTransactionService`.
- Purchase receiving now routes through `ReceivePurchaseOrder`, which shares the inventory transaction stock movement path.
- Quotation-to-project conversion now routes through `ConvertQuotationToProject`.
- Quotation reopening now routes through `ReopenQuotation`.
- Quotation submit, approve, reject, customer send, customer accept, customer decline, and void transitions now route through dedicated workflow actions.
- Project change-order financial conversion now routes through `ConvertProjectChangeOrderToFinancialRecord`.
- Project change-order quotation creation now routes through `CreateProjectChangeOrderQuotation`.
- Project change-order submit, approve, customer confirmation, and cancel transitions now route through dedicated workflow actions.
- Repeated quotation and project change-order workflow log payloads now route through `WorkflowActivityLogger`; custom conversion logs stay close to their actions.
- Quotation Inertia output shapes for index, show, edit, statuses, and form options now route through `QuotationPresenter`.
- Project change-order Inertia output shapes for index, create, show, edit, statuses, and form options now route through `ProjectChangeOrderPresenter`.
- Shared presenter model summaries now live in `PresentsModelSummaries` for customer, project, quotation, user, material, and simple model payloads.
- Read-only API routes for quotation and project change-order index/show now share the same presenters under `auth:sanctum` and existing capability middleware.
- Quotation workflow write API routes now call the existing quotation actions for submit, approve, reject, send, accept, decline, void, and reopen transitions.
- Project change-order workflow write API routes now call the existing change-order actions for submit, approve, customer confirm, cancel, create quotation, and convert financial record transitions.
- Purchase-order receive API now calls `ReceivePurchaseOrder`, sharing the same purchase item receiving and inventory stock movement path as Inertia.
- Purchase-order create, update, and delete now route through `CreatePurchaseOrder`, `UpdatePurchaseOrder`, and `DeletePurchaseOrder`, with shared item subtotal/total calculation in `PurchaseOrderItemSyncService`.
- Purchase-order create/update/delete API routes now call the same purchase-order actions as Inertia and return the shared `PurchaseOrderPresenter` shape.
- Inventory transaction write API now calls `CreateInventoryTransaction`, `UpdateInventoryTransaction`, and `DeleteInventoryTransaction`, preserving stock recalculation semantics.
- Material read/write API routes now share `MaterialPresenter` with Inertia output, and material update preserves `current_stock` so external writes cannot bypass stock movement flows.
- Purchase-order read API routes now share `PurchaseOrderPresenter` with Inertia output; receive API responses also use the same presenter shape.
- Workflow notifications now route through `WorkflowNotificationRequested`, `SendWorkflowNotification`, and queued `WorkflowNotification` mail delivery for quotation review/approval/customer acceptance, project change-order review/approval/customer confirmation, and purchase-order receiving.
- External API tokens now use Sanctum personal access tokens, user self-service create/revoke flows, expiration dates, and route-level token abilities such as `read:quotations`.
- Quotation write API access now requires `write:quotations` token ability plus the matching workflow RBAC capability on each route.
- Project change-order write API access now requires `write:project-change-orders` token ability plus the matching workflow RBAC capability on each route.
- Purchase receiving write API access now requires `write:purchase-orders`; inventory transaction write API access now requires `write:inventory-transactions`.
- API token display shapes now route through `ApiTokenPresenter` for Profile and user-management pages.
- API token create/revoke events now route through `ApiTokenAuditLogger` and write `security.api_tokens` activity logs without storing plaintext tokens or token hashes.
- Users with `security.users.update.tenant` can revoke another user's API token from the user detail page.
- Controllers still own request validation, authorization, object visibility, redirects, and Inertia responses.

## Guardrails

- Do not change route names, middleware, request validation, or visible UI behavior during service extraction.
- Keep object-level visibility checks in controllers until a shared visibility service is explicitly introduced.
- Preserve existing ActivityLogger entries and DocumentVersion semantics.
- Keep initial API responses aligned with existing Inertia prop keys until a broader API envelope is explicitly introduced.
- Require both RBAC capabilities and token abilities for external API token access.
- Keep write API endpoints thin: validate request-specific fields, verify object visibility, call the existing workflow action, then return the shared presenter shape.
- For stock-affecting APIs, never bypass the inventory transaction actions/services; they own stock delta application, reversal, and recalculation.
- Material metadata APIs may update master data, but stock quantity changes must continue to flow through inventory transactions or purchase receiving.
- Purchase-order write APIs must preserve the receive lifecycle: partially received, completed, and cancelled orders are not editable, and orders with received quantities are not deletable.
- Dispatch workflow notifications from actions after successful state changes or committed transactions, so Inertia and API controllers share notification behavior.
- Do not log plaintext API tokens or token hashes; audit only metadata, owner, abilities, expiry, and revocation actor.
- Add or run feature tests for every extracted slice before moving to the next one.
