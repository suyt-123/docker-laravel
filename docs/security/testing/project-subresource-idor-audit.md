# Project Subresource IDOR Audit

## Scope

本輪只新增測試，不修改 Controller / Service / Model，不部署 Production。

檢查目標：

- Project change orders
- Dispatches
- Progress logs
- Financial records
- Inventory transactions
- Quotations linked to project
- Attachments linked to project or quotation

## 新增測試檔案

- `tests/Feature/ProjectSubresourceIdorTest.php`

測試策略：

- 建立使用者，給予子資源 route 所需 capability。
- 同時給予 `projects.projects.view.assigned`，但不把目標 project 指派給該使用者。
- 直接透過子資源 ID 存取 show/delete route。
- 預期安全行為：403。

## 測試結果

指令：

- `docker compose exec -T app php -l tests/Feature/ProjectSubresourceIdorTest.php`
  - PASS

- `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/ProjectSubresourceIdorTest.php`
  - FAIL
  - 2 passed, 6 failed

## 已正確阻擋的項目

## Progress Logs

- 測試：`test_progress_log_id_cannot_bypass_project_visibility`
- Route: `progress-logs.show`
- Result: PASS, 403
- 判斷：`ProgressLogController::ensureVisible()` 已使用 `DataScope::progressLogs()` 檢查 object-level visibility。

## Progress Photos

- 測試：`test_progress_photo_id_cannot_bypass_project_visibility`
- Route: `progress-photos.destroy`
- Result: PASS, 403
- 判斷：刪除照片時會透過照片所屬 `ProgressLog` 執行 visibility check。

## 發現的 IDOR 風險

## Finding 1：Project Change Orders 可透過 ID 存取不可見 Project 資料

- Test: `test_project_change_order_id_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 200
- Affected route:
  - `project-change-orders.show`
- Risk: High
- Attack scenario: 使用者具備 `projects.change_orders.view.tenant`，但只具備 project assigned scope，仍可猜測或取得 change order ID，直接查看未指派 project 的追加單。
- Impact:
  - 洩漏 project name/customer/amount/status/internal note 等追加單資訊。
  - 可能暴露工程追加金額與客戶溝通資訊。
- 建議修法:
  - 在 `ProjectChangeOrderController` 加入共用 `ensureVisible(Request $request, ProjectChangeOrder $order)`。
  - 使用 `DataScope::projects(Project::query(), $request->user())->whereKey($order->project_id)->exists()` 驗證所屬 project 可見。
  - 套用於 show/edit/update/destroy 以及 workflow actions：submit-review、approve、confirm-customer、cancel、create-quotation、convert-financial-record。

## Finding 2：Dispatches 可透過 ID 存取不可見 Project 資料

- Test: `test_dispatch_id_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 200
- Affected route:
  - `dispatches.show`
- Risk: High
- Attack scenario: 使用者具備 `field.dispatches.view.assigned`，但該 dispatch 並不屬於自己的工班、本人、或自己管理的 project，仍可直接透過 dispatch ID 查看。
- Impact:
  - 洩漏派工日期、工作項目、施工地址、工班與工人資訊。
- 建議修法:
  - 在 `DispatchController` 加入 `ensureVisible()`。
  - 使用 `DataScope::dispatches(Dispatch::query(), $request->user())->whereKey($dispatch->id)->exists()`。
  - 套用於 show/edit/update/destroy，以及任何 route-model-bound dispatch action。

## Finding 3：Financial Records 可透過 ID 存取不可見 Project 財務資料

