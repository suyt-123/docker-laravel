目前專案已經不只是 MVP 骨架，功能其實滿完整了。依程式碼來看，主要功能與連動如下。

**主流程**
```text
客戶
→ 工程專案
→ 報價單 / 報價模板
→ 報價核准 / 客戶確認
→ 轉工程
→ 派工 / 工班 / 工人
→ 工程日誌 / 照片 / 出勤
→ 材料庫存 / 採購 / 機具
→ 財務紀錄 / 請款 PDF
→ 活動紀錄 / 權限控管
```

**1. 客戶管理**
功能：客戶 CRUD、客戶聯絡人管理。  
連動：

```text
Customer
├─ CustomerContact
├─ Project
└─ Quotation
```

客戶會被工程、報價單引用，所以如果已有工程或報價，不能隨便刪。

**2. 工程專案管理**
功能：工程 CRUD、工程狀態、負責人、工班、地址、GPS、合約金額、預估成本、實際成本、毛利。  
連動：

```text
Project
├─ Customer
├─ Manager(User)
├─ WorkCrew
├─ Quotation
├─ Dispatch
├─ ProgressLog / ProgressPhoto
├─ AttendanceRecord
├─ InventoryTransaction
├─ FinancialRecord
├─ ProjectChangeOrder
└─ Invoice PDF / DocumentVersion
```

工程是整個系統的中心。

**3. 報價單管理**
功能：報價 CRUD、報價項目、PDF 匯出、附件上傳、文件版本、作廢、重開、轉工程。  
流程：

```text
draft 草稿
→ pending_approval 待主管核准
→ approved 已核准
→ sent_to_customer 已送客戶
→ customer_accepted 客戶接受
→ convert_project 轉工程
```

其他動作：

```text
reject 拒絕
decline_customer 客戶拒絕
void 作廢
reopen 重開新版本
```

連動：

```text
Quotation
├─ Customer
├─ Project 可選
├─ QuotationItem
│  └─ Material 可選
├─ QuotationTemplate 可選
├─ Creator(User)
├─ Approver(User)
├─ DocumentVersion
├─ DocumentAttachment
├─ reopenedFrom
└─ supersededBy
```

PDF 是目前比較吃資源的功能，會用 Browsershot + Chromium。

**4. 報價模板**
功能：報價模板 CRUD、模板項目、套用計算。  
連動：

```text
QuotationTemplate
└─ QuotationTemplateItem
   └─ Material
```

用途是讓常用報價項目可以重複套用。

**5. 工程變更追加單**
功能：追加單 CRUD、送審、主管核准、客戶確認、取消、建立追加報價、轉追加款。  
流程：

```text
draft 草稿
→ pending_approval 待主管核准
→ approved 主管已核准
→ customer_confirmed 客戶已確認
→ converted 已轉追加款
```

連動：

```text
ProjectChangeOrder
├─ Project
├─ Quotation 可選，正式追加報價
├─ FinancialRecord 可選，轉成追加款
├─ Creator(User)
└─ Approver(User)
```

如果追加單設定「需要正式報價」，必須先建立並核准追加報價，才能客戶確認或轉財務紀錄。

**6. 材料與庫存**
功能：材料 CRUD、材料分類、庫存異動 CRUD。  
連動：

```text
Material
├─ MaterialCategory
├─ QuotationItem
├─ QuotationTemplateItem
├─ PurchaseOrderItem
└─ InventoryTransaction
```

庫存異動會連到：

```text
InventoryTransaction
├─ Material
├─ Project 可選
├─ PurchaseOrderItem 可選
└─ Creator(User)
```

採購收料時會自動建立 `purchase_in` 庫存異動，並增加材料目前庫存。

**7. 供應商與採購**
功能：供應商 CRUD、採購單 CRUD、採購項目、到貨驗收。  
採購狀態：

```text
draft 草稿
sent 已送出
partially_received 部分到貨
completed 已完成
cancelled 已取消
```

連動：

```text
Supplier
└─ PurchaseOrder
   ├─ PurchaseOrderItem
   │  ├─ Material
   │  └─ InventoryTransaction
   └─ Creator(User)
```

到貨驗收時：

```text
PurchaseOrderItem.received_quantity 增加
Material.current_stock 增加
InventoryTransaction 自動建立
PurchaseOrder.status 自動更新
```

**8. 工班與工人**
功能：工班 CRUD、工人 CRUD、工人綁定使用者帳號。  
連動：

