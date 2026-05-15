<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->after('project_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['purchase_order_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_item_id', 'type']);
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });
    }
};
