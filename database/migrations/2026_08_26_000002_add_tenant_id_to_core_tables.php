<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddTenantIdToCoreTables extends Migration
{
    public function up()
    {
        // Ensure default tenant exists for backfill (idempotent)
        $defaultId = DB::table('tenants')->where('slug', 'qserve-default')->value('id');
        if (!$defaultId) {
            $defaultId = DB::table('tenants')->insertGetId([
                'name' => 'QServe Default',
                'slug' => 'qserve-default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['users', 'services', 'antrians'] as $table) {
            if (!Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $table->index('tenant_id');
                });
            }
        }

        // Backfill existing rows to default tenant (where null)
        foreach (['users', 'services', 'antrians'] as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultId]);
        }

        // Add FK after backfill (optional, without cascade delete to preserve history if tenant deleted)
        // Use try-catch to stay idempotent if already exists (Laravel doesn't have hasForeign)
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('services', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('antrians', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        foreach (['users', 'services', 'antrians'] as $table) {
            try {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign([$table . '_tenant_id_foreign'] ?? ['tenant_id']);
                });
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }
        Schema::dropIfExists('tenants');
    }
}
