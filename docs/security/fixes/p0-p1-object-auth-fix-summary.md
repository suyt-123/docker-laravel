# P0/P1 Object Authorization Fix Summary

Generated: 2026-06-02

Scope: first-round P0/P1 fixes from `object-authorization-audit.md`.

## 1. 修補檔案

本輪實際修改：

- `app/Http/Controllers/QuotationController.php`
- `app/Http/Controllers/FinancialRecordController.php`
- `app/Http/Controllers/ProjectChangeOrderController.php`
- `tests/Feature/ProjectSubresourceIdorTest.php`
- `p0-p1-object-auth-fix-summary.md`

注意：工作樹中仍有前幾輪已存在的修改與報告檔，本輪未回復或覆蓋那些既有變更。

## 2. 每個 Controller 修補邏輯

### QuotationController

- 新增 `DataScope` 注入。
- 新增 private `ensureVisible(Request $request, Quotation $quotation): void`。
- 若 quotation 沒有 `project_id`，維持既有 quotation tenant capability 行為。
- 若 quotation 有 linked project，使用 `DataScope::projects(Project::query(), $request->user())->whereKey($quotation->project_id)->exists()` 確認目前使用者可見該 project。
- 在以下 action 一開始執行 object-level check：
  - `show`
  - `pdf`
  - `edit`
  - `update`
  - `destroy`
  - `submitReview`
  - `approve`
  - `reject`
  - `sendCustomer`
  - `acceptCustomer`
  - `declineCustomer`
  - `convertProject`
  - `voidQuotation`
  - `reopen`
  - `storeAttachment`
  - `destroyAttachment`
- `destroyAttachment()` 先檢查 `sales.quotations.update.tenant` capability，再確認 attachment parent 是 `Quotation`，接著對 parent quotation 做 linked project visibility check，最後才刪除檔案與資料列。

### FinancialRecordController

- 新增 `DataScope` 注入。
- 新增 private `ensureVisible(Request $request, FinancialRecord $financialRecord): void`。
- 若 financial record 有 `project_id`，使用既有 `DataScope::projects()` 確認目前使用者可見該 project。
- 在以下 action 一開始執行 object-level check：
  - `show`
  - `edit`
  - `update`
  - `destroy`

### ProjectChangeOrderController

- 沿用既有 `DataScope` 注入，未修改 `DataScope`。
- 新增 private `ensureVisible(Request $request, ProjectChangeOrder $projectChangeOrder): void`。
- 使用 linked `project_id` 透過 `DataScope::projects()` 確認目前使用者可見該 project。
- 在以下 action 一開始執行 object-level check：
  - `show`
  - `edit`
  - `update`
  - `destroy`
  - `submitReview`
  - `approve`
  - `confirmCustomer`
  - `cancel`
  - `createQuotation`
  - `convertFinancialRecord`

## 3. 測試結果

已執行：

| Command | Result |
|---|---|
| `docker compose exec -T app php -l app/Http/Controllers/QuotationController.php` | Pass |
| `docker compose exec -T app php -l app/Http/Controllers/FinancialRecordController.php` | Pass |
| `docker compose exec -T app php -l app/Http/Controllers/ProjectChangeOrderController.php` | Pass |
| `docker compose exec -T app php -l tests/Feature/ProjectSubresourceIdorTest.php` | Pass |
| `docker compose exec -T app php artisan test tests/Feature/SecurityIdorTest.php` | Pass: 11 passed |
| `docker compose exec -T app php artisan test tests/Feature/QuotationManagementTest.php` | Pass: 24 passed |
| `docker compose exec -T app php artisan test tests/Feature/FinancialRecordManagementTest.php` | Pass: 12 passed |
| `docker compose exec -T app php artisan test tests/Feature/ProjectChangeOrderManagementTest.php` | Pass: 9 passed |
| `docker compose exec -T app php artisan test tests/Feature/ProjectSubresourceIdorTest.php` | Fail: 9 passed, 2 failed |
| `docker compose exec -T app php artisan test` | Fail: 206 passed, 3 failed |
| `docker compose exec -T app php artisan route:list --except-vendor` | Pass: 181 routes listed |
| `docker compose exec -T app php artisan config:cache` | Pass |

