# Fix Summary

## 修改檔案

- `system-overview.md`
- `security-audit.md`
- `security-fix-plan.md`
- `security-review-2.md`
- `fix-summary.md`
- `app/Http/Controllers/QuotationController.php`
- `app/Http/Controllers/UserController.php`
- `tests/Feature/QuotationManagementTest.php`
- `tests/Feature/UserManagementTest.php`

## 修補內容

1. 報價附件上傳限制
   - 將 `QuotationController::storeAttachment` 從僅限制 `file|max:10240` 改為明確允許 PDF、圖片、Word、Excel 類型。
   - 阻擋 PHP、HTML、SVG、腳本型或未知副檔名檔案進入 public storage。

2. 使用者角色指派權限分離
   - 在 `UserController` 同步 `roles[]` 前檢查 `security.roles.assign_capabilities.tenant`。
   - 若角色清單沒有變更，允許一般 user update 照常進行。
   - 若建立或更新時要新增、移除、替換角色，必須具備角色指派 capability。

3. 回歸測試
   - 新增測試：報價附件拒絕 `shell.php`。
   - 新增測試：只有 user update 權限、沒有角色指派權限的使用者不能把 admin role 指派給他人。

## 測試結果

- `docker compose exec -T app php -l app/Http/Controllers/UserController.php`: PASS
- `docker compose exec -T app php -l app/Http/Controllers/QuotationController.php`: PASS
- `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/UserManagementTest.php tests/Feature/QuotationManagementTest.php`: PASS, 29 tests / 159 assertions
- `docker compose exec -T app php artisan route:list --except-vendor`: PASS, 181 routes listed
- `docker compose exec -T node npm run build`: PASS
- `docker compose exec -T app php artisan config:cache`: PASS

## 測試限制與失敗

- Local host `php` 不存在，因此 Laravel 指令改用 Docker app container 執行。
- Local host `npm run build` 失敗，原因是 host Node 為 `v10.15.0`，無法解析 Vite 7 的 Node 語法；Docker node container 為 `v24.15.0` 且 build 成功。
- `docker compose exec -T app php artisan test`: 178 passed, 1 failed。
  - 失敗測試：`Tests\Feature\ExampleTest`
  - 原因：該測試未使用 migration/seed，直接 GET `/` 時 Inertia shared props 讀取 `system_settings`，SQLite memory DB 尚無 `system_settings` table。
  - 與本次修補的上傳限制、角色同步權限無關。

## 剩餘風險

- Public registration 仍開啟，內部系統建議改成邀請制或 admin 建立帳號。
- 尚未建立 Laravel Policies，未來新增 route-model-bound action 時仍有 IDOR 漏洞風險。
- 多數租戶/資料範圍控制依賴 controller/data-scope 約定，建議後續統一成 policy 或 scoped binding。
- Docker compose 仍發布多個基礎服務 port，僅適合 local development。

## 後續建議

- 修正或移除 `Feature\ExampleTest`，或讓它使用 `RefreshDatabase`/migration。
- 將 quotation attachment 改為 private disk + 授權下載 route。
- 生產環境停用 public registration。
- 補齊 Policies：View/Create/Update/Delete。
- 在 staging/production 禁止直接套用本次修改；先經過 code review、完整 CI、備份與部署計畫。
