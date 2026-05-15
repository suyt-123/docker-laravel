<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_change_orders', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('financial_record_id')->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->boolean('requires_formal_quotation')->default(false)->after('amount');
            $table->timestamp('submitted_at')->nullable()->after('requested_date');
            $table->timestamp('approved_at')->nullable()->after('approved_date');
            $table->timestamp('customer_confirmed_at')->nullable()->after('approved_at');

            $table->index(['quotation_id', 'status']);
            $table->index(['requires_formal_quotation', 'status']);
        });

        DB::table('project_change_orders')
            ->where('status', 'pending')
            ->update(['status' => 'draft']);

        DB::table('project_change_orders')
            ->where('status', 'approved')
            ->update(['status' => 'customer_confirmed']);

        DB::table('project_change_orders')
            ->where('status', 'rejected')
            ->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        DB::table('project_change_orders')
            ->where('status', 'draft')
            ->update(['status' => 'pending']);

        DB::table('project_change_orders')
            ->where('status', 'customer_confirmed')
            ->update(['status' => 'approved']);

        DB::table('project_change_orders')
            ->where('status', 'cancelled')
            ->update(['status' => 'rejected']);

        Schema::table('project_change_orders', function (Blueprint $table) {
            $table->dropIndex(['requires_formal_quotation', 'status']);
            $table->dropIndex(['quotation_id', 'status']);
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'requires_formal_quotation',
                'submitted_at',
                'approved_at',
                'customer_confirmed_at',
            ]);
        });
    }
};
