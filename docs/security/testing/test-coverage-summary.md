# Test Coverage Summary

## Scope

本輪只新增/補強測試，未修改 Controller、Service、Model 等正式程式邏輯。

## 修改的測試檔案

- `tests/Feature/QuotationManagementTest.php`
- `tests/Feature/UserManagementTest.php`
- `tests/Feature/RoleManagementTest.php`
- `tests/Feature/SecurityIdorTest.php`

## 新增覆蓋：上傳安全

## 已覆蓋項目

- 拒絕 `.html`
- 拒絕 `.svg`
- 拒絕 `.php`
- 拒絕 `.phar`
- 拒絕 `.phtml`
- 拒絕 `.js`
- 拒絕 `.zip`
- 拒絕 MIME spoofing：檔名為 `.jpg`，但 MIME 為 `text/html`
- 允許正常業務檔案：
  - PDF
  - JPG
  - PNG
  - WebP
  - DOCX
  - XLSX

## 測試結果

- `QuotationManagementTest`: PASS
- 相關測試：
  - `test_quotation_attachment_rejects_executable_files`
  - `test_quotation_attachment_rejects_disallowed_file_extensions`
  - `test_quotation_attachment_rejects_mime_spoofing`
  - `test_quotation_attachment_allows_business_document_and_image_types`

## 判斷

任意檔案上傳 High risk 的主要測試覆蓋已補強。測試確認不允許腳本型/壓縮檔/未知高風險附件進入 `document_attachments`，且允許類型仍能正常建立附件紀錄與 storage 檔案。

## 新增覆蓋：角色權限

## 已覆蓋項目

- create user 時，沒有 `security.roles.assign_capabilities.tenant` 不可指派 role。
- update user 時，roles 不變更可正常更新一般欄位。
- 沒有 role assignment capability 時不可移除 role。
- 沒有 role assignment capability 時不可替換 role。
- admin 可送出重複 role ID，且不會產生重複 pivot assignment。
- owner 可建立與更新 custom role capabilities。
- 既有 admin 建立/更新 role 測試仍通過。

## 測試結果

- `UserManagementTest`: PASS
- `RoleManagementTest`: PASS
- 相關新增測試：
  - `test_user_manager_without_role_assignment_capability_cannot_assign_roles_on_create`
  - `test_user_manager_without_role_assignment_capability_can_update_profile_fields_when_roles_do_not_change`
  - `test_user_manager_without_role_assignment_capability_cannot_remove_or_replace_roles`
  - `test_admin_can_submit_duplicate_role_ids_without_duplicate_assignments`
  - `test_owner_can_create_and_update_custom_role_capabilities`

## 判斷

角色權限提升 High risk 的測試覆蓋已補強。測試確認 delegated user 不能透過 create/update user 竄改 roles，但可在 roles 不變時更新一般欄位；admin/owner 的合法角色管理流程仍可運作。

## 新增覆蓋：IDOR / Direct Object Access

## 已覆蓋項目

- quotations：沒有 quotation view capability 的使用者不可直接存取 `quotations.show`。
- projects：只有 `projects.projects.view.assigned` 的使用者不可直接存取未指派 project。
- financial records：沒有 financial record view capability 的使用者不可直接存取 `financial-records.show`。
- users：沒有 user view capability 的使用者不可直接存取 `users.show`。
- roles：沒有 role view capability 的使用者不可直接存取 `roles.show`。
- attachments：沒有 quotation update capability 的使用者不可直接用 attachment ID 刪除附件。

## 測試結果

- `SecurityIdorTest`: FAIL
- 5 passed, 1 failed

失敗測試：

- `test_project_assigned_scope_cannot_directly_view_unassigned_project`
- Expected: 403
- Actual: 200

## 失敗原因

`routes/web.php` 的 `projects.show` route 允許以下任一 capability：

- `projects.projects.view.tenant`
- `projects.projects.view.assigned`

但 `ProjectController::show` 沒有使用 `DataScope::projects(...)` 驗證目前使用者是否真的可見該 project。結果是只要使用者具備 `projects.projects.view.assigned`，即可透過猜測或取得 project ID 直接開啟未指派 project 的 show 頁。

## 建議修補方向，先不要修

- 在 `ProjectController::show`, `edit`, `update`, `destroy`, `invoicePdf` 等 route-model-bound actions 前加入一致的 object-level visibility check。
- 可新增 private method，例如 `ensureVisible(Request $request, Project $project)`：
  - 使用 `$this->dataScope->projects(Project::query(), $request->user())->whereKey($project->id)->exists()`
  - 不存在則 `abort(403)`
- 後續也應檢查 project 相關子資源是否有同類 direct object access 風險。

## 執行指令與結果

語法檢查：

- `docker compose exec -T app php -l tests/Feature/QuotationManagementTest.php`
- `docker compose exec -T app php -l tests/Feature/UserManagementTest.php`
- `docker compose exec -T app php -l tests/Feature/RoleManagementTest.php`
- `docker compose exec -T app php -l tests/Feature/SecurityIdorTest.php`
- Result: PASS

目標測試：

- `docker compose exec -T app php artisan config:clear && docker compose exec -T app php artisan test tests/Feature/QuotationManagementTest.php tests/Feature/UserManagementTest.php tests/Feature/RoleManagementTest.php tests/Feature/SecurityIdorTest.php`
- Result: FAIL
- Summary: 50 passed, 1 failed, 294 assertions
- 失敗原因：Project assigned-scope direct object access IDOR。

## 下一步建議

1. 先不要部署 production。
2. 可部署 staging 觀察上傳與角色管理流程，但需標記 Project IDOR 為待修 High/Medium 風險。
3. 下一輪修補建議優先處理 `ProjectController` direct object access。
4. 修補後重跑：
   - `SecurityIdorTest`
   - `ProjectManagementTest`
   - `DataScopeTest`
   - 全量 `php artisan test`
