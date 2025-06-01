<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat admin default
        User::create([
            'name' => 'Admin',
            'email' => 'adminflaming@gmail.com',
            'password' => Hash::make('gayoituenak'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'developer',
            'email' => 'developerflaming@gmail.com',
            'password' => Hash::make('ivanganteng'),
            'role' => 'admin',
        ]);
    }
}