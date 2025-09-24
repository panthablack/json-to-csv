<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => env('ADMIN_USER_EMAIL', 'admin@example.com'),
                'password' => Hash::make(env('ADMIN_USER_PASSWORD', 'secret')),
                'email_verified_at' => now(),
            ]
        );
    }
}
