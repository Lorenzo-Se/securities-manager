<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('wallet_source_type')->nullable()->after('crypto_name');
            $table->text('wallet_source')->nullable()->after('wallet_source_type');
            $table->string('chain_slug')->nullable()->after('wallet_source');
            $table->string('token_contract')->nullable()->after('chain_slug');
            $table->timestamp('last_synced_at')->nullable()->after('token_contract');
            $table->string('sync_status')->default('failed')->after('last_synced_at');
            $table->text('sync_error')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_source_type',
                'wallet_source',
                'chain_slug',
                'token_contract',
                'last_synced_at',
                'sync_status',
                'sync_error',
            ]);
        });
    }
};
