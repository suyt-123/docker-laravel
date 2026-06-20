# Release Review

## Git Diff 摘要

- `app/Http/Controllers/QuotationController.php`
  - 報價附件上傳從 `file|max:10240` 擴充為明確白名單。
  - 允許：`pdf`, `jpg`, `jpeg`, `png`, `webp`, `doc`, `docx`, `xls`, `xlsx`。
  - 同時檢查副檔名與 MIME type。

- `app/Http/Controllers/UserController.php`
  - 注入 `CapabilityAuthorizer`。
  - 在建立/更新使用者同步 `roles[]` 前，新增 `ensureCanSyncRoles()`。
  - 若角色清單有變更，必須具備 `security.roles.assign_capabilities.tenant`。
  - 若角色清單未變更，保留一般使用者資料更新流程。

- `tests/Feature/QuotationManagementTest.php`
  - 新增測試：上傳 `shell.php` 應被 validation 擋下。
  - 驗證不會建立 `document_attachments` 紀錄。

- `tests/Feature/UserManagementTest.php`
  - 新增測試：只有 `security.users.update.tenant`、沒有 `security.roles.assign_capabilities.tenant` 的管理者，不能把 admin role 指派給他人。
  - 驗證 `role_user` 不會產生 admin role 關聯。

## 實際修改過的檔案

- `app/Http/Controllers/QuotationController.php`
- `app/Http/Controllers/UserController.php`
- `tests/Feature/QuotationManagementTest.php`
- `tests/Feature/UserManagementTest.php`
- `system-overview.md`
- `security-audit.md`
- `security-fix-plan.md`
- `security-review-2.md`
- `fix-summary.md`
- `release-review.md`

## 既有功能影響評估

- 報價附件上傳
  - 會影響既有功能。
  - 使用者仍可上傳常見業務文件與圖片。
  - 以前若上傳非白名單檔案，例如 `.txt`, `.csv`, `.zip`, `.svg`, `.html`, `.php`，現在會被拒絕。
  - 這是安全性收斂，屬預期行為改變。

- 使用者建立/更新
  - 可能影響既有功能。
  - 具備 admin/owner 或含 `security.roles.assign_capabilities.tenant` 的角色，既有建立使用者並指派角色流程維持可用。
  - 只有 `security.users.create.tenant` 或 `security.users.update.tenant` 的角色，若嘗試新增、移除、替換角色，現在會收到 403。
  - 若只是更新姓名、email、密碼，且 roles 未變更，流程維持可用。

- 測試檔
  - 不影響 production runtime。
  - 增加兩個 High risk 回歸測試。

- 報告文件
  - 不影響 runtime。

## High Risk 覆蓋檢查

## 任意檔案上傳

- 覆蓋狀態：已覆蓋核心攻擊路徑。
- 測試：`test_quotation_attachment_rejects_executable_files`
- 測試做了以下驗證：
  - 使用有權限的 authenticated user。
  - 對實際上傳 route `quotations.attachments.store` 發送 `shell.php`。
  - MIME 設為 `application/x-php`。
  - 期待 validation error 出現在 `file`。
  - 期待 `document_attachments` 資料表沒有新增紀錄。
- 尚未覆蓋：
  - SVG/HTML polyglot 檔案。
  - 副檔名與 MIME 不一致的 spoofing case。
  - 私有下載授權，因目前仍使用 public disk。

## 角色權限提升

- 覆蓋狀態：已覆蓋主要權限提升路徑。
- 測試：`test_user_manager_without_role_assignment_capability_cannot_assign_roles`
- 測試做了以下驗證：
  - 建立只有 `security.users.view.tenant` 與 `security.users.update.tenant` 的自訂角色。
  - 該使用者嘗試把目標使用者指派為 admin role。
  - 期待 response 為 403。
  - 期待 `role_user` 沒有 admin role 關聯。
