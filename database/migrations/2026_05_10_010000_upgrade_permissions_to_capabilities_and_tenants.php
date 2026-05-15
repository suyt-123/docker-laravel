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
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions') && ! Schema::hasTable('capabilities')) {
            Schema::rename('permissions', 'capabilities');
        }

        if (Schema::hasTable('permission_role') && ! Schema::hasTable('capability_role')) {
            Schema::rename('permission_role', 'capability_role');
        }

        if (Schema::hasTable('capability_role') && Schema::hasColumn('capability_role', 'permission_id')) {
            Schema::table('capability_role', function (Blueprint $table) {
                $table->renameColumn('permission_id', 'capability_id');
            });
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn('roles', 'is_system')) {
                    $table->boolean('is_system')->default(false)->after('description');
                }

                if (! Schema::hasColumn('roles', 'is_protected')) {
                    $table->boolean('is_protected')->default(false)->after('is_system');
                }
            });
        }

        if (Schema::hasTable('capabilities')) {
            Schema::table('capabilities', function (Blueprint $table) {
                if (! Schema::hasColumn('capabilities', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn('capabilities', 'domain')) {
                    $table->string('domain')->default('core')->after('code');
                }

                if (! Schema::hasColumn('capabilities', 'resource')) {
                    $table->string('resource')->default('general')->after('domain');
                }

                if (! Schema::hasColumn('capabilities', 'action')) {
                    $table->string('action')->default('view')->after('resource');
                }

                if (! Schema::hasColumn('capabilities', 'scope')) {
                    $table->string('scope')->default('tenant')->after('action');
                }

                if (! Schema::hasColumn('capabilities', 'is_system')) {
                    $table->boolean('is_system')->default(true)->after('description');
                }
            });
        }

        if (Schema::hasTable('role_user')) {
            Schema::table('role_user', function (Blueprint $table) {
                if (! Schema::hasColumn('role_user', 'tenant_id')) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                }
            });
        }

        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');

        if (Schema::hasTable('role_user') && Schema::hasColumn('role_user', 'tenant_id')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        if (Schema::hasTable('capabilities')) {
            Schema::table('capabilities', function (Blueprint $table) {
                foreach (['tenant_id', 'domain', 'resource', 'action', 'scope', 'is_system'] as $column) {
                    if (Schema::hasColumn('capabilities', $column)) {
                        $column === 'tenant_id'
                            ? $table->dropConstrainedForeignId($column)
                            : $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                foreach (['tenant_id', 'is_system', 'is_protected'] as $column) {
                    if (Schema::hasColumn('roles', $column)) {
                        $column === 'tenant_id'
                            ? $table->dropConstrainedForeignId($column)
                            : $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('capability_role') && Schema::hasColumn('capability_role', 'capability_id')) {
            Schema::table('capability_role', function (Blueprint $table) {
                $table->renameColumn('capability_id', 'permission_id');
            });
        }

        if (Schema::hasTable('capability_role') && ! Schema::hasTable('permission_role')) {
            Schema::rename('capability_role', 'permission_role');
        }

        if (Schema::hasTable('capabilities') && ! Schema::hasTable('permissions')) {
            Schema::rename('capabilities', 'permissions');
        }

        Schema::dropIfExists('tenants');
    }
};
