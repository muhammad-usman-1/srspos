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
        $this->call(UserSeeder::class);
        $this->call(SettingsSeeder::class);
        // No products are seeded: the catalogue starts empty and the store admin
        // picks the stock mode (tracked/simple) in Settings before adding any.
    }
}
