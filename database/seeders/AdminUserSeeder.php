<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Use updateOrCreate to avoid duplicate entry errors
        User::updateOrCreate(
            ['email' => 'test@testemail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Password1!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
