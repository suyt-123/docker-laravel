<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('customer_confirmation_status')->default('not_sent')->after('status');
            $table->timestamp('customer_sent_at')->nullable()->after('customer_confirmation_status');
            $table->timestamp('customer_confirmed_at')->nullable()->after('customer_sent_at');
            $table->string('customer_confirmed_by_name')->nullable()->after('customer_confirmed_at');
            $table->timestamp('locked_at')->nullable()->after('customer_confirmed_by_name');
            $table->timestamp('voided_at')->nullable()->after('locked_at');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->foreignId('reopened_from_id')->nullable()->after('void_reason')->constrained('quotations')->nullOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->after('reopened_from_id')->constrained('quotations')->nullOnDelete();

            $table->index(['customer_confirmation_status', 'status']);
            $table->index('locked_at');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('category');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status')->default('active');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('file_hash')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id', 'category']);
            $table->unique(['document_type', 'document_id', 'category', 'file_hash']);
        });

        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('document_versions');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropConstrainedForeignId('reopened_from_id');
            $table->dropColumn([
                'customer_confirmation_status',
                'customer_sent_at',
                'customer_confirmed_at',
                'customer_confirmed_by_name',
                'locked_at',
                'voided_at',
                'void_reason',
            ]);
        });
    }
};
