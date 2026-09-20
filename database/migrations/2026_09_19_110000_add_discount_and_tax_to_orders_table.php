<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('discount', 12, 2)->default(0)->after('user_id');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount', 'tax_rate', 'tax_amount']);
        });
    }
};
