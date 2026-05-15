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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->boolean('is_within_range')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->boolean('requires_attention')->default(false);
            $table->string('anomaly_reason')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['dispatch_id', 'type', 'recorded_at']);
            $table->index(['worker_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
            $table->index(['requires_attention', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
