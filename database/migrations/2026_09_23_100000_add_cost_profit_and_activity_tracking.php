<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking needed for proper profit reports and a full audit trail:
 *  - order_items.cost_price: the product's unit cost frozen at the moment of sale, so
 *    profit stays correct after the cost changes later (never shown on the bill).
 *  - purchases.reference_no + purchase_payments: supplier invoice number and every
 *    payment made to the supplier (paid / due per purchase).
 *  - activity_logs: who did what and when (sales, payments, purchases, stock, prices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->nullable()->after('price');
        });

        // Best available cost for sales made before this existed: the product's current cost.
        DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNull('order_items.cost_price')
            ->update(['order_items.cost_price' => DB::raw('products.purchase_price')]);

        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('reference_no', 100)->nullable()->after('purchase_date');
        });

        Schema::create('purchase_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('method', 30)->default('cash');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('purchase_payments');
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('reference_no');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('cost_price');
        });
    }
};