```text
WorkCrew
├─ Worker
├─ Project
└─ Dispatch

Worker
├─ WorkCrew
├─ User 可選
├─ Dispatch many-to-many
├─ ProgressLog
└─ AttendanceRecord
```

這裡也會影響資料權限，例如工人只看自己被派到的工程/派工。

**9. 派工管理**
功能：派工 CRUD、派工排程頁、指派工班與工人。  
連動：

```text
Dispatch
├─ Project
├─ WorkCrew
├─ Creator(User)
├─ Worker many-to-many
├─ ProgressLog
├─ ProgressPhoto
└─ AttendanceRecord
```

派工是現場作業的核心，後面的出勤與工程日誌都會掛在派工上。

**10. 出勤打卡**
功能：出勤紀錄列表、建立、查看、刪除；支援 GPS、照片、異常標記、工時計算。  
連動：

```text
AttendanceRecord
├─ Dispatch
├─ Project
├─ Worker
└─ User
```

建立打卡時會檢查：

```text
使用者可看該派工
工人有被指派到該派工
GPS 與工程座標距離
是否重複打卡
是否需要注意異常
```

**11. 工程日誌與進度照片**
功能：工程日誌 CRUD、進度百分比、工作內容、問題紀錄、語音文字欄位、照片上傳/刪除。  
連動：

```text
ProgressLog
├─ Project
├─ Dispatch 可選
├─ Worker 可選
├─ Creator(User)
└─ ProgressPhoto
   ├─ Project
   ├─ Dispatch
   └─ Uploader(User)
```

這個功能會吃 storage，若省成本可以先關照片上傳。

**12. 機具設備管理**
功能：設備分類 CRUD、設備 CRUD、設備交易紀錄。  
設備交易會改變設備目前狀態。  
連動：

```text
Equipment
├─ EquipmentCategory
├─ currentProject
├─ currentWorker
├─ currentWorkCrew
└─ EquipmentTransaction
```

交易類型包含類似：

```text
借出
歸還
指派工程
轉移工程
維修
報廢
```

建立交易後會更新設備目前所在工程、工人、工班與狀態。

**13. 財務紀錄**
功能：財務紀錄 CRUD、收款/支出/追加款狀態管理、工程請款 PDF。  
連動：

```text
FinancialRecord
├─ Project
└─ ProjectChangeOrder 可選
```

工程頁可以選待收或逾期款項產生請款單 PDF。

**14. 儀表板**
功能：Dashboard。  
目前會聚合系統資料，例如工程、報價、財務、派工等摘要，作為登入後首頁。

**15. 使用者、角色、權限、多租戶**
功能：使用者 CRUD、角色 CRUD、角色權限矩陣、Capability 權限控管、Tenant 資料範圍。  
連動：

```text
User
├─ Role many-to-many
├─ Tenant many-to-many
├─ Worker 可選
├─ managedProjects
├─ createdQuotations
├─ approvedQuotations
├─ createdDispatches
└─ activityLogs
```

每個 route 幾乎都有 capability middleware，例如：

```text
crm.customers.view.tenant
projects.projects.view.assigned
sales.quotations.approve.tenant
field.dispatches.view.own
```

所以這套系統已經有細緻權限設計。

**16. 系統設定**
功能：系統設定編輯。  
連動：

```text
SystemSetting
├─ Tenant
└─ updater(User)
```

PDF 裡的公司資訊、付款條件等應該會從設定帶出。

**17. 活動紀錄 / 稽核**
功能：活動紀錄列表與詳細頁。  
目前多數重要 model 都有 observer 自動記錄：

```text
客戶、工程、報價、報價項目、追加單、採購、庫存、設備、派工、工班、工人、日誌、照片、財務、使用者、角色、系統設定
```

另外一些 workflow 也會手動記錄，例如：

```text
報價 PDF 匯出
採購驗收
追加單轉財務紀錄
追加單建立報價
```

**18. 認證與個人資料**
功能：登入、註冊、忘記密碼、重設密碼、email verification、個人資料、改密碼、刪除帳號。  
這是 Laravel Breeze/Inertia 的標準認證功能。

**目前沒有大量背景 Queue**
目前有 `QUEUE_CONNECTION=database` 設定，但沒有實際 Job class，也沒有大量 `dispatch()` 背景任務。  
所以第一版部署可以先不用開 queue worker。

**最吃資源的功能**
目前真正要注意的是：

```text
報價 PDF
請款 PDF
照片/附件上傳
PostgreSQL
Redis cache
```

如果要低成本部署，可以先開核心資料管理，暫時關閉 PDF、照片、附件這幾類功能。