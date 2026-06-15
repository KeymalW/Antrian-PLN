<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddRoleAndCounterNumberToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('petugas')->after('username');
            $table->unsignedTinyInteger('counter_number')->nullable()->after('role');
        });

        DB::table('users')->where('username', 'admin_loket')->update([
            'role' => 'admin',
            'counter_number' => null,
        ]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'counter_number']);
        });
    }
}
