<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create your master account
        User::create([
            'name' => 'admin', // Or whatever name you prefer
            'email' => 'admin@gmail.com',
            'password' => Hash::make('admin12345'), // Change this to a secure password
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}