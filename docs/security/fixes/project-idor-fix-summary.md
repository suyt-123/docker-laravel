# Project IDOR Fix Summary

## 1. 修補檔案

- `app/Http/Controllers/ProjectController.php`
- `tests/Feature/SecurityIdorTest.php`
- `project-idor-fix-summary.md`

## 2. 修補邏輯

本次只處理 Project IDOR，不修改 route permission，不修改 `DataScope`。

在 `ProjectController` 新增共用 object-level visibility check：

- 使用 `$this->dataScope->projects(Project::query(), $request->user())`
- 再以 `whereKey($project->id)->exists()` 確認目前使用者真的可見該 project。
- 不可見時回傳 403。

已在以下 route-model-bound Project actions 一開始呼叫：

- `show`
- `edit`
- `update`
- `destroy`
- `invoicePdf`

修補目的：

- 具備 `projects.projects.view.assigned` 的使用者，不能再透過猜測 project ID 直接存取未指派 project。
- 保留 `DataScope` 原本定義的可見範圍，包括：
  - tenant view
  - manager assigned project
  - work crew assigned project
  - dispatch/worker assigned project

## 3. 測試結果

語法檢查：

- `docker compose exec -T app php -l app/Http/Controllers/ProjectController.php`
  - PASS
- `docker compose exec -T app php -l tests/Feature/SecurityIdorTest.php`
  - PASS

指定測試：

- `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/SecurityIdorTest.php tests/Feature/ProjectManagementTest.php tests/Feature/DataScopeTest.php`
  - PASS
  - 24 tests, 106 assertions

全量測試：

- `docker compose exec -T app php artisan test`
  - FAIL
  - 197 passed, 1 failed, 1077 assertions

全量測試唯一失敗：

- `Tests\Feature\ExampleTest`
- 原因：該測試未使用 migration/seed，直接 GET `/` 時 Inertia shared props 讀取 `system_settings`，SQLite memory DB 尚無 `system_settings` table。
- 判斷：與本次 Project IDOR 修補無關；這是先前已存在的測試環境問題。

## 4. 是否影響既有 Assigned User 流程

目前測試顯示不影響合法 assigned user 的 view 流程。

新增測試已確認：

- 只有 `projects.projects.view.assigned` 的使用者，若是該 project 的 manager，可以正常直接開啟 `projects.show`。
- 未指派 project 則會被 403 擋下。

既有 `DataScopeTest` 也全部通過，代表以下既有 assigned visibility 邏輯未被破壞：

- worker 只能看到自己的 dispatches。
- crew leader 可看到指派工班 workers。
- worker 可透過 dispatch assignment 看到 project。
- crew leader 可透過 crew dispatch assignment 看到 project。
- 無任何 view scope 的使用者仍被 forbidden。

## 5. 是否仍有未修 Project 子資源風險

仍建議下一輪檢查 Project 子資源與關聯 action。

本次只修補 `ProjectController` 中直接 route-model-bound 的 Project actions：

- `show`
- `edit`
- `update`
- `destroy`
- `invoicePdf`

仍建議後續審查：

- Project change orders 是否能透過 change order ID 存取非可見 project。
- Dispatches 是否能透過 dispatch ID 存取非可見 project。
- Progress logs 是否能透過 progress log ID 存取非可見 project。
- Financial records 是否能透過 financial record ID 間接暴露 project。
- Inventory transactions 是否能透過 project relation 間接暴露 project。
- Quotations linked to project 是否有跨 project direct object access。

## Deployment Note

未部署 Production。建議先進 staging 驗證 assigned user 的實際操作流程，再處理既有 `ExampleTest` 問題與其他 Project 子資源 IDOR 審查。
