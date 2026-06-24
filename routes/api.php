<?php

use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ProjectChangeOrderController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\V1\MaterialController as V1MaterialController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('v1')->name('api.v1.')->group(function (): void {
        Route::get('materials', [V1MaterialController::class, 'index'])
            ->middleware(['capability:inventory.materials.view.tenant', 'token_ability:read:materials'])
            ->name('materials.index');
        Route::get('materials/{material}', [V1MaterialController::class, 'show'])
            ->middleware(['capability:inventory.materials.view.tenant', 'token_ability:read:materials'])
            ->name('materials.show');
        Route::post('materials', [V1MaterialController::class, 'store'])
            ->middleware(['capability:inventory.materials.create.tenant', 'token_ability:write:materials'])
            ->name('materials.store');
        Route::match(['put', 'patch'], 'materials/{material}', [V1MaterialController::class, 'update'])
            ->middleware(['capability:inventory.materials.update.tenant', 'token_ability:write:materials'])
            ->name('materials.update');
        Route::delete('materials/{material}', [V1MaterialController::class, 'destroy'])
            ->middleware(['capability:inventory.materials.delete.tenant', 'token_ability:write:materials'])
            ->name('materials.destroy');
    });

    Route::get('quotations', [QuotationController::class, 'index'])
        ->middleware(['capability:sales.quotations.view.tenant', 'token_ability:read:quotations'])
        ->name('api.quotations.index');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware(['capability:sales.quotations.view.tenant', 'token_ability:read:quotations'])
        ->name('api.quotations.show');
    Route::post('quotations/{quotation}/submit-review', [QuotationController::class, 'submitReview'])
        ->middleware(['capability:sales.quotations.submit_review.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.submit-review');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])
        ->middleware(['capability:sales.quotations.approve.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.approve');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])
        ->middleware(['capability:sales.quotations.reject.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.reject');
    Route::post('quotations/{quotation}/send-customer', [QuotationController::class, 'sendCustomer'])
        ->middleware(['capability:sales.quotations.send_customer.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.send-customer');
    Route::post('quotations/{quotation}/accept-customer', [QuotationController::class, 'acceptCustomer'])
        ->middleware(['capability:sales.quotations.confirm_customer.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.accept-customer');
    Route::post('quotations/{quotation}/decline-customer', [QuotationController::class, 'declineCustomer'])
        ->middleware(['capability:sales.quotations.confirm_customer.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.decline-customer');
    Route::post('quotations/{quotation}/void', [QuotationController::class, 'voidQuotation'])
        ->middleware(['capability:sales.quotations.void.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.void');
    Route::post('quotations/{quotation}/reopen', [QuotationController::class, 'reopen'])
        ->middleware(['capability:sales.quotations.reopen.tenant', 'token_ability:write:quotations'])
        ->name('api.quotations.reopen');

    Route::get('project-change-orders', [ProjectChangeOrderController::class, 'index'])
        ->middleware(['capability:projects.change_orders.view.tenant', 'token_ability:read:project-change-orders'])
        ->name('api.project-change-orders.index');
    Route::get('project-change-orders/{project_change_order}', [ProjectChangeOrderController::class, 'show'])
        ->middleware(['capability:projects.change_orders.view.tenant', 'token_ability:read:project-change-orders'])
        ->name('api.project-change-orders.show');
    Route::post('project-change-orders/{project_change_order}/submit-review', [ProjectChangeOrderController::class, 'submitReview'])
        ->middleware(['capability:projects.change_orders.submit_review.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.submit-review');
    Route::post('project-change-orders/{project_change_order}/approve', [ProjectChangeOrderController::class, 'approve'])
        ->middleware(['capability:projects.change_orders.approve.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.approve');
    Route::post('project-change-orders/{project_change_order}/confirm-customer', [ProjectChangeOrderController::class, 'confirmCustomer'])
        ->middleware(['capability:projects.change_orders.confirm_customer.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.confirm-customer');
    Route::post('project-change-orders/{project_change_order}/cancel', [ProjectChangeOrderController::class, 'cancel'])
        ->middleware(['capability:projects.change_orders.cancel.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.cancel');
    Route::post('project-change-orders/{project_change_order}/create-quotation', [ProjectChangeOrderController::class, 'createQuotation'])
        ->middleware(['capability:projects.change_orders.create_quotation.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.create-quotation');
    Route::post('project-change-orders/{project_change_order}/convert-financial-record', [ProjectChangeOrderController::class, 'convertFinancialRecord'])
        ->middleware(['capability:projects.change_orders.convert_financial_record.tenant', 'token_ability:write:project-change-orders'])
        ->name('api.project-change-orders.convert-financial-record');

    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware(['capability:purchasing.purchase_orders.receive.tenant', 'token_ability:write:purchase-orders'])
        ->name('api.purchase-orders.receive');
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware(['capability:purchasing.purchase_orders.view.tenant', 'token_ability:read:purchase-orders'])
        ->name('api.purchase-orders.index');
    Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])
        ->middleware(['capability:purchasing.purchase_orders.view.tenant', 'token_ability:read:purchase-orders'])
        ->name('api.purchase-orders.show');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware(['capability:purchasing.purchase_orders.create.tenant', 'token_ability:write:purchase-orders'])
        ->name('api.purchase-orders.store');
    Route::match(['put', 'patch'], 'purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])
        ->middleware(['capability:purchasing.purchase_orders.update.tenant', 'token_ability:write:purchase-orders'])
        ->name('api.purchase-orders.update');
    Route::delete('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy'])
        ->middleware(['capability:purchasing.purchase_orders.delete.tenant', 'token_ability:write:purchase-orders'])
        ->name('api.purchase-orders.destroy');

    Route::get('materials', [MaterialController::class, 'index'])
        ->middleware(['capability:inventory.materials.view.tenant', 'token_ability:read:materials'])
        ->name('api.materials.index');
    Route::get('materials/{material}', [MaterialController::class, 'show'])
        ->middleware(['capability:inventory.materials.view.tenant', 'token_ability:read:materials'])
        ->name('api.materials.show');
    Route::post('materials', [MaterialController::class, 'store'])
        ->middleware(['capability:inventory.materials.create.tenant', 'token_ability:write:materials'])
        ->name('api.materials.store');
    Route::match(['put', 'patch'], 'materials/{material}', [MaterialController::class, 'update'])
        ->middleware(['capability:inventory.materials.update.tenant', 'token_ability:write:materials'])
        ->name('api.materials.update');
    Route::delete('materials/{material}', [MaterialController::class, 'destroy'])
        ->middleware(['capability:inventory.materials.delete.tenant', 'token_ability:write:materials'])
        ->name('api.materials.destroy');

    Route::post('inventory-transactions', [InventoryTransactionController::class, 'store'])
        ->middleware(['capability:inventory.inventory_transactions.create.tenant', 'token_ability:write:inventory-transactions'])
        ->name('api.inventory-transactions.store');
    Route::match(['put', 'patch'], 'inventory-transactions/{inventory_transaction}', [InventoryTransactionController::class, 'update'])
        ->middleware(['capability:inventory.inventory_transactions.update.tenant', 'token_ability:write:inventory-transactions'])
        ->name('api.inventory-transactions.update');
    Route::delete('inventory-transactions/{inventory_transaction}', [InventoryTransactionController::class, 'destroy'])
        ->middleware(['capability:inventory.inventory_transactions.delete.tenant', 'token_ability:write:inventory-transactions'])
        ->name('api.inventory-transactions.destroy');
});
