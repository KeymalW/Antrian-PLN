<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin PLN',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'counter_number' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'petugas1'],
            [
                'name' => 'Petugas Loket 1',
                'password' => bcrypt('petugas123'),
                'role' => 'petugas',
                'counter_number' => 1,
            ]
        );

        User::updateOrCreate(
            ['username' => 'petugas2'],
            [
                'name' => 'Petugas Loket 2',
                'password' => bcrypt('petugas123'),
                'role' => 'petugas',
                'counter_number' => 2,
            ]
        );

        User::updateOrCreate(
            ['username' => 'petugas3'],
            [
                'name' => 'Petugas Loket 3',
                'password' => bcrypt('petugas123'),
                'role' => 'petugas',
                'counter_number' => 3,
            ]
        );
    }
}