- 尚未覆蓋：
  - 建立新使用者時同時指派高權限 role 的 forbidden case。
  - 移除角色或替換多角色的細部 case。
  - API route case，因目前未發現 dedicated API routes。

## 測試結果

- `docker compose exec -T app php -l app/Http/Controllers/UserController.php`
  - PASS

- `docker compose exec -T app php -l app/Http/Controllers/QuotationController.php`
  - PASS

- `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/UserManagementTest.php tests/Feature/QuotationManagementTest.php`
  - PASS
  - 29 tests, 159 assertions

- `docker compose exec -T app php artisan route:list --except-vendor`
  - PASS
  - 181 routes listed

- `docker compose exec -T node npm run build`
  - PASS

- `docker compose exec -T app php artisan config:cache`
  - PASS

- `docker compose exec -T app php artisan test`
  - FAIL
  - 178 passed, 1 failed
  - 失敗項目：`Tests\Feature\ExampleTest`
  - 失敗原因：測試直接 GET `/`，但未使用 migration/seed；Inertia shared props 讀取 `system_settings` 時，SQLite memory database 尚無 `system_settings` table。
  - 判斷：與本次 High risk 修補無直接關聯。

## ExampleTest 失敗修正建議

先不要修改，建議選一個方向：

1. 最小修正：在 `tests/Feature/ExampleTest.php` 加上 `RefreshDatabase`。
   - 優點：符合目前多數 Feature tests 模式。
   - 風險：測試會跑 migration，時間略增。

2. 改測不依賴 Inertia shared props 的健康檢查 route，例如 `/up`。
   - 優點：保留 smoke test 意義，避免首頁依賴資料表。
   - 風險：測試語意從「首頁可回應」改成「app health route 可回應」。

3. 在 `SettingService` 或 Inertia share 層對缺少資料表做 fallback。
   - 優點：提升未 migrate 環境韌性。
   - 風險：這是 runtime 行為改動，範圍比測試修正大，不建議作為第一選項。

建議採用選項 1，若團隊希望 smoke test 不依賴 DB，則採用選項 2。

## 未解風險

- Quotation attachments 仍存放於 public disk，敏感文件建議改為 private disk + authorized download route。
- Public registration 仍開啟，內部系統建議 production 停用或改 invitation/admin-created onboarding。
- 尚未建立 Laravel Policies，未來新增 route-model-bound actions 時仍可能漏掉 object-level authorization。
- 部分資料範圍控制依賴 controller + `DataScope` 約定，建議後續統一成 policy/scoped binding。
- Docker compose 發布 Postgres、Redis、MinIO、Adminer 等服務 port，僅適合 local development。
- 本機 Node 是 `v10.15.0`，本機 `npm run build` 無法執行 Vite 7；目前需使用 Docker node container。
- 本機沒有 `php` binary；Laravel 指令目前需透過 Docker app container。

## 上線前檢查清單

- 確認不直接部署 Production，先進 staging。
- Code review 通過，特別確認允許附件類型符合業務需求。
- 決定是否允許 `.csv`, `.txt`, `.zip` 等目前被拒絕的檔案類型。
- 修正或處理 `Tests\Feature\ExampleTest` 失敗。
- 全量 CI 通過。
- Staging 手動驗證：
  - PDF 上傳成功。
  - 圖片上傳成功。
  - PHP/HTML/SVG 上傳被拒絕。
  - admin 建立使用者並指派角色成功。
  - 無 `security.roles.assign_capabilities.tenant` 的帳號更新一般欄位成功。
  - 無 `security.roles.assign_capabilities.tenant` 的帳號修改 roles 被 403。
- Production `.env` 確認：
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - strong `APP_KEY`
  - 強密碼與正式 storage/mail credentials
  - HTTPS/session/cookie 設定符合部署環境
- 備份資料庫與 storage。
- 確認 rollback plan。
- 部署後檢查 logs、上傳流程、使用者管理流程與 activity logs。
