# Final Security Handoff

## 1. 本次已修補的 High Risk

## High Risk 1：任意檔案上傳

- 原風險：報價附件上傳僅驗證 `file` 與 `max:10240`，未限制副檔名或 MIME type。
- 攻擊情境：具備報價更新權限的使用者可上傳 PHP、HTML、SVG 或其他腳本型檔案到 public disk，造成 stored XSS、惡意檔案散布，或在錯誤伺服器設定下擴大成檔案執行風險。
- 修補內容：
  - 報價附件新增副檔名白名單。
  - 報價附件新增 MIME type 白名單。
  - 目前允許：PDF、JPEG、PNG、WebP、Word、Excel。
- 已新增測試：
  - `shell.php` 上傳會被拒絕。
  - `document_attachments` 不會新增紀錄。

## High Risk 2：角色權限提升

- 原風險：使用者建立/更新權限與角色指派耦合。具備 `security.users.create.tenant` 或 `security.users.update.tenant` 的 delegated user 可能透過 `roles[]` 指派 admin role。
- 攻擊情境：低權限管理者修改他人帳號並送出 admin role ID，達成權限提升。
- 修補內容：
  - 新增角色同步前檢查。
  - 若角色清單有新增、移除、替換，必須具備 `security.roles.assign_capabilities.tenant`。
  - 若角色清單未變更，仍允許一般使用者資料更新。
- 已新增測試：
  - 只有 user update 權限、沒有 role assignment 權限的使用者，嘗試指派 admin role 會得到 403。
  - `role_user` 不會新增 admin role 關聯。

## 2. 尚未覆蓋的延伸 Case

- 任意檔案上傳：
  - SVG 上傳與 SVG 內嵌 script。
  - HTML 檔案上傳。
  - 副檔名與 MIME type 不一致的 spoofing，例如 `.jpg` 內容實際為 HTML/PHP。
  - polyglot file，例如同時可被視為圖片與腳本的檔案。
  - 大量檔案、重複檔名、特殊 Unicode 檔名。
  - 上傳後的直接 public URL 存取風險。
  - 敏感附件未經授權下載的風險，因目前仍使用 public disk。

- 角色權限提升：
  - 建立新使用者時同時指派 admin/owner role 的 forbidden case。
  - 移除角色、替換多個角色、送出重複 role ID 的 case。
  - delegated user 不變更 roles、只更新姓名/email/password 的成功 case。
  - 角色 ID 枚舉與不存在 role ID 的 validation case。
  - 若未來新增 API route，需要補 API 層同等測試。

- 其他仍未解風險：
  - Public registration 仍開啟。
  - 尚未建立 Laravel Policies。
  - 多數 object-level authorization 仍依賴 controller 與 `DataScope` 約定。
  - Docker compose 發布多個基礎服務 port，僅適合 local development。

## 3. 建議下一輪測試項目

1. 上傳安全測試
   - 測試 `.html`, `.svg`, `.php`, `.phar`, `.phtml`, `.js`, `.zip` 是否全部被拒絕。
   - 測試副檔名/MIME 不一致的 spoofing。
   - 測試允許類型 PDF、JPG、PNG、WebP、DOCX、XLSX 是否仍可正常上傳。
   - 測試上傳後 URL 是否能被未授權使用者直接存取。

2. 角色權限測試
   - 測試 create user 時沒有 `security.roles.assign_capabilities.tenant` 不可指派 role。
   - 測試 update user 時不變更 roles 可正常更新一般欄位。
   - 測試移除 role、替換 role、重複 role ID。
   - 測試 admin/owner 仍可正常建立與更新角色。

3. Object-level authorization
   - 針對 route-model binding 的 show/edit/update/delete 做 IDOR 測試。
   - 優先檢查 quotations、projects、financial records、users、roles、attachments。

4. Production readiness
   - 修正 `Tests\Feature\ExampleTest`。
   - 確認 production `.env` 安全設定。
   - 執行完整 CI。
   - Staging 做手動驗證。

## 4. 是否建議部署 Staging

建議部署 staging，但不建議直接部署 production。

原因：

- 兩個 High risk 的核心修補已完成，且目標測試通過。
- 這次修補有明確行為變更：
  - 某些附件類型會被拒絕。
  - 沒有角色指派 capability 的帳號不能變更 roles。
- 需要 staging 驗證實際業務流程是否依賴目前被拒絕的檔案類型，例如 `.csv`, `.txt`, `.zip`。
- 全量測試仍有一個既有 `ExampleTest` 失敗，雖然與本次修補無關，但 production 前仍應處理。

## 5. Production 部署前必要條件

- Code review 通過。
- 修正或正式豁免 `Tests\Feature\ExampleTest` 失敗。
- 完整 CI 通過。
- Staging 驗證通過：
  - PDF/圖片/Word/Excel 上傳成功。
  - PHP/HTML/SVG 上傳失敗。
  - admin 可正常建立使用者並指派角色。
  - delegated user 不可修改 roles。
  - delegated user 可更新不涉及 roles 的一般欄位。
- 確認附件白名單符合業務需求。
- 確認 production 不直接使用 public registration，或已明確接受該風險。
- 確認 production `.env`：
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - strong `APP_KEY`
  - 正式 DB/storage/mail credentials
  - HTTPS 與 session/cookie 設定正確
- 備份 database 與 storage。
- 準備 rollback plan。
- 部署後監控：
  - Laravel logs
  - Web server logs
  - 上傳失敗率
  - 使用者/角色管理 activity logs
  - 403/422 response 是否異常增加
