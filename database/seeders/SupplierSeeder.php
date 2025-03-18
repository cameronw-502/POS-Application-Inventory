<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'TechSource Electronics',
                'email' => 'orders@techsource.com',
                'phone' => '1-800-555-0001',
                'address' => '123 Tech Road',
                'city' => 'San Jose',
                'state' => 'CA',
                'postal_code' => '95123',
                'country' => 'USA',
                'website' => 'https://www.techsource.com',
                'tax_id' => '12-3456789',
                'contact_name' => 'John Smith',
                'contact_email' => 'john@techsource.com',
                'contact_phone' => '1-800-555-0002',
                'notes' => 'Our primary electronics supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_30',
                'default_shipping_method' => 'ground',
            ],
            [
                'name' => 'Fashion Wholesale Inc',
                'email' => 'orders@fashionwholesale.com',
                'phone' => '1-800-555-0003',
                'address' => '456 Fashion Avenue',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10018',
                'country' => 'USA',
                'website' => 'https://www.fashionwholesale.com',
                'tax_id' => '98-7654321',
                'contact_name' => 'Sarah Johnson',
                'contact_email' => 'sarah@fashionwholesale.com',
                'contact_phone' => '1-800-555-0004',
                'notes' => 'Premium clothing supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_45',
                'default_shipping_method' => 'express',
            ],
            [
                'name' => 'Home Goods Direct',
                'email' => 'supply@homegoodsdirect.com',
                'phone' => '1-800-555-0005',
                'address' => '789 Home Street',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'USA',
                'website' => 'https://www.homegoodsdirect.com',
                'tax_id' => '45-6789012',
                'contact_name' => 'Mike Wilson',
                'contact_email' => 'mike@homegoodsdirect.com',
                'contact_phone' => '1-800-555-0006',
                'notes' => 'Home and garden products supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_30',
                'default_shipping_method' => 'freight',
            ],
            [
                'name' => 'Outdoor Adventures Supply',
                'email' => 'sales@outdooradventures.com',
                'phone' => '1-800-555-0007',
                'address' => '101 Mountain Road',
                'city' => 'Denver',
                'state' => 'CO',
                'postal_code' => '80202',
                'country' => 'USA',
                'website' => 'https://www.outdooradventures.com',
                'tax_id' => '78-9012345',
                'contact_name' => 'Alex Thompson',
                'contact_email' => 'alex@outdooradventures.com',
                'contact_phone' => '1-800-555-0008',
                'notes' => 'Outdoor and camping gear supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_30',
                'default_shipping_method' => 'ground',
            ],
            [
                'name' => 'Global Electronics Ltd',
                'email' => 'orders@globalelectronics.com',
                'phone' => '1-800-555-0009',
                'address' => '200 Innovation Blvd',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'USA',
                'website' => 'https://www.globalelectronics.com',
                'tax_id' => '23-4567890',
                'contact_name' => 'David Chen',
                'contact_email' => 'david@globalelectronics.com',
                'contact_phone' => '1-800-555-0010',
                'notes' => 'International electronics supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_60',
                'default_shipping_method' => 'air',
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }
        
        $this->command->info('Created ' . count($suppliers) . ' suppliers.');
    }
}