新增/補強的 IDOR 覆蓋：

- Quotation linked project actions:
  - `pdf`
  - `edit`
  - `update`
  - `destroy`
  - `submit-review`
  - `approve`
  - `reject`
  - `send-customer`
  - `accept-customer`
  - `decline-customer`
  - `convert-project`
  - `void`
  - `reopen`
  - `storeAttachment`
- Financial record bound actions:
  - `edit`
  - `update`
  - `destroy`
- Project change order bound/workflow actions:
  - `edit`
  - `update`
  - `destroy`
  - `submit-review`
  - `approve`
  - `confirm-customer`
  - `cancel`
  - `create-quotation`
  - `convert-financial-record`

既有 `ProjectSubresourceIdorTest` 也已確認本輪三個修補範圍的 `show`/attachment cases 通過。

## 4. 是否仍有 failing tests

有。

### 本輪外既有/未處理 IDOR failures

`ProjectSubresourceIdorTest` 仍有 2 個失敗：

- `test_dispatch_id_cannot_bypass_project_visibility`
  - Expected: 403
  - Actual: 200
  - 影響 route: `dispatches.show`
  - 建議下一輪修補：在 `DispatchController` 對 route-model binding actions 加入 object-level visibility check。

- `test_inventory_transaction_id_cannot_bypass_project_visibility`
  - Expected: 403
  - Actual: 200
  - 影響 route: `inventory-transactions.show`
  - 建議下一輪修補：在 `InventoryTransactionController` 對 project-linked transaction 加入 linked project visibility check。

### 既有 ExampleTest failure

`Tests\Feature\ExampleTest::the application returns a successful response` 仍失敗：

- Expected: 200
- Actual: 500
- Root cause: SQLite memory test database lacks `system_settings` table while `/` request goes through `HandleInertiaRequests -> SettingService`.
- 建議修法：讓 `ExampleTest` 使用 `RefreshDatabase`/migration，或調整 welcome route/Inertia shared settings 在 table 不存在時安全 fallback。此項非本輪 object authorization 修補範圍。

## 5. 是否影響正常 admin / assigned user 流程

- Admin / tenant-wide capability 流程：管理測試均通過，`QuotationManagementTest`、`FinancialRecordManagementTest`、`ProjectChangeOrderManagementTest` 未出現回歸。
- Assigned user 流程：Project 本體與本輪修補的 project-linked quotation / financial record / change order，在未指派 project 時會回 403；可見 project 仍沿用 `DataScope::projects()` 判定。
- 注意：linked project 的 quotation 現在要求目前使用者可見該 project。若某些業務角色過去只有 `sales.quotations.*.tenant` 而沒有任何 project visibility，但仍預期能處理已 linked project 的 quotation，需補齊角色能力或明確定義例外規則。

## 6. 下一輪仍待修項目

依 `object-authorization-audit.md` 與本輪測試結果，下一輪建議優先處理：

1. `DispatchController`
   - `show`
   - `edit`
   - `update`
   - `destroy`
   - 建議使用 `DataScope::dispatches()` 或 linked project visibility。

2. `InventoryTransactionController`
   - `show`
   - `edit`
   - `update`
   - `destroy`
   - 若有 `project_id`，需確認 linked project 對目前使用者可見。

3. 後續 P1/P2
   - `WorkerController` assigned/own scope mismatch 已後續修補：direct model-bound actions 使用 `DataScope::workers()`。
   - `CustomerController` show related projects/quotations 是否需要 project scope。
   - 其他 tenant-wide master-data controller 是否符合產品授權模型。

## Production Note

本輪未部署 Production。建議在剩餘 Dispatch / InventoryTransaction IDOR 修補並讓全量測試只剩已接受的非安全測試問題前，不進 Production。
