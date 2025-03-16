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
                'name' => 'BestBuy',
                'email' => 'wholesale@bestbuy.com',
                'phone' => '1-800-555-0001',
                'address' => '123 Electronics Way',
                'city' => 'Minneapolis',
                'state' => 'MN',
                'postal_code' => '55423',
                'country' => 'USA',
                'website' => 'https://www.bestbuy.com',
                'tax_id' => '12-3456789',
                'contact_name' => 'John Smith',
                'contact_email' => 'john.smith@bestbuy.com',
                'contact_phone' => '1-800-555-0002',
                'notes' => 'Major electronics supplier',
                'status' => 'active',
                'default_payment_terms' => 'net_30',
                'default_shipping_method' => 'standard',
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
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }
    }
}
