# AGENT.md

給在這個專案工作的 coding agent 使用。請先讀完這份文件，再動手改程式。

## 專案概況

Tinhouse Engineering Backend 是 Laravel 12 + React/Inertia + PostgreSQL 的工程管理系統，主要處理板金/鋼構工程的 CRM、專案、報價、庫存、派工、出勤、設備、採購、財務與權限管理。

主要技術棧：

- 後端：PHP 8.4、Laravel 12、Inertia Laravel、Sanctum
- 前端：React 18、Inertia React、Vite、Tailwind CSS、Headless UI、lucide-react
- 資料庫/服務：PostgreSQL、Redis、MinIO、Mailpit、Adminer
- PDF：Spatie Browsershot + Chromium
- 測試：PHPUnit / Laravel Feature tests

## 常用指令

優先使用 Docker compose，因為 README 的開發環境以容器為準。

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose run --rm node npm install
```

開發前端畫面時啟動 Vite：

```bash
docker compose run --rm --service-ports node npm run dev -- --host 0.0.0.0
```

常用檢查：

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose run --rm node npm run build
```

如果不在容器內執行，Composer 也有本機 scripts：

```bash
composer test
composer dev
npm run build
```

## 專案結構

- `app/Http/Controllers`：主要頁面與資源流程控制器。
- `app/Http/Requests`：表單驗證與授權前置檢查。
- `app/Models`：Eloquent models。
- `app/Auth`：capability authorizer 與資料可見範圍邏輯。
- `app/Services`：跨 controller 的業務服務，例如設定與報價模板計算。
- `app/Support`：共用輔助類別，例如 activity logging。
- `routes/web.php`：Inertia web routes 與 capability middleware。
- `resources/js/Pages`：Inertia React pages，依功能模組分資料夾。
- `resources/js/Components`：共用 React components。
- `resources/js/lib/authorization.js`：前端 capability 常數與 `can`/`canAny` helper。
- `resources/views/pdf`：PDF Blade templates。
- `database/migrations`：資料表與 RBAC/schema 演進。
- `database/seeders`：RBAC、管理者、demo data 與報價模板資料。
- `tests/Feature`：主要功能與安全測試。
- `docs/security`：安全稽核、修復與交接文件。

## 開發規則

- 遵循既有 Laravel/Inertia 寫法；不要為單一改動引入新的架構或大型抽象。
- 新增或修改資源流程時，通常需要同步檢查 controller、FormRequest、model relationship、route、Inertia page、feature test。
- Route 必須放在適當的 `auth` group 內，並使用 capability middleware 保護。
- 前端只負責隱藏/顯示操作入口；真正的權限與資料範圍必須在後端執行。
- 前端 capability 字串若有新增，請同步更新 `resources/js/lib/authorization.js`。
- 使用既有 UI 元件與頁面模式，CRUD 頁面保持一致的 Index/Create/Edit/Show 結構。
- PHP 格式以 Laravel Pint 為準。
- 不要提交 `.env`、本機憑證、產生的暫存檔或容器資料。

## 權限與資料範圍

這個專案對權限很敏感。改動時特別注意：

- Capability 格式大多是 `{domain}.{resource}.{action}.{scope}`，例如 `projects.projects.view.tenant`。
- `tenant`、`assigned`、`own` 是不同可見範圍，不可混用。
- `EnsureUserHasCapability` 只檢查能力字串；資料列可見性仍要在 controller/query 層處理。
- 與專案相關的子資源要避免 IDOR：即使使用者有子資源 view/update/delete capability，也不能透過直接猜 ID 存取不可見專案底下的資料。
- 參考 `app/Auth/DataScope.php` 與 `tests/Feature/ProjectSubresourceIdorTest.php` 的模式。
- 新增敏感資源或 bound action 時，請加 feature test 驗證 403 與資料未被修改。

## 測試策略

改動後請依影響範圍跑測試：

- 純 PHP 邏輯或 controller 修改：跑對應 `tests/Feature/*Test.php`。
- 權限/資料範圍修改：跑相關 feature test，至少包含 IDOR / DataScope / RBAC 測試。
- 前端 build 相關修改：跑 `docker compose run --rm node npm run build`。
- 全專案重要改動：跑 `docker compose exec app php artisan test`。

常見測試指令：

```bash
docker compose exec app php artisan test --filter=ProjectSubresourceIdorTest
docker compose exec app php artisan test --filter=DataScopeTest
docker compose exec app php artisan test
```

## PDF 與檔案

- PDF 產生依賴 Chromium/Browsershot，容器內路徑通常是 `/usr/bin/chromium`。
- `.env.example` 內有 `DOCUMENT_PDF_RENDERER`、`DOCUMENT_PDF_DISPOSITION` 與 Browsershot 相關設定。
- 本機檔案/附件使用 MinIO/S3 設定；不要假設 local disk 是正式儲存。

## 重要文件

需要理解背景時優先看：

- `README.md`：開發環境、服務、常用指令。
- `docs/project/project.md` 與 `docs/project/siteFunc.md`：產品/功能背景。
- `docs/security/README.md`：安全文件入口。
- `docs/security/audits/object-authorization-audit.md`：物件授權稽核。
- `docs/security/testing/test-coverage-summary.md`：安全測試覆蓋概況。

## 給 agent 的工作習慣

- 改檔前先用 `rg` 找既有模式。
- 小心目前工作樹可能已有使用者修改；不要 revert 不是自己造成的變更。
- 修改共享流程時，優先加或更新測試。
- 回報時說明改了哪些檔案、跑了哪些測試，以及任何未跑測試的原因。
