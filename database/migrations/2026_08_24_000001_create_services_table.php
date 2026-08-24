<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix', 5)->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_kiosk')->default(true);
            $table->timestamps();
        });

        DB::table('services')->insert([
            [
                'name' => 'Pengaduan',
                'prefix' => 'G',
                'is_active' => true,
                'show_in_kiosk' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PB/PD/Migrasi',
                'prefix' => 'M',
                'is_active' => true,
                'show_in_kiosk' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'P2TL',
                'prefix' => 'T',
                'is_active' => true,
                'show_in_kiosk' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('services');
    }
}
