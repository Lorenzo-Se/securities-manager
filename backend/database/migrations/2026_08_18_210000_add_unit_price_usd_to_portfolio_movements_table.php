<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->decimal('unit_price_usd', 24, 8)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->dropColumn('unit_price_usd');
        });
    }
};
