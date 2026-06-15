<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddServiceTypeAndUpdateAntriansTable extends Migration
{
    public function up()
    {
        Schema::table('antrians', function (Blueprint $table) {
            $table->string('service_type')->default('pembayaran')->after('nomor_antrian');
            $table->unsignedTinyInteger('counter_number')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('panggil_at');
        });

        DB::table('antrians')->where('loket', '!=', null)->update([
            'counter_number' => DB::raw('CAST(loket AS UNSIGNED)'),
        ]);
    }

    public function down()
    {
        Schema::table('antrians', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'counter_number', 'completed_at']);
        });
    }
}
