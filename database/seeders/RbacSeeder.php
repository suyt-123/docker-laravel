<?php

namespace Database\Seeders;

use App\Models\Capability;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Seed default roles and capabilities.
     */
    public function run(): void
    {
        $capabilities = collect($this->capabilities())->mapWithKeys(function (array $capability) {
            $model = Capability::updateOrCreate(
                ['code' => $capability['code']],
                [
                    'tenant_id' => null,
                    'name' => $capability['name'],
                    'domain' => $capability['domain'],
                    'resource' => $capability['resource'],
                    'action' => $capability['action'],
                    'scope' => $capability['scope'],
                    'group' => $capability['group'],
                    'description' => $capability['description'] ?? null,
                    'is_system' => true,
                ],
            );

            return [$model->code => $model];
        });

        foreach ($this->roles() as $roleData) {
            $role = Role::updateOrCreate(
                ['code' => $roleData['code']],
                [
                    'tenant_id' => null,
                    'name' => $roleData['name'],
                    'description' => $roleData['description'] ?? null,
                    'is_system' => true,
                    'is_protected' => true,
                ],
            );

            $codes = $roleData['capabilities'] === ['*']
                ? $capabilities->keys()->all()
                : $roleData['capabilities'];

            $role->capabilities()->sync(
                $capabilities->only($codes)->pluck('id')->all(),
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function capabilities(): array
    {
        $modules = [
            ['core', 'dashboard', 'Dashboard'],
            ['crm', 'customers', '客戶管理'],
            ['projects', 'projects', '工程案件'],
            ['projects', 'change_orders', '工程變更追加單'],
            ['sales', 'quotations', '報價單'],
            ['sales', 'quotation_templates', '報價模板'],
            ['inventory', 'materials', '材料管理'],
            ['inventory', 'inventory_transactions', '庫存異動'],
            ['equipment', 'categories', '工具與機具分類'],
            ['equipment', 'equipment', '工具與機具資產'],
            ['equipment', 'transactions', '工具與機具交易'],
            ['purchasing', 'suppliers', '供應商管理'],
            ['purchasing', 'purchase_orders', '採購單'],
            ['field', 'dispatches', '派工管理'],
            ['field', 'attendance', 'GPS 打卡'],
            ['field', 'progress_logs', '工程日誌'],
            ['field', 'work_crews', '工班管理'],
            ['field', 'workers', '師傅管理'],
            ['finance', 'financial_records', '財務收款'],
            ['security', 'activity_logs', '操作紀錄'],
            ['security', 'users', '使用者管理'],
            ['security', 'roles', '角色權限'],
            ['system', 'settings', '系統設定'],
        ];

        $actions = [
            'view' => '查看',
            'create' => '新增',
            'update' => '編輯',
            'delete' => '刪除',
        ];

        $capabilities = [];

        foreach ($modules as [$domain, $resource, $moduleName]) {
            foreach ($actions as $action => $actionName) {
                $capabilities[] = [
                    'code' => "{$domain}.{$resource}.{$action}.tenant",
                    'name' => "{$moduleName} {$actionName}",
                    'domain' => $domain,
                    'resource' => $resource,
                    'action' => $action,
                    'scope' => 'tenant',
                    'group' => $moduleName,
                ];
            }
        }

        $capabilities[] = [
            'code' => 'sales.quotations.export_pdf.tenant',
            'name' => '報價單 匯出 PDF',
            'domain' => 'sales',
            'resource' => 'quotations',
            'action' => 'export_pdf',
            'scope' => 'tenant',
            'group' => '報價單',
        ];

        foreach ([
            ['sales.quotations.submit_review.tenant', '報價單 送審', 'submit_review'],
            ['sales.quotations.approve.tenant', '報價單 核准', 'approve'],
            ['sales.quotations.reject.tenant', '報價單 退回', 'reject'],
            ['sales.quotations.send_customer.tenant', '報價單 送客戶確認', 'send_customer'],
            ['sales.quotations.confirm_customer.tenant', '報價單 客戶確認', 'confirm_customer'],
            ['sales.quotations.convert_project.tenant', '報價單 轉工程案件', 'convert_project'],
            ['sales.quotations.void.tenant', '報價單 作廢', 'void'],
            ['sales.quotations.reopen.tenant', '報價單 重開版本', 'reopen'],
        ] as [$code, $name, $action]) {
            $capabilities[] = [
                'code' => $code,
                'name' => $name,
                'domain' => 'sales',
                'resource' => 'quotations',
                'action' => $action,
                'scope' => 'tenant',
                'group' => '報價單',
            ];
        }

        $capabilities[] = [
            'code' => 'projects.projects.view_financials.tenant',
            'name' => '工程案件 查看合約與毛利',
            'domain' => 'projects',
            'resource' => 'projects',
            'action' => 'view_financials',
            'scope' => 'tenant',
            'group' => '工程案件',
            'description' => '查看工程合約金額、預估成本、實際成本與毛利。',
        ];

        $capabilities[] = [
            'code' => 'projects.change_orders.convert_financial_record.tenant',
            'name' => '工程變更追加單 轉追加款',
            'domain' => 'projects',
            'resource' => 'change_orders',
            'action' => 'convert_financial_record',
            'scope' => 'tenant',
            'group' => '工程變更追加單',
            'description' => '將客戶已確認的工程變更追加單轉成追加款收款紀錄。',
        ];

        foreach ([
            ['projects.change_orders.submit_review.tenant', '工程變更追加單 送審', 'submit_review', '將草稿追加單送主管核准。'],
            ['projects.change_orders.approve.tenant', '工程變更追加單 主管核准', 'approve', '核准待審追加單。'],
            ['projects.change_orders.confirm_customer.tenant', '工程變更追加單 客戶確認', 'confirm_customer', '標記追加單已取得客戶確認。'],
            ['projects.change_orders.cancel.tenant', '工程變更追加單 取消', 'cancel', '取消尚未轉追加款的追加單。'],
            ['projects.change_orders.create_quotation.tenant', '工程變更追加單 建立追加報價', 'create_quotation', '由追加單建立正式追加報價單。'],
        ] as [$code, $name, $action, $description]) {
            $capabilities[] = [
                'code' => $code,
                'name' => $name,
                'domain' => 'projects',
                'resource' => 'change_orders',
                'action' => $action,
                'scope' => 'tenant',
                'group' => '工程變更追加單',
                'description' => $description,
            ];
        }

        $capabilities[] = [
            'code' => 'crm.customers.view_contact.tenant',
            'name' => '客戶管理 查看聯絡與識別資料',
            'domain' => 'crm',
            'resource' => 'customers',
            'action' => 'view_contact',
            'scope' => 'tenant',
            'group' => '客戶管理',
            'description' => '查看客戶電話、LINE、統編、地址、聯絡人與備註。',
        ];

        $capabilities[] = [
            'code' => 'security.roles.assign_capabilities.tenant',
            'name' => '角色權限 指派 capability',
            'domain' => 'security',
            'resource' => 'roles',
            'action' => 'assign_capabilities',
            'scope' => 'tenant',
            'group' => '角色權限',
        ];

        $capabilities[] = [
            'code' => 'finance.financial_records.export_pdf.tenant',
            'name' => '財務收款 匯出請款單 PDF',
            'domain' => 'finance',
            'resource' => 'financial_records',
            'action' => 'export_pdf',
            'scope' => 'tenant',
            'group' => '財務收款',
        ];

        $capabilities[] = [
            'code' => 'purchasing.purchase_orders.receive.tenant',
            'name' => '採購單 到貨驗收',
            'domain' => 'purchasing',
            'resource' => 'purchase_orders',
            'action' => 'receive',
            'scope' => 'tenant',
            'group' => '採購單',
        ];

        foreach ([
            ['projects.projects.view.assigned', '工程案件 查看指派案件', 'projects', 'projects', 'view', 'assigned', '工程案件'],
            ['field.dispatches.view.assigned', '派工管理 查看指派工班', 'field', 'dispatches', 'view', 'assigned', '派工管理'],
            ['field.dispatches.view.own', '派工管理 查看本人派工', 'field', 'dispatches', 'view', 'own', '派工管理'],
            ['field.attendance.view.assigned', 'GPS 打卡 查看指派工班', 'field', 'attendance', 'view', 'assigned', 'GPS 打卡'],
            ['field.attendance.view.own', 'GPS 打卡 查看本人紀錄', 'field', 'attendance', 'view', 'own', 'GPS 打卡'],
            ['field.progress_logs.view.assigned', '工程日誌 查看指派工班', 'field', 'progress_logs', 'view', 'assigned', '工程日誌'],
            ['field.progress_logs.view.own', '工程日誌 查看本人日誌', 'field', 'progress_logs', 'view', 'own', '工程日誌'],
            ['field.workers.view.assigned', '師傅管理 查看同工班師傅', 'field', 'workers', 'view', 'assigned', '師傅管理'],
            ['field.workers.view.own', '師傅管理 查看本人資料', 'field', 'workers', 'view', 'own', '師傅管理'],
        ] as [$code, $name, $domain, $resource, $action, $scope, $group]) {
            $capabilities[] = compact('code', 'name', 'domain', 'resource', 'action', 'scope', 'group');
        }

        return $capabilities;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roles(): array
    {
        return [
            [
                'code' => 'owner',
                'name' => '老闆',
                'capabilities' => ['*'],
            ],
            [
                'code' => 'admin',
                'name' => '系統管理員',
                'capabilities' => ['*'],
            ],
            [
                'code' => 'office',
                'name' => '行政',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'crm.customers.view.tenant', 'crm.customers.view_contact.tenant', 'crm.customers.create.tenant', 'crm.customers.update.tenant',
                    'projects.projects.view.tenant', 'projects.projects.create.tenant', 'projects.projects.update.tenant',
                    'projects.change_orders.view.tenant', 'projects.change_orders.create.tenant', 'projects.change_orders.update.tenant', 'projects.change_orders.submit_review.tenant', 'projects.change_orders.create_quotation.tenant', 'projects.change_orders.cancel.tenant',
                    'sales.quotations.view.tenant', 'sales.quotations.create.tenant', 'sales.quotations.update.tenant', 'sales.quotations.export_pdf.tenant', 'sales.quotations.submit_review.tenant', 'sales.quotations.send_customer.tenant', 'sales.quotations.confirm_customer.tenant', 'sales.quotations.void.tenant', 'sales.quotations.reopen.tenant',
                    'sales.quotation_templates.view.tenant', 'sales.quotation_templates.create.tenant', 'sales.quotation_templates.update.tenant', 'sales.quotation_templates.delete.tenant',
                    'inventory.materials.view.tenant',
                    'inventory.inventory_transactions.view.tenant',
                    'equipment.categories.view.tenant',
                    'equipment.equipment.view.tenant',
                    'equipment.transactions.view.tenant',
                    'purchasing.suppliers.view.tenant', 'purchasing.suppliers.create.tenant', 'purchasing.suppliers.update.tenant',
                    'purchasing.purchase_orders.view.tenant', 'purchasing.purchase_orders.create.tenant', 'purchasing.purchase_orders.update.tenant', 'purchasing.purchase_orders.receive.tenant',
                    'field.dispatches.view.tenant', 'field.dispatches.create.tenant', 'field.dispatches.update.tenant',
                    'field.attendance.view.tenant',
                    'field.progress_logs.view.tenant',
                    'field.work_crews.view.tenant',
                    'field.workers.view.tenant',
                    'system.settings.view.tenant',
                ],
            ],
            [
                'code' => 'sales',
                'name' => '業務',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'crm.customers.view.tenant', 'crm.customers.view_contact.tenant', 'crm.customers.create.tenant', 'crm.customers.update.tenant',
                    'projects.projects.view.tenant', 'projects.projects.create.tenant', 'projects.projects.update.tenant',
                    'projects.change_orders.view.tenant', 'projects.change_orders.create.tenant', 'projects.change_orders.update.tenant', 'projects.change_orders.submit_review.tenant', 'projects.change_orders.create_quotation.tenant', 'projects.change_orders.cancel.tenant',
                    'sales.quotations.view.tenant', 'sales.quotations.create.tenant', 'sales.quotations.update.tenant', 'sales.quotations.export_pdf.tenant', 'sales.quotations.submit_review.tenant', 'sales.quotations.send_customer.tenant', 'sales.quotations.confirm_customer.tenant', 'sales.quotations.void.tenant', 'sales.quotations.reopen.tenant',
                    'sales.quotation_templates.view.tenant',
                    'finance.financial_records.view.tenant',
                ],
            ],
            [
                'code' => 'purchasing',
                'name' => '採購',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'projects.projects.view.tenant',
                    'projects.change_orders.view.tenant',
                    'inventory.materials.view.tenant', 'inventory.materials.create.tenant', 'inventory.materials.update.tenant',
                    'inventory.inventory_transactions.view.tenant', 'inventory.inventory_transactions.create.tenant', 'inventory.inventory_transactions.update.tenant',
                    'equipment.categories.view.tenant',
                    'equipment.equipment.view.tenant',
                    'equipment.transactions.view.tenant',
                    'purchasing.suppliers.view.tenant', 'purchasing.suppliers.create.tenant', 'purchasing.suppliers.update.tenant',
                    'purchasing.purchase_orders.view.tenant', 'purchasing.purchase_orders.create.tenant', 'purchasing.purchase_orders.update.tenant', 'purchasing.purchase_orders.delete.tenant', 'purchasing.purchase_orders.receive.tenant',
                ],
            ],
            [
                'code' => 'site_manager',
                'name' => '工地主任',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'projects.projects.view.tenant', 'projects.projects.update.tenant',
                    'projects.change_orders.view.tenant', 'projects.change_orders.create.tenant', 'projects.change_orders.update.tenant', 'projects.change_orders.submit_review.tenant', 'projects.change_orders.confirm_customer.tenant',
                    'inventory.materials.view.tenant',
                    'inventory.inventory_transactions.view.tenant', 'inventory.inventory_transactions.create.tenant',
                    'equipment.equipment.view.tenant',
                    'equipment.transactions.view.tenant', 'equipment.transactions.create.tenant',
                    'purchasing.purchase_orders.view.tenant', 'purchasing.purchase_orders.receive.tenant',
                    'field.dispatches.view.tenant', 'field.dispatches.create.tenant', 'field.dispatches.update.tenant',
                    'field.attendance.view.tenant', 'field.attendance.create.tenant', 'field.attendance.update.tenant', 'field.attendance.delete.tenant',
                    'field.progress_logs.view.tenant', 'field.progress_logs.create.tenant', 'field.progress_logs.update.tenant', 'field.progress_logs.delete.tenant',
                    'field.work_crews.view.tenant',
                    'field.workers.view.tenant',
                ],
            ],
            [
                'code' => 'crew_leader',
                'name' => '工班負責人',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'projects.projects.view.assigned',
                    'projects.change_orders.view.tenant', 'projects.change_orders.create.tenant', 'projects.change_orders.submit_review.tenant',
                    'field.dispatches.view.assigned', 'field.dispatches.update.tenant',
                    'equipment.equipment.view.tenant',
                    'equipment.transactions.create.tenant',
                    'field.attendance.view.assigned', 'field.attendance.create.tenant',
                    'field.progress_logs.view.assigned', 'field.progress_logs.create.tenant', 'field.progress_logs.update.tenant',
                    'field.workers.view.assigned',
                ],
            ],
            [
                'code' => 'worker',
                'name' => '師傅',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'field.dispatches.view.own',
                    'equipment.equipment.view.tenant',
                    'equipment.transactions.create.tenant',
                    'field.attendance.view.own', 'field.attendance.create.tenant',
                    'field.progress_logs.view.own', 'field.progress_logs.create.tenant',
                    'field.workers.view.own',
                    'projects.projects.view.assigned',
                ],
            ],
            [
                'code' => 'warehouse',
                'name' => '倉管',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'inventory.materials.view.tenant', 'inventory.materials.create.tenant', 'inventory.materials.update.tenant',
                    'inventory.inventory_transactions.view.tenant', 'inventory.inventory_transactions.create.tenant', 'inventory.inventory_transactions.update.tenant', 'inventory.inventory_transactions.delete.tenant',
                    'equipment.categories.view.tenant', 'equipment.categories.create.tenant', 'equipment.categories.update.tenant', 'equipment.categories.delete.tenant',
                    'equipment.equipment.view.tenant', 'equipment.equipment.create.tenant', 'equipment.equipment.update.tenant', 'equipment.equipment.delete.tenant',
                    'equipment.transactions.view.tenant', 'equipment.transactions.create.tenant',
                    'purchasing.suppliers.view.tenant',
                    'purchasing.purchase_orders.view.tenant', 'purchasing.purchase_orders.receive.tenant',
                    'projects.projects.view.tenant',
                    'projects.change_orders.view.tenant',
                ],
            ],
            [
                'code' => 'accounting',
                'name' => '會計',
                'capabilities' => [
                    'core.dashboard.view.tenant',
                    'crm.customers.view.tenant', 'crm.customers.view_contact.tenant',
                    'projects.projects.view.tenant', 'projects.projects.view_financials.tenant',
                    'projects.change_orders.view.tenant', 'projects.change_orders.approve.tenant', 'projects.change_orders.confirm_customer.tenant', 'projects.change_orders.convert_financial_record.tenant',
                    'sales.quotations.view.tenant',
                    'sales.quotation_templates.view.tenant',
                    'finance.financial_records.view.tenant', 'finance.financial_records.create.tenant', 'finance.financial_records.update.tenant', 'finance.financial_records.delete.tenant', 'finance.financial_records.export_pdf.tenant',
                    'system.settings.view.tenant',
                ],
            ],
        ];
    }
}
