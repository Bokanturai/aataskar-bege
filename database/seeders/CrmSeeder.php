<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Helpers\ServiceManager;

class CrmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the CRM Service and its fields
        ServiceManager::getServiceWithFields('CRM', [
            ['name' => 'Central Risk Management', 'code' => '021', 'price' => 1500],
        ]);

        // Define the BVN SEARCH Service
        ServiceManager::getServiceWithFields('BVN SEARCH', [
            ['name' => 'Search BVN', 'code' => '45', 'price' => 1500],
        ]);

        // Define the Verification Service and its fields
        ServiceManager::getServiceWithFields('Verification', [
            ['name' => 'BVN Verification', 'code' => '600', 'price' => 70],
            ['name' => 'Standard BVN Slip', 'code' => '601', 'price' => 50],
            ['name' => 'Premium BVN Slip', 'code' => '602', 'price' => 100],
            ['name' => 'Plastic BVN Slip', 'code' => '603', 'price' => 150],
            ['name' => 'NIN Verification', 'code' => '610', 'price' => 100],
            ['name' => 'NIN Demographic Verification', 'code' => 'V100', 'price' => 200],
            ['name' => 'NIN Phone Verification', 'code' => 'V105', 'price' => 100],
            ['name' => 'Basic Slip', 'code' => 'V101', 'price' => 50],
            ['name' => 'Regular Slip', 'code' => 'V102', 'price' => 100],
            ['name' => 'Standard Slip', 'code' => '611', 'price' => 100],
            ['name' => 'Premium Slip', 'code' => '612', 'price' => 150],
            ['name' => 'VNIN Slip', 'code' => '616', 'price' => 100],
        ]);
    }
}
