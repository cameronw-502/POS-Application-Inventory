<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            RoleAndPermissionSeeder::class,
            UserSeeder::class, // Add our new UserSeeder here
            CustomerSeeder::class, // Add our new CustomerSeeder here
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            RegisterSeeder::class,
            // First generate purchase orders
            PurchaseOrderSeeder::class,
            // Then generate receiving reports for those orders
            ReceivingReportSeeder::class,
            // Generate sales and transactions after products are in stock
            SalesSeeder::class,
        ]);
    }
}