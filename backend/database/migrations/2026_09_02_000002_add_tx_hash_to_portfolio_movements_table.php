<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->string('tx_hash')->nullable()->after('occurred_at');
            $table->string('external_id')->nullable()->after('tx_hash');

            $table->unique(['portfolio_id', 'tx_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->dropUnique(['portfolio_id', 'tx_hash']);
            $table->dropColumn(['tx_hash', 'external_id']);
        });
    }
};
