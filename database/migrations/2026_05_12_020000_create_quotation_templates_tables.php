<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('active');
            $table->decimal('profit_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->jsonb('parameter_definitions')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('name');
        });

        Schema::create('quotation_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('spec')->nullable();
            $table->string('unit', 32);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->decimal('waste_rate', 5, 2)->default(0);
            $table->string('formula_type')->default('fixed_quantity');
            $table->jsonb('formula_params')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quotation_template_id', 'sort_order']);
            $table->index('formula_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_template_items');
        Schema::dropIfExists('quotation_templates');
    }
};
