<?php

namespace Database\Seeders;

use App\Models\Register;
use Illuminate\Database\Seeder;

class RegisterSeeder extends Seeder
{
    public function run(): void
    {
        // Create 5 registers with different statuses
        $statuses = ['active', 'active', 'active', 'maintenance', 'inactive'];
        
        foreach ($statuses as $i => $status) {
            Register::create([
                'name' => 'Register ' . ($i + 1),
                'register_number' => 'REG-' . str_pad(($i + 1), 4, '0', STR_PAD_LEFT),
                'status' => $status,
            ]);
        }
    }
}