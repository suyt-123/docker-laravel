<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->foreignId('project_change_order_id')
                ->nullable()
                ->after('project_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['project_change_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropIndex(['project_change_order_id', 'status']);
            $table->dropConstrainedForeignId('project_change_order_id');
        });
    }
};
