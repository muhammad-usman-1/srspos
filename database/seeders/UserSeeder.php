<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First store (created by the multi-store migration on fresh installs too).
        $store = Store::first() ?? Store::create(['name' => 'Main Store', 'is_active' => true]);

        // Project owner: manages stores only, no POS / stock access.
        User::unguarded(fn() => User::updateOrCreate(['email' => 'superadmin@gmail.com'], [
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'password' => bcrypt('superadmin123'),
            'role' => User::ROLE_SUPERADMIN,
            'store_id' => null,
        ]));

        // Store login.
        User::unguarded(fn() => User::updateOrCreate(['email' => 'admin@gmail.com'], [
            'first_name' => 'Admin',
            'last_name' => 'admin',
            'password' => bcrypt('admin123'),
            'role' => User::ROLE_STORE_ADMIN,
            'store_id' => $store->id,
        ]));
    }
}