- Test: `test_financial_record_id_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 200
- Affected route:
  - `financial-records.show`
- Risk: High
- Attack scenario: 使用者具備 `finance.financial_records.view.tenant`，但不具備目標 project visibility，仍可透過 financial record ID 查看收款資料。
- Impact:
  - 洩漏訂金、尾款、追加款、付款狀態、到期日與備註。
  - 直接暴露商業敏感財務資料。
- 建議修法:
  - 在 `FinancialRecordController` 加入 project visibility check。
  - 對有 `project_id` 的 financial record，確認所屬 project 對目前使用者可見。
  - 套用於 show/edit/update/destroy。
  - 若業務上 `finance.financial_records.view.tenant` 本來就代表全租戶財務可見，需明確記錄這是設計決策；否則應以 project visibility 收斂。

## Finding 4：Inventory Transactions 可透過 ID 存取不可見 Project 庫存異動

- Test: `test_inventory_transaction_id_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 200
- Affected route:
  - `inventory-transactions.show`
- Risk: Medium to High
- Attack scenario: 使用者具備 `inventory.inventory_transactions.view.tenant`，但不具備目標 project visibility，仍可查看掛在該 project 的庫存異動。
- Impact:
  - 洩漏材料用量、成本、reference number、project 關聯資訊。
- 建議修法:
  - 在 `InventoryTransactionController` 加入 visibility check。
  - 若 transaction 有 `project_id`，確認所屬 project 對目前使用者可見。
  - 若 transaction 無 project_id，依 inventory tenant permission 處理。
  - 套用於 show/edit/update/destroy。

## Finding 5：Quotations linked to Project 可透過 ID 存取不可見 Project 報價資料

- Test: `test_quotation_linked_to_project_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 200
- Affected route:
  - `quotations.show`
- Risk: High
- Attack scenario: 使用者具備 `sales.quotations.view.tenant`，但不具備目標 project visibility，仍可透過 quotation ID 查看 linked project 的報價單。
- Impact:
  - 洩漏報價金額、成本、利潤率、客戶資訊、報價項目。
  - 可間接繞過 Project visibility 取得 project 商業資訊。
- 建議修法:
  - 在 `QuotationController` 加入 visibility check。
  - 若 quotation 有 `project_id`，確認所屬 project 對目前使用者可見。
  - 若 quotation 尚未 linked project，依 quotation tenant permission 處理。
  - 套用於 show/pdf/edit/update/destroy/workflow actions/attachments。

## Finding 6：Quotation Attachments 可透過 Attachment ID 刪除不可見 Project 的附件

- Test: `test_quotation_attachment_id_cannot_bypass_project_visibility`
- Expected: 403
- Actual: 302
- Affected route:
  - `quotations.attachments.destroy`
- Risk: High
- Attack scenario: 使用者具備 `sales.quotations.update.tenant`，但不具備 linked project visibility，仍可透過 `document_attachment` ID 刪除 quotation attachment。
- Impact:
  - 未授權刪除客戶簽回、合約、報價附件等重要文件。
  - 造成資料完整性破壞與稽核風險。
- 建議修法:
  - 在 `QuotationController::destroyAttachment()` 取得 attachable quotation 後，執行 quotation/project visibility check。
  - 若 quotation linked project，需確認 project 對目前使用者可見。
  - 同樣檢查 `storeAttachment()`，避免對不可見 project 的 quotation 新增附件。

## Summary

本輪 Project 子資源 IDOR 測試揭露 6 條可疑路徑：

- Project change orders: FAIL, IDOR found
- Dispatches: FAIL, IDOR found
- Financial records: FAIL, IDOR found
- Inventory transactions: FAIL, IDOR found
- Quotations linked to project: FAIL, IDOR found
- Quotation attachments: FAIL, unauthorized delete path found

已正確阻擋：

- Progress logs
- Progress photos

## 建議修補優先順序

1. High: Quotation attachments delete/store authorization。
2. High: Financial records object-level project visibility。
3. High: Quotations linked to project visibility。
4. High: Project change orders visibility。
5. High: Dispatches visibility。
6. Medium/High: Inventory transactions visibility，需先確認 inventory tenant visibility 是否為設計需求。

## Production Note

不要部署 Production。

建議先針對上述 6 個 failing tests 做下一輪最小修補，每個 controller 使用既有 `DataScope` 或等效 project visibility check，不改 route permission，不做架構重構。
