<?php

namespace Database\Seeders;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Get the actual columns from the customers table
        $columns = Schema::getColumnListing('customers');
        
        // Show the available columns for debugging
        $this->command->info('Customer table columns: ' . implode(', ', $columns));
        
        // Create basic customer data
        $customers = [];
        
        // Add 20 random customers
        for ($i = 0; $i < 20; $i++) {
            $customerData = [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
            ];
            
            // Only add fields that exist in the table
            if (in_array('status', $columns)) {
                $customerData['status'] = 'active';
            }
            
            if (in_array('type', $columns)) {
                $customerData['type'] = 'individual';
            }
            
            if (in_array('tax_id', $columns) && rand(1, 5) == 1) {
                $customerData['tax_id'] = $faker->numerify('##-#######');
            }
            
            $customers[] = $customerData;
        }
        
        // Create the customers
        $createdCount = 0;
        foreach ($customers as $customerData) {
            try {
                $customer = Customer::create($customerData);
                $createdCount++;
            } catch (\Exception $e) {
                $this->command->error('Error creating customer: ' . $e->getMessage());
            }
        }
        
        $this->command->info("Created {$createdCount} customers successfully.");
    }
}