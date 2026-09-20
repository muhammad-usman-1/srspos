<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('mkt_price', 12, 2)->nullable()->after('price');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('mkt_price', 12, 2)->nullable()->after('price');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('tendered', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn(Blueprint $table) => $table->dropColumn('tendered'));
        Schema::table('order_items', fn(Blueprint $table) => $table->dropColumn('mkt_price'));
        Schema::table('products', fn(Blueprint $table) => $table->dropColumn('mkt_price'));
    }
};
