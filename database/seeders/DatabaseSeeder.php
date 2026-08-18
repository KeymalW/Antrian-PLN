<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['username' => 'adminulpsubang'],
            [
                'name' => 'Admin ULP Subang',
                'password' => bcrypt('adminulpsubang'),
                'role' => 'admin',
                'counter_number' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'petugasulpsubang'],
            [
                'name' => 'Petugas ULP Subang',
                'password' => bcrypt('petugasulpsubang'),
                'role' => 'petugas',
                'counter_number' => 1,
            ]
        );

        User::updateOrCreate(
            ['username' => 'kioskulpsubang'],
            [
                'name' => 'Kiosk ULP Subang',
                'password' => bcrypt('kioskulpsubang'),
                'role' => 'kiosk',
                'counter_number' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'tvulpsubang'],
            [
                'name' => 'TV Display ULP Subang',
                'password' => bcrypt('tvulpsubang'),
                'role' => 'tvdisplay',
                'counter_number' => null,
            ]
        );
    }
}
