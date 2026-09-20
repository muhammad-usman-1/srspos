<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Store;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'app_name' => 'Laravel-POS',
            'currency_symbol' => 'PKR',
            'warning_quantity' => '10',
            'enable_discount' => '1',
            'enable_tax' => '0',
            'tax_name' => 'GST',
            'tax_rate' => '0',
        ];

        // Platform-wide defaults (store_id NULL) plus the first store's own copy.
        $storeIds = array_merge([null], Store::pluck('id')->take(1)->all());

        foreach ($storeIds as $storeId) {
            foreach ($data as $key => $value) {
                // Never overwrite values a store has already customised.
                Setting::firstOrCreate(['key' => $key, 'store_id' => $storeId], ['value' => $value]);
            }
        }
    }
}
