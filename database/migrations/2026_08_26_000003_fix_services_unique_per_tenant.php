<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixServicesUniquePerTenant extends Migration
{
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            try {
                $table->dropUnique('services_prefix_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropUnique('services_code_unique');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('services', function (Blueprint $table) {
            // Composite unique per tenant (allow same prefix/code in different tenants)
            try {
                $table->unique(['tenant_id', 'prefix'], 'services_tenant_prefix_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->unique(['tenant_id', 'code'], 'services_tenant_code_unique');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            try {
                $table->dropUnique('services_tenant_prefix_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropUnique('services_tenant_code_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->unique('prefix', 'services_prefix_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->unique('code', 'services_code_unique');
            } catch (\Throwable $e) {
            }
        });
    }
}
