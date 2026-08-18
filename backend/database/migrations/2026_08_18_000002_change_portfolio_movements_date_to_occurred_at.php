<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('portfolio_movements', 'date')) {
            return;
        }

        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->after('amount');
        });

        foreach (DB::table('portfolio_movements')->get() as $movement) {
            DB::table('portfolio_movements')
                ->where('id', $movement->id)
                ->update(['occurred_at' => $movement->date.' 00:00:00']);
        }

        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('portfolio_movements', 'occurred_at')) {
            return;
        }

        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->date('date')->nullable()->after('amount');
        });

        foreach (DB::table('portfolio_movements')->get() as $movement) {
            DB::table('portfolio_movements')
                ->where('id', $movement->id)
                ->update(['date' => substr((string) $movement->occurred_at, 0, 10)]);
        }

        Schema::table('portfolio_movements', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
    }
};
