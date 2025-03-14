<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'test@testemail.com',
            'password' => Hash::make('Password1!'),
            'email_verified_at' => now(),
        ]);
    }
}
