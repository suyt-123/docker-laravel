# Object-Level Authorization Audit

Generated: 2026-06-02

Scope: Laravel route-model binding actions in `routes/web.php` and `app/Http/Controllers`, focusing on `show`, `edit`, `update`, `destroy`, `download/pdf`, approval/workflow actions, and parent-resource binding actions.

Legend:
- Permission Check: route middleware capability or explicit controller capability check.
- Object Visibility Check: per-object authorization such as `DataScope` membership, owner/assignee check, policy check, or equivalent.
- Risk Level reflects object-level authorization risk, not general business-rule risk.

## Executive Summary

| Status | Count / Area |
|---|---|
| Confirmed object visibility checks | `ProjectController`, `ProgressLogController`, `AttendanceRecordController` |
| High-risk missing object checks | Project subresources: change orders, dispatches, financial records, inventory transactions, quotations/attachments |
| Assigned/own scope mismatch | `DispatchController`, `WorkerController` expose assigned/own view capabilities on routes but model-bound actions do not re-check object scope |
| Tenant-wide admin/master data | Users, roles, customers, suppliers, materials, equipment, work crews, purchase orders, templates, activity logs rely mostly on route capability and business rules |

## Audit Table

| Controller | Action | Model | Route | Permission Check | Object Visibility Check | Risk Level | Recommended Fix |
|---|---|---|---|---|---|---|---|
| `ProjectController` | `show` | `Project` | `GET /projects/{project}` | `projects.projects.view.tenant` or `projects.projects.view.assigned` | Yes: `ensureVisible()` checks `DataScope::projects(...)->whereKey($project->id)->exists()` | Low / Fixed | Keep regression tests for assigned user cannot view unassigned project. |
| `ProjectController` | `edit` | `Project` | `GET /projects/{project}/edit` | `projects.projects.update.tenant` | Yes: `ensureVisible()` | Low / Fixed | Keep update/edit object-scope tests. |
| `ProjectController` | `update` | `Project` | `PUT/PATCH /projects/{project}` | `projects.projects.update.tenant` | Yes: `ensureVisible()` | Low / Fixed | Keep update object-scope tests. |
| `ProjectController` | `destroy` | `Project` | `DELETE /projects/{project}` | `projects.projects.delete.tenant` | Yes: `ensureVisible()` | Low / Fixed | Keep delete object-scope tests. |
| `ProjectController` | `invoicePdf` | `Project` | `GET /projects/{project}/invoice-pdf` | `finance.financial_records.export_pdf.tenant` | Yes: `ensureVisible()` | Low / Fixed | Keep PDF export object-scope test because permission is finance-specific. |
| `ProjectChangeOrderController` | `show` | `ProjectChangeOrder` | `GET /project-change-orders/{project_change_order}` | `projects.change_orders.view.tenant` | No: only route capability; no parent project visibility check | High | Add shared `ensureVisible(Request, ProjectChangeOrder)` that verifies the linked `project_id` through `DataScope::projects`. |
| `ProjectChangeOrderController` | `edit` | `ProjectChangeOrder` | `GET /project-change-orders/{project_change_order}/edit` | `projects.change_orders.update.tenant` | Partial: status/business checks only; no parent project visibility check | High | Call shared visibility check before status checks. |
| `ProjectChangeOrderController` | `update` | `ProjectChangeOrder` | `PUT/PATCH /project-change-orders/{project_change_order}` | `projects.change_orders.update.tenant` | Partial: status/business checks only; no parent project visibility check | High | Call shared visibility check before mutation. |
| `ProjectChangeOrderController` | `destroy` | `ProjectChangeOrder` | `DELETE /project-change-orders/{project_change_order}` | `projects.change_orders.delete.tenant` | Partial: status/business checks only; no parent project visibility check | High | Call shared visibility check before deletion checks. |
| `ProjectChangeOrderController` | `convertFinancialRecord` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/convert-financial-record` | `projects.change_orders.convert_financial_record.tenant` | Partial: workflow/status checks only; no parent project visibility check | High | Require parent project visibility before conversion. |
| `ProjectChangeOrderController` | `submitReview` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/submit-review` | `projects.change_orders.submit_review.tenant` | Partial: status checks only; no parent project visibility check | High | Require parent project visibility before workflow transition. |
| `ProjectChangeOrderController` | `approve` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/approve` | `projects.change_orders.approve.tenant` | Partial: status checks only; no parent project visibility check | High | Require parent project visibility before approval. |
| `ProjectChangeOrderController` | `confirmCustomer` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/confirm-customer` | `projects.change_orders.confirm_customer.tenant` | Partial: status checks only; no parent project visibility check | High | Require parent project visibility before customer confirmation. |
| `ProjectChangeOrderController` | `cancel` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/cancel` | `projects.change_orders.cancel.tenant` | Partial: status checks only; no parent project visibility check | High | Require parent project visibility before cancel. |
| `ProjectChangeOrderController` | `createQuotation` | `ProjectChangeOrder` | `POST /project-change-orders/{project_change_order}/create-quotation` | `projects.change_orders.create_quotation.tenant` | Partial: status/formal quotation checks only; no parent project visibility check | High | Require parent project visibility before quotation creation. |
| `DispatchController` | `show` | `Dispatch` | `GET /dispatches/{dispatch}` | `field.dispatches.view.tenant`, `field.dispatches.view.assigned`, or `field.dispatches.view.own` | No: index/schedule use `DataScope`, model-bound action does not | High | Add shared dispatch visibility check using `DataScope::dispatches` or linked project visibility. |
| `DispatchController` | `edit` | `Dispatch` | `GET /dispatches/{dispatch}/edit` | `field.dispatches.update.tenant` | No object visibility check | High | Require dispatch/project visibility before rendering edit form. |
| `DispatchController` | `update` | `Dispatch` | `PUT/PATCH /dispatches/{dispatch}` | `field.dispatches.update.tenant` | No object visibility check | High | Require dispatch/project visibility before mutation. |
| `DispatchController` | `destroy` | `Dispatch` | `DELETE /dispatches/{dispatch}` | `field.dispatches.delete.tenant` | No object visibility check | High | Require dispatch/project visibility before deletion. |
| `ProgressLogController` | `show` | `ProgressLog` | `GET /progress-logs/{progress_log}` | `field.progress_logs.view.tenant`, `field.progress_logs.view.assigned`, or `field.progress_logs.view.own` | Yes: `ensureVisible()` checks scoped progress logs | Low | Keep IDOR regression test. |
| `ProgressLogController` | `edit` | `ProgressLog` | `GET /progress-logs/{progress_log}/edit` | `field.progress_logs.update.tenant` | Yes: `ensureVisible()` | Low | Keep edit/update object-scope tests. |
| `ProgressLogController` | `update` | `ProgressLog` | `PUT/PATCH /progress-logs/{progress_log}` | `field.progress_logs.update.tenant` | Yes: `ensureVisible()` | Low | Keep mutation object-scope tests. |
| `ProgressLogController` | `destroy` | `ProgressLog` | `DELETE /progress-logs/{progress_log}` | `field.progress_logs.delete.tenant` | Yes: `ensureVisible()` | Low | Keep delete object-scope test. |
| `ProgressLogController` | `destroyPhoto` | `ProgressPhoto` | `DELETE /progress-photos/{progress_photo}` | `field.progress_logs.update.tenant` or `field.progress_logs.delete.tenant` | Yes: checks parent `ProgressLog` visibility | Low | Keep photo delete IDOR regression test. |
| `FinancialRecordController` | `show` | `FinancialRecord` | `GET /financial-records/{financial_record}` | `finance.financial_records.view.tenant` | No parent project visibility check | High | If record has `project_id`, require project visibility; otherwise require finance tenant capability. |
| `FinancialRecordController` | `edit` | `FinancialRecord` | `GET /financial-records/{financial_record}/edit` | `finance.financial_records.update.tenant` | No object visibility check | High | Add shared financial-record visibility check. |
| `FinancialRecordController` | `update` | `FinancialRecord` | `PUT/PATCH /financial-records/{financial_record}` | `finance.financial_records.update.tenant` | No object visibility check | High | Add object check before mutation. |
| `FinancialRecordController` | `destroy` | `FinancialRecord` | `DELETE /financial-records/{financial_record}` | `finance.financial_records.delete.tenant` | No object visibility check | High | Add object check before deletion. |
| `InventoryTransactionController` | `show` | `InventoryTransaction` | `GET /inventory-transactions/{inventory_transaction}` | `inventory.inventory_transactions.view.tenant` | No parent project visibility check | High | If transaction has `project_id`, require project visibility. |
| `InventoryTransactionController` | `edit` | `InventoryTransaction` | `GET /inventory-transactions/{inventory_transaction}/edit` | `inventory.inventory_transactions.update.tenant` | No object visibility check | High | Add shared inventory-transaction visibility check. |
| `InventoryTransactionController` | `update` | `InventoryTransaction` | `PUT/PATCH /inventory-transactions/{inventory_transaction}` | `inventory.inventory_transactions.update.tenant` | No object visibility check | High | Add object check before mutation. |
| `InventoryTransactionController` | `destroy` | `InventoryTransaction` | `DELETE /inventory-transactions/{inventory_transaction}` | `inventory.inventory_transactions.delete.tenant` | No object visibility check | High | Add object check before deletion. |
| `QuotationController` | `show` | `Quotation` | `GET /quotations/{quotation}` | `sales.quotations.view.tenant` | No linked project visibility check | High | For quotations with `project_id`, require parent project visibility or quotation-specific DataScope. |
| `QuotationController` | `pdf` | `Quotation` | `GET /quotations/{quotation}/pdf` | `sales.quotations.export_pdf.tenant` | No linked project visibility check | High | Add object check before PDF generation. |
| `QuotationController` | `edit` | `Quotation` | `GET /quotations/{quotation}/edit` | `sales.quotations.update.tenant` | Partial: `canEditQuotation()` status/version rules only | High | Add quotation/project visibility before `canEditQuotation()`. |
| `QuotationController` | `update` | `Quotation` | `PUT/PATCH /quotations/{quotation}` | `sales.quotations.update.tenant` | Partial: `canEditQuotation()` status/version rules only | High | Add object check before mutation. |
| `QuotationController` | `destroy` | `Quotation` | `DELETE /quotations/{quotation}` | `sales.quotations.delete.tenant` | Partial: draft status check only | High | Add object check before deletion. |
| `QuotationController` | `submitReview`, `approve`, `reject`, `sendCustomer`, `acceptCustomer`, `declineCustomer`, `convertProject`, `voidQuotation`, `reopen` | `Quotation` | `POST /quotations/{quotation}/...` | Workflow-specific `sales.quotations.*.tenant` capabilities | Partial: workflow/status checks only; no linked project visibility check | High | Add shared quotation visibility check to every workflow action. |
| `QuotationController` | `storeAttachment` | `Quotation` | `POST /quotations/{quotation}/attachments` | `sales.quotations.update.tenant` | Partial: file type/status checks; no linked project visibility check | High | Add quotation visibility check before accepting upload. |
| `QuotationController` | `destroyAttachment` | `DocumentAttachment` | `DELETE /quotation-attachments/{document_attachment}` | `sales.quotations.update.tenant` | Partial: validates attachment type is `Quotation`; no parent quotation/project visibility check | High | Resolve parent quotation and require same shared quotation visibility check before delete. |
| `AttendanceRecordController` | `show` | `AttendanceRecord` | `GET /attendance-records/{attendance_record}` | `field.attendance.view.tenant`, `field.attendance.view.assigned`, or `field.attendance.view.own` | Yes: `ensureVisible()` checks `DataScope::attendanceRecords` | Low | Keep IDOR regression test. |
| `AttendanceRecordController` | `destroy` | `AttendanceRecord` | `DELETE /attendance-records/{attendance_record}` | `field.attendance.delete.tenant` | Yes: `ensureVisible()` | Low | Keep delete object-scope test. |
| `WorkerController` | `show` | `Worker` | `GET /workers/{worker}` | `field.workers.view.tenant`, `field.workers.view.assigned`, or `field.workers.view.own` | No: index uses `DataScope::workers`, model-bound action does not | High | Add shared `ensureVisible()` using worker DataScope for assigned/own permissions. |
| `WorkerController` | `edit` | `Worker` | `GET /workers/{worker}/edit` | `field.workers.update.tenant` | No object visibility check | Medium | If update is tenant-admin only, document tenant-wide design; otherwise add object check. |
| `WorkerController` | `update` | `Worker` | `PUT/PATCH /workers/{worker}` | `field.workers.update.tenant` | No object visibility check | Medium | Add object check if non-admin update should be scoped. |
| `WorkerController` | `destroy` | `Worker` | `DELETE /workers/{worker}` | `field.workers.delete.tenant` | No object visibility check | Medium | Add object check if non-admin delete should be scoped. |
| `CustomerController` | `show` | `Customer` | `GET /customers/{customer}` | `crm.customers.view.tenant` | Partial: contact fields gated by capability; no customer/project object scope | Medium | If customer records can be project-scoped, add customer visibility and scope related projects/quotations. |
| `CustomerController` | `edit` | `Customer` | `GET /customers/{customer}/edit` | `crm.customers.update.tenant` | No object visibility check | Medium | Add customer object policy if users should not update all tenant customers. |
| `CustomerController` | `update` | `Customer` | `PUT/PATCH /customers/{customer}` | `crm.customers.update.tenant` | No object visibility check | Medium | Add object check before mutation if customer ownership exists. |
| `CustomerController` | `destroy` | `Customer` | `DELETE /customers/{customer}` | `crm.customers.delete.tenant` | No object visibility check | Medium | Add object check and dependency checks before deletion. |
| `PurchaseOrderController` | `show`, `edit`, `update`, `destroy`, `receive` | `PurchaseOrder` | `/purchase-orders/{purchase_order}` and `/purchase-orders/{purchase_order}/receive` | `purchasing.purchase_orders.*.tenant` and `purchasing.purchase_orders.receive.tenant` | Partial: status/item membership checks only | Medium | If purchase orders are project/site scoped, add object visibility; otherwise document tenant-wide purchasing role as intended. |
| `EquipmentTransactionController` | `store` | `Equipment` | `POST /equipment/{equipment}/transactions` | `equipment.transactions.create.tenant` | No object visibility check on bound equipment | Medium | Add equipment visibility/tenant ownership check before creating transaction. |
| `EquipmentController` | `show`, `edit`, `update`, `destroy` | `Equipment` | `/equipment/{equipment}` | `equipment.equipment.*.tenant` | Partial: destroy dependency check only | Medium | Add equipment object policy if equipment access can be scoped by crew/site/project. |
| `EquipmentCategoryController` | `show`, `edit`, `update`, `destroy` | `EquipmentCategory` | `/equipment-categories/{equipment_category}` | `equipment.categories.*.tenant` | Partial: destroy dependency check only | Low | Tenant-wide master data is acceptable if documented; otherwise add category ownership/tenant check. |
| `MaterialController` | `show`, `edit`, `update`, `destroy` | `Material` | `/materials/{material}` | `inventory.materials.*.tenant` | No object visibility check | Low | Tenant-wide inventory master data is acceptable if documented; otherwise add material object policy. |
| `SupplierController` | `show`, `edit`, `update`, `destroy` | `Supplier` | `/suppliers/{supplier}` | `purchasing.suppliers.*.tenant` | Partial: destroy dependency check only | Low | Tenant-wide supplier master data is acceptable if documented; otherwise add supplier object policy. |
| `WorkCrewController` | `show`, `edit`, `update`, `destroy` | `WorkCrew` | `/work-crews/{work_crew}` | `field.work_crews.*.tenant` | No object visibility check | Low | Tenant-wide crew master data is acceptable if documented; otherwise add crew object policy. |
| `QuotationTemplateController` | `show` | `QuotationTemplate` | `GET /quotation-templates/{quotation_template}` | `sales.quotation_templates.view.tenant` | Partial: hides inactive templates unless user can manage templates | Low | Keep active/inactive check; add policy only if templates become scoped. |
| `QuotationTemplateController` | `edit`, `update`, `destroy` | `QuotationTemplate` | `/quotation-templates/{quotation_template}` | `sales.quotation_templates.update/delete.tenant` | No object visibility check beyond tenant capability | Low | Tenant-wide template management is acceptable if documented. |
| `QuotationTemplateController` | `calculate` | `QuotationTemplate` | `POST /quotation-templates/{quotation_template}/calculate` | `sales.quotation_templates.view.tenant` | Partial: active template check only | Low | Keep active-template check; add object policy only if scoped templates are introduced. |
| `UserController` | `show`, `edit`, `update`, `destroy` | `User` | `/users/{user}` | `security.users.*.tenant` | Partial: role assignment now requires `security.roles.assign_capabilities.tenant`; destroy blocks deleting current user | Medium | Add user object policy if tenant admins should not manage every user; keep role escalation tests. |
| `RoleController` | `show`, `edit`, `update`, `destroy` | `Role` | `/roles/{role}` | `security.roles.*.tenant`; store/update also require `security.roles.assign_capabilities.tenant` | Partial: protected system role checks; no per-role object scope | Medium | Tenant-wide role management is acceptable for security admins; keep protected-role and capability-assignment tests. |
| `ActivityLogController` | `show` | `ActivityLog` | `GET /activity-logs/{activity_log}` | `security.activity_logs.view.tenant` | No object visibility check | Medium | Ensure activity logs are tenant-isolated at model/query level; otherwise add object tenant check. |

## Highest Priority Fix Candidates

1. `ProjectChangeOrderController`: all model-bound CRUD and workflow actions should verify linked project visibility before returning or mutating data.
2. `DispatchController`: assigned/own route capabilities are exposed, but direct model binding bypasses index/schedule `DataScope`.
3. `FinancialRecordController`: project-linked records can leak financial data if route ID is guessed.
4. `InventoryTransactionController`: project-linked inventory movement can leak operational data if route ID is guessed.
5. `QuotationController`: project-linked quotations and quotation attachments need shared object visibility checks across PDF, workflows, upload, and delete.
6. `WorkerController`: route supports assigned/own visibility, but direct worker model binding lacks equivalent enforcement.

## Notes

- This audit did not modify production logic.
- The prior Project IDOR fix appears present in `ProjectController` and should remain the reference pattern for project-linked object checks.
- For tenant-wide master data, the risk level assumes tenant capability is an intentional trust boundary. If the product requires branch/site/project-level segmentation, those rows should be reclassified upward and covered by object policies or `DataScope` checks.
