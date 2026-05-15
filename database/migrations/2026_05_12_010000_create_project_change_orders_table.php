<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_change_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('requested_date')->nullable();
            $table->date('approved_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['financial_record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_change_orders');
    }
};
