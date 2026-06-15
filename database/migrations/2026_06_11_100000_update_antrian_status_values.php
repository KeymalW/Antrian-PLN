<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateAntrianStatusValues extends Migration
{
    public function up()
    {
        DB::table('antrians')
            ->where('status', 'menunggu')
            ->update(['status' => 'waiting']);

        DB::table('antrians')
            ->where('status', 'dipanggil')
            ->update(['status' => 'called']);

        DB::table('antrians')
            ->where('status', 'selesai')
            ->update(['status' => 'completed']);

        DB::table('antrians')
            ->where('status', 'lewati')
            ->update(['status' => 'skipped']);
    }

    public function down()
    {
        DB::table('antrians')
            ->where('status', 'waiting')
            ->update(['status' => 'menunggu']);

        DB::table('antrians')
            ->where('status', 'called')
            ->update(['status' => 'dipanggil']);

        DB::table('antrians')
            ->where('status', 'completed')
            ->update(['status' => 'selesai']);

        DB::table('antrians')
            ->where('status', 'skipped')
            ->update(['status' => 'lewati']);
    }
}
