<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Set by the POS browser so a sale that is uploaded twice is only saved once.
            $table->uuid('client_uuid')->nullable()->unique()->after('user_id');
            // Bill number printed while offline (e.g. OFF-0007), kept for reference.
            $table->string('offline_ref', 30)->nullable()->after('client_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['client_uuid', 'offline_ref']);
        });
    }
};
