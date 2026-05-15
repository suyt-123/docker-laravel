<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_no')->unique();
            $table->foreignId('equipment_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('current_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('current_work_crew_id')->nullable()->constrained('work_crews')->nullOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('asset_tag')->nullable();
            $table->string('status')->default('available');
            $table->string('condition')->default('good');
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_price')->default(0);
            $table->date('warranty_until')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->timestamp('next_maintenance_at')->nullable();
            $table->text('note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'condition']);
            $table->index(['equipment_category_id', 'status']);
            $table->index(['current_project_id', 'status']);
            $table->index(['current_worker_id', 'status']);
            $table->index(['current_work_crew_id', 'status']);
        });

        Schema::create('equipment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_crew_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->timestamp('due_at')->nullable();
            $table->string('condition_before')->nullable();
            $table->string('condition_after')->nullable();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
            $table->index(['project_id', 'type']);
            $table->index(['worker_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_transactions');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('equipment_categories');
    }
};
