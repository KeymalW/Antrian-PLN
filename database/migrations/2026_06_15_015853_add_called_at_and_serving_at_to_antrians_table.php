<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCalledAtAndServingAtToAntriansTable extends Migration
{
    public function up()
    {
        Schema::table('antrians', function (Blueprint $table) {
            $table->timestamp('called_at')->nullable()->after('panggil_at');
            $table->timestamp('serving_at')->nullable()->after('called_at');
        });

        DB::table('antrians')->whereNotNull('panggil_at')->update([
            'called_at' => DB::raw('panggil_at'),
        ]);
    }

    public function down()
    {
        Schema::table('antrians', function (Blueprint $table) {
            $table->dropColumn(['called_at', 'serving_at']);
        });
    }
}
