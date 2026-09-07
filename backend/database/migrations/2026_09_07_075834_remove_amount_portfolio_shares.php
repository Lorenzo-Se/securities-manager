<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('portfolio_shares')
            ->where('share_type', 'amount')
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
