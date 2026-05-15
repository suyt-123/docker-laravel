<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\EquipmentCategoryController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentTransactionController;
use App\Http\Controllers\FinancialRecordController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressLogController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectChangeOrderController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationTemplateController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\WorkCrewController;
use App\Http\Controllers\WorkHoursReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$crudCapabilities = function (string $name, string $controller, string $resource, string $parameter, ?array $viewCapabilities = null): void {
    $view = implode('|', $viewCapabilities ?? ["{$resource}.view.tenant"]);

    Route::get($name, [$controller, 'index'])
        ->middleware("capability:{$view}")
        ->name("{$name}.index");
    Route::get("{$name}/create", [$controller, 'create'])
        ->middleware("capability:{$resource}.create.tenant")
        ->name("{$name}.create");
    Route::post($name, [$controller, 'store'])
        ->middleware("capability:{$resource}.create.tenant")
        ->name("{$name}.store");
    Route::get("{$name}/{{$parameter}}", [$controller, 'show'])
        ->middleware("capability:{$view}")
        ->name("{$name}.show");
    Route::get("{$name}/{{$parameter}}/edit", [$controller, 'edit'])
        ->middleware("capability:{$resource}.update.tenant")
        ->name("{$name}.edit");
    Route::match(['put', 'patch'], "{$name}/{{$parameter}}", [$controller, 'update'])
        ->middleware("capability:{$resource}.update.tenant")
        ->name("{$name}.update");
    Route::delete("{$name}/{{$parameter}}", [$controller, 'destroy'])
        ->middleware("capability:{$resource}.delete.tenant")
        ->name("{$name}.destroy");
};

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'capability:core.dashboard.view.tenant'])
    ->name('dashboard');

