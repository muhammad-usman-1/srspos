<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows belong to a single store. */
    private array $scoped = [
        'products', 'customers', 'suppliers', 'orders', 'purchases',
        'held_bills', 'stock_movements', 'settings',
    ];

    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Everything that exists today becomes the first store.
        $name = DB::table('settings')->where('key', 'app_name')->value('value') ?: 'Main Store';
        $storeId = DB::table('stores')->insertGetId([
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('store_admin')->after('email');
            $table->unsignedBigInteger('store_id')->nullable()->after('role')->index();
        });
        DB::table('users')->update(['store_id' => $storeId]);

        foreach ($this->scoped as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('store_id')->nullable()->index();
            });
            DB::table($tableName)->update(['store_id' => $storeId]);
        }

        // Barcodes and setting keys are unique per store, not globally.
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_barcode_unique');
            $table->unique(['store_id', 'barcode']);
        });
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique('settings_key_unique');
            $table->unique(['store_id', 'key']);
        });

        // Keep a copy of the current settings as platform-wide defaults (store_id NULL).
        foreach (DB::table('settings')->get() as $row) {
            DB::table('settings')->insert([
                'key' => $row->key,
                'value' => $row->value,
                'store_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

    }

    public function down(): void
    {
        DB::table('settings')->whereNull('store_id')->delete();

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'key']);
            $table->unique('key');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'barcode']);
            $table->unique('barcode');
        });

        foreach ($this->scoped as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName . '_store_id_index');
                $table->dropColumn('store_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_store_id_index');
            $table->dropColumn(['role', 'store_id']);
        });

        Schema::dropIfExists('stores');
    }
};
