<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each store picks, once, whether it tracks stock quantity (adjustable, with
 * low-stock alerts) or just keeps a product catalogue with no quantities.
 * The choice lives on the store itself (not the generic key/value settings
 * table) so it is easy to guard as write-once from the app layer.
 *
 * Left unset for every store — old and new — so the admin makes the choice
 * in Settings rather than it being picked for them. Until they choose, the
 * app treats an unset store exactly as "tracked" (today's only behaviour),
 * so nothing changes for a store that has not visited Settings yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('stock_mode', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('stock_mode');
        });
    }
};