Route::middleware('auth')->group(function () use ($crudCapabilities) {
    $crudCapabilities('customers', CustomerController::class, 'crm.customers', 'customer');
    Route::get('projects/{project}/invoice-pdf', [ProjectController::class, 'invoicePdf'])
        ->middleware('capability:finance.financial_records.export_pdf.tenant')
        ->name('projects.invoice-pdf');
    $crudCapabilities('projects', ProjectController::class, 'projects.projects', 'project', [
        'projects.projects.view.tenant',
        'projects.projects.view.assigned',
    ]);
    Route::post('project-change-orders/{project_change_order}/convert-financial-record', [ProjectChangeOrderController::class, 'convertFinancialRecord'])
        ->middleware('capability:projects.change_orders.convert_financial_record.tenant')
        ->name('project-change-orders.convert-financial-record');
    Route::post('project-change-orders/{project_change_order}/submit-review', [ProjectChangeOrderController::class, 'submitReview'])
        ->middleware('capability:projects.change_orders.submit_review.tenant')
        ->name('project-change-orders.submit-review');
    Route::post('project-change-orders/{project_change_order}/approve', [ProjectChangeOrderController::class, 'approve'])
        ->middleware('capability:projects.change_orders.approve.tenant')
        ->name('project-change-orders.approve');
    Route::post('project-change-orders/{project_change_order}/confirm-customer', [ProjectChangeOrderController::class, 'confirmCustomer'])
        ->middleware('capability:projects.change_orders.confirm_customer.tenant')
        ->name('project-change-orders.confirm-customer');
    Route::post('project-change-orders/{project_change_order}/cancel', [ProjectChangeOrderController::class, 'cancel'])
        ->middleware('capability:projects.change_orders.cancel.tenant')
        ->name('project-change-orders.cancel');
    Route::post('project-change-orders/{project_change_order}/create-quotation', [ProjectChangeOrderController::class, 'createQuotation'])
        ->middleware('capability:projects.change_orders.create_quotation.tenant')
        ->name('project-change-orders.create-quotation');
    $crudCapabilities('project-change-orders', ProjectChangeOrderController::class, 'projects.change_orders', 'project_change_order');
    $crudCapabilities('materials', MaterialController::class, 'inventory.materials', 'material');

    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])
        ->middleware('capability:sales.quotations.export_pdf.tenant')
        ->name('quotations.pdf');
    Route::post('quotations/{quotation}/submit-review', [QuotationController::class, 'submitReview'])
        ->middleware('capability:sales.quotations.submit_review.tenant')
        ->name('quotations.submit-review');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])
        ->middleware('capability:sales.quotations.approve.tenant')
        ->name('quotations.approve');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])
        ->middleware('capability:sales.quotations.reject.tenant')
        ->name('quotations.reject');
    Route::post('quotations/{quotation}/send-customer', [QuotationController::class, 'sendCustomer'])
        ->middleware('capability:sales.quotations.send_customer.tenant')
        ->name('quotations.send-customer');
    Route::post('quotations/{quotation}/accept-customer', [QuotationController::class, 'acceptCustomer'])
        ->middleware('capability:sales.quotations.confirm_customer.tenant')
        ->name('quotations.accept-customer');
    Route::post('quotations/{quotation}/decline-customer', [QuotationController::class, 'declineCustomer'])
        ->middleware('capability:sales.quotations.confirm_customer.tenant')
        ->name('quotations.decline-customer');
    Route::post('quotations/{quotation}/convert-project', [QuotationController::class, 'convertProject'])
        ->middleware('capability:sales.quotations.convert_project.tenant')
        ->name('quotations.convert-project');
    Route::post('quotations/{quotation}/void', [QuotationController::class, 'voidQuotation'])
        ->middleware('capability:sales.quotations.void.tenant')
        ->name('quotations.void');
    Route::post('quotations/{quotation}/reopen', [QuotationController::class, 'reopen'])
        ->middleware('capability:sales.quotations.reopen.tenant')
        ->name('quotations.reopen');
    Route::post('quotations/{quotation}/attachments', [QuotationController::class, 'storeAttachment'])
        ->middleware('capability:sales.quotations.update.tenant')
        ->name('quotations.attachments.store');
    Route::delete('quotation-attachments/{document_attachment}', [QuotationController::class, 'destroyAttachment'])
        ->middleware('capability:sales.quotations.update.tenant')
        ->name('quotations.attachments.destroy');
    $crudCapabilities('quotations', QuotationController::class, 'sales.quotations', 'quotation');
    Route::post('quotation-templates/{quotation_template}/calculate', [QuotationTemplateController::class, 'calculate'])
        ->middleware('capability:sales.quotation_templates.view.tenant')
        ->name('quotation-templates.calculate');
    $crudCapabilities('quotation-templates', QuotationTemplateController::class, 'sales.quotation_templates', 'quotation_template');
    $crudCapabilities('inventory-transactions', InventoryTransactionController::class, 'inventory.inventory_transactions', 'inventory_transaction');
    Route::post('equipment/{equipment}/transactions', [EquipmentTransactionController::class, 'store'])
        ->middleware('capability:equipment.transactions.create.tenant')
        ->name('equipment.transactions.store');
    $crudCapabilities('equipment-categories', EquipmentCategoryController::class, 'equipment.categories', 'equipment_category');
    $crudCapabilities('equipment', EquipmentController::class, 'equipment.equipment', 'equipment');
    Route::get('equipment-transactions', [EquipmentTransactionController::class, 'index'])
        ->middleware('capability:equipment.transactions.view.tenant')
        ->name('equipment-transactions.index');
    $crudCapabilities('suppliers', SupplierController::class, 'purchasing.suppliers', 'supplier');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('capability:purchasing.purchase_orders.receive.tenant')
        ->name('purchase-orders.receive');
    $crudCapabilities('purchase-orders', PurchaseOrderController::class, 'purchasing.purchase_orders', 'purchase_order');
    Route::get('dispatches-schedule', [DispatchController::class, 'schedule'])
        ->middleware('capability:field.dispatches.view.tenant|field.dispatches.view.assigned|field.dispatches.view.own')
        ->name('dispatches.schedule');
    $crudCapabilities('dispatches', DispatchController::class, 'field.dispatches', 'dispatch', [
        'field.dispatches.view.tenant',
        'field.dispatches.view.assigned',
        'field.dispatches.view.own',
    ]);
    Route::get('reports/work-hours', WorkHoursReportController::class)
        ->middleware('capability:field.attendance.view.tenant|field.attendance.view.assigned|field.attendance.view.own')
        ->name('reports.work-hours');
    Route::get('attendance-records', [AttendanceRecordController::class, 'index'])
        ->middleware('capability:field.attendance.view.tenant|field.attendance.view.assigned|field.attendance.view.own')
        ->name('attendance-records.index');
    Route::post('attendance-records', [AttendanceRecordController::class, 'store'])
        ->middleware('capability:field.attendance.create.tenant')
        ->name('attendance-records.store');
    Route::get('attendance-records/{attendance_record}', [AttendanceRecordController::class, 'show'])
        ->middleware('capability:field.attendance.view.tenant|field.attendance.view.assigned|field.attendance.view.own')
        ->name('attendance-records.show');
    Route::delete('attendance-records/{attendance_record}', [AttendanceRecordController::class, 'destroy'])
        ->middleware('capability:field.attendance.delete.tenant')
        ->name('attendance-records.destroy');
    $crudCapabilities('progress-logs', ProgressLogController::class, 'field.progress_logs', 'progress_log', [
        'field.progress_logs.view.tenant',
        'field.progress_logs.view.assigned',
        'field.progress_logs.view.own',
    ]);
    Route::delete('progress-photos/{progress_photo}', [ProgressLogController::class, 'destroyPhoto'])
        ->middleware('capability:field.progress_logs.update.tenant|field.progress_logs.delete.tenant')
        ->name('progress-photos.destroy');
    $crudCapabilities('work-crews', WorkCrewController::class, 'field.work_crews', 'work_crew');
    $crudCapabilities('workers', WorkerController::class, 'field.workers', 'worker', [
        'field.workers.view.tenant',
        'field.workers.view.assigned',
        'field.workers.view.own',
    ]);
    $crudCapabilities('financial-records', FinancialRecordController::class, 'finance.financial_records', 'financial_record');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('capability:security.activity_logs.view.tenant')
        ->name('activity-logs.index');
    Route::get('activity-logs/{activity_log}', [ActivityLogController::class, 'show'])
        ->middleware('capability:security.activity_logs.view.tenant')
        ->name('activity-logs.show');

    Route::get('system-settings', [SystemSettingController::class, 'edit'])
        ->middleware('capability:system.settings.view.tenant')
        ->name('system-settings.edit');
    Route::patch('system-settings', [SystemSettingController::class, 'update'])
        ->middleware('capability:system.settings.update.tenant')
        ->name('system-settings.update');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('capability:security.users.view.tenant')
        ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('capability:security.users.create.tenant')
        ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('capability:security.users.create.tenant')
        ->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('capability:security.users.view.tenant')
        ->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('capability:security.users.update.tenant')
        ->name('users.edit');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
        ->middleware('capability:security.users.update.tenant')
        ->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('capability:security.users.delete.tenant')
        ->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('capability:security.roles.view.tenant')
        ->name('roles.index');
    Route::get('roles/matrix', [RoleController::class, 'matrix'])
        ->middleware('capability:security.roles.view.tenant')
        ->name('roles.matrix');
    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware('capability:security.roles.create.tenant')
        ->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware(['capability:security.roles.create.tenant', 'capability:security.roles.assign_capabilities.tenant'])
        ->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('capability:security.roles.view.tenant')
        ->name('roles.show');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('capability:security.roles.update.tenant')
        ->name('roles.edit');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])
        ->middleware(['capability:security.roles.update.tenant', 'capability:security.roles.assign_capabilities.tenant'])
        ->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('capability:security.roles.delete.tenant')
        ->name('roles.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
