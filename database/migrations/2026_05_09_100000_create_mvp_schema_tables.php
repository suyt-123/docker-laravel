<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('line_id')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('source')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('phone');
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('line_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_primary']);
        });

        Schema::create('work_crews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('leader_name')->nullable();
            $table->string('phone')->nullable();
            $table->jsonb('specialties')->nullable();
            $table->unsignedInteger('daily_rate')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_crew_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->unsignedInteger('daily_rate')->nullable();
            $table->jsonb('certifications')->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['work_crew_id', 'is_active']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_no')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_crew_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('inquiry');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('contract_amount')->default(0);
            $table->unsignedBigInteger('estimated_cost')->default(0);
            $table->unsignedBigInteger('actual_cost')->default(0);
            $table->integer('gross_profit')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('spec')->nullable();
            $table->string('unit', 32);
            $table->decimal('length', 10, 3)->nullable();
            $table->decimal('width', 10, 3)->nullable();
            $table->decimal('thickness', 10, 3)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedBigInteger('sale_price')->default(0);
            $table->decimal('safe_stock', 12, 3)->default(0);
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['name', 'spec']);
            $table->index('current_stock');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->decimal('profit_rate', 5, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->jsonb('items_json')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('spec')->nullable();
            $table->string('unit', 32);
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->decimal('waste_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 32);
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->string('reference_no')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'type']);
            $table->index(['project_id', 'type']);
            $table->index('occurred_at');
        });

        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_crew_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('work_item');
            $table->string('status')->default('scheduled');
            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('address')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index(['scheduled_date', 'status']);
            $table->index(['project_id', 'scheduled_date']);
        });

        Schema::create('dispatch_worker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 6, 2)->nullable();
            $table->unsignedBigInteger('wage')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['dispatch_id', 'worker_id']);
        });

        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->unsignedBigInteger('amount');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['type', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_records');
        Schema::dropIfExists('dispatch_worker');
        Schema::dropIfExists('dispatches');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('work_crews');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
