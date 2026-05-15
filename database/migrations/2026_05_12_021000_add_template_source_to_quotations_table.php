<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('quotation_template_id')
                ->nullable()
                ->after('project_id')
                ->constrained()
                ->nullOnDelete();
            $table->jsonb('template_inputs')->nullable()->after('items_json');

            $table->index(['quotation_template_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['quotation_template_id', 'status']);
            $table->dropConstrainedForeignId('quotation_template_id');
            $table->dropColumn('template_inputs');
        });
    }
};
