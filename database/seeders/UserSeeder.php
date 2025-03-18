<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, ensure cashier role exists
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        
        // Get the actual columns from the users table
        $columns = Schema::getColumnListing('users');
        
        // Show the available columns for debugging
        $this->command->info('User table columns: ' . implode(', ', $columns));
        
        // Define basic user data
        $users = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@example.com',
                'role' => 'cashier'
            ],
            [
                'name' => 'Emily Johnson',
                'email' => 'emily.johnson@example.com',
                'role' => 'cashier'
            ],
            [
                'name' => 'Robert Wilson',
                'email' => 'robert.wilson@example.com',
                'role' => 'manager'
            ]
        ];
        
        $createdCount = 0;
        foreach ($users as $userData) {
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            
            if (!$existingUser) {
                try {
                    $createData = [
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'password' => Hash::make('password'), // Default password
                    ];
                    
                    if (in_array('email_verified_at', $columns)) {
                        $createData['email_verified_at'] = now();
                    }
                    
                    if (in_array('remember_token', $columns)) {
                        $createData['remember_token'] = Str::random(10);
                    }
                    
                    $user = User::create($createData);
                    
                    // Assign appropriate role
                    if ($userData['role'] === 'manager') {
                        $user->assignRole($managerRole);
                    } else {
                        $user->assignRole($cashierRole);
                    }
                    
                    $createdCount++;
                } catch (\Exception $e) {
                    $this->command->error('Error creating user: ' . $e->getMessage());
                }
            }
        }
        
        $this->command->info("Created {$createdCount} users with appropriate roles.");
    }
}