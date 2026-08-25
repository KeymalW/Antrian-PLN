<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddCodeAndDisplayFieldsToServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Catatan: tanpa ->change() agar tidak bergantung pada doctrine/dbal.
        // Kolom code tetap nullable di skema namun selalu terisi oleh backfill,
        // dan keunikannya dipasang lewat unique index (DDL murni MySQL).
        //
        // Idempoten: aman dijalankan ulang bila percobaan sebelumnya gagal
        // setelah ALTER dieksekusi.
        if (!Schema::hasColumn('services', 'code')) {
            Schema::table('services', function (Blueprint $table) {
                // Kunci teknis yang tersimpan di antrians.service_type.
                $table->string('code')->nullable()->after('name');
                // Pasangan loket untuk kartu TV display (null = tidak tampil di TV).
                $table->unsignedTinyInteger('counter_number')->nullable()->after('prefix');
                // Nama ikon Lucide untuk halaman kiosk.
                $table->string('icon')->nullable()->after('counter_number');
                // Grup konflik panggil bersamaan di loket yang sama.
                $table->enum('service_group', ['group_a', 'group_b'])->default('group_a')->after('icon');
            });
        }

        // Sinkronkan 3 layanan bawaan dengan kode/pemetaan yang selama ini dipakai.
        DB::table('services')->where('prefix', 'G')->update([
            'code' => 'pengaduan',
            'counter_number' => 1,
            'icon' => 'megaphone',
            'service_group' => 'group_a',
        ]);
        DB::table('services')->where('prefix', 'M')->update([
            'code' => 'pb_pd_migrasi',
            'counter_number' => 2,
            'icon' => 'plug-zap',
            'service_group' => 'group_a',
        ]);
        DB::table('services')->where('prefix', 'T')->update([
            'code' => 'p2tl',
            'counter_number' => 3,
            'icon' => 'wrench',
            'service_group' => 'group_b',
        ]);

        // Layanan lain tanpa kode: buat slug dari nama.
        $rows = DB::table('services')->whereNull('code')->get(['id', 'name']);
        foreach ($rows as $row) {
            DB::table('services')->where('id', $row->id)->update([
                'code' => Str::slug($row->name, '_') ?: 'svc_' . $row->id,
            ]);
        }

        try {
            Schema::table('services', function (Blueprint $table) {
                $table->unique('code');
            });
        } catch (\Throwable $e) {
            // Abaikan bila index sudah ada dari percobaan sebelumnya.
            if (!str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('services_code_unique');
            $table->dropColumn(['code', 'counter_number', 'icon', 'service_group']);
        });
    }
}
