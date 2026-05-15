<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\AttendanceRecord;
use App\Models\CustomerContact;
use App\Models\Dispatch;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentTransaction;
use App\Models\FinancialRecord;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProgressLog;
use App\Models\ProgressPhoto;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationTemplate;
use App\Models\QuotationTemplateItem;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        foreach ($this->auditedModels() as $model) {
            $model::observe(ActivityLogObserver::class);
        }
    }

    /**
     * @return array<int, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private function auditedModels(): array
    {
        return [
            Customer::class,
            AttendanceRecord::class,
            CustomerContact::class,
            Project::class,
            ProjectChangeOrder::class,
            Supplier::class,
            PurchaseOrder::class,
            PurchaseOrderItem::class,
            Quotation::class,
            QuotationItem::class,
            QuotationTemplate::class,
            QuotationTemplateItem::class,
            Material::class,
            MaterialCategory::class,
            InventoryTransaction::class,
            EquipmentCategory::class,
            Equipment::class,
            EquipmentTransaction::class,
            Dispatch::class,
            WorkCrew::class,
            Worker::class,
            ProgressLog::class,
            ProgressPhoto::class,
            FinancialRecord::class,
            User::class,
            Role::class,
            SystemSetting::class,
        ];
    }
}
