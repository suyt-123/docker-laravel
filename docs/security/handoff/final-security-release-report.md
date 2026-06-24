# Final Security Release Report

Generated: 2026-06-02

Scope: final P1 security sprint for verified Dispatch and Inventory Transaction IDOR findings.

## 修補內容

### DispatchController

Modified: `app/Http/Controllers/DispatchController.php`

- Added private `ensureVisible(Request $request, Dispatch $dispatch): void`.
- Uses existing `DataScope::dispatches(Dispatch::query(), $request->user())`.
- Checks route-bound dispatch visibility with `whereKey($dispatch->id)->exists()`.
- Applied before data load, schedule conflict checks, update, and delete side effects in:
  - `show`
  - `edit`
  - `update`
  - `destroy`

### InventoryTransactionController

Modified: `app/Http/Controllers/InventoryTransactionController.php`

- Injected existing `DataScope`.
- Added private `ensureVisible(Request $request, InventoryTransaction $inventoryTransaction): void`.
- If `project_id` is present, checks linked project visibility through `DataScope::projects(Project::query(), $request->user())`.
- Transactions without `project_id` keep existing tenant inventory capability behavior.
- Applied before data load, stock recalculation, update, and delete side effects in:
  - `show`
  - `edit`
  - `update`
  - `destroy`

### Tests

Modified: `tests/Feature/ProjectSubresourceIdorTest.php`

- Added coverage for:
  - `dispatches.edit`
  - `dispatches.update`
  - `dispatches.destroy`
  - `inventory-transactions.edit`
  - `inventory-transactions.update`
  - `inventory-transactions.destroy`
- Existing `dispatches.show` and `inventory-transactions.show` IDOR tests now pass.

## 測試結果

| Command | Result |
|---|---|
| `docker compose exec -T app php -l app/Http/Controllers/DispatchController.php` | Pass |
| `docker compose exec -T app php -l app/Http/Controllers/InventoryTransactionController.php` | Pass |
| `docker compose exec -T app php -l tests/Feature/ProjectSubresourceIdorTest.php` | Pass |
| `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/ProjectSubresourceIdorTest.php` | Pass: 13 passed, 75 assertions |
| `docker compose exec -T app php artisan test tests/Feature/SecurityIdorTest.php` | Pass: 11 passed, 15 assertions |
| `docker compose exec -T app php artisan test tests/Feature/DataScopeTest.php` | Pass: 5 passed, 55 assertions |
| `docker compose exec -T app php artisan test` | Fail: 210 passed, 1 failed |
| `docker compose exec -T app php artisan route:list --except-vendor` | Pass: route list generated |
| `docker compose exec -T app php artisan config:cache` | Pass |
| `docker compose exec -T app php artisan config:clear` | Pass; cleared after validation to avoid cached test config side effects |

### Full Test Failure

Remaining full-suite failure:

- `Tests\Feature\ExampleTest::the application returns a successful response`
- Expected: `200`
- Actual: `500`
- Root cause: `HandleInertiaRequests -> SettingService` queries `system_settings`, but the SQLite in-memory database used by `ExampleTest` does not have the `system_settings` table.
- Classification: non-security regression blocker / test environment setup issue. This was present before this final sprint and is unrelated to Dispatch or InventoryTransaction object authorization.

## 剩餘安全風險

### Closed in this sprint

- `dispatches.show` IDOR: fixed.
- `dispatches.edit/update/destroy` direct route-model binding IDOR: fixed.
- `inventory-transactions.show` IDOR for project-linked inventory transactions: fixed.
- `inventory-transactions.edit/update/destroy` direct route-model binding IDOR for project-linked inventory transactions: fixed.

### Remaining known security risks

- `WorkerController` assigned/own direct route-model binding has been reviewed and now uses `DataScope::workers()` in `show`, `edit`, `update`, and `destroy`.
- `CustomerController` may expose related projects/quotations without project-level scoping if customer access is not intended to be tenant-wide.
- Tenant-wide master data controllers still rely mainly on route capability. This is acceptable only if tenant-wide access is the intended authorization boundary.
- `InventoryTransactionController@index` and form option project lists are still tenant-wide. The sprint scope was only direct route-model binding actions; list/form scoping should be reviewed separately if assigned users can reach those pages.

## 是否允許 Production

Recommendation: **Do not deploy directly to Production yet.**

Security status for the verified P1 IDOR items is good: targeted IDOR tests now pass, and Dispatch / Inventory Transaction management tests pass in the full suite.

Production should wait until:

- Full test suite is green or the `ExampleTest` failure is formally accepted as non-release-blocking.
- The release is deployed to staging first.
- Staging smoke tests confirm:
  - assigned users cannot access unassigned dispatches by ID;
  - assigned users cannot access project-linked inventory transactions by ID;
  - assigned users cannot access workers outside their visible worker scope by ID;
  - admin users can still manage dispatches and inventory transactions normally;
  - inventory stock recalculation still works after authorized update/delete.

## Release Recommendation

- **Staging:** Allowed and recommended.
- **Production:** Conditional no-go until the full-suite `ExampleTest` failure is fixed or explicitly waived.
- **Security release confidence for this sprint:** High for the verified Dispatch and Inventory Transaction IDOR scope.
- **Next security sprint:** Review customer-related project/quotation exposure and list/form option scoping for assigned users.

No Production deployment was performed.
