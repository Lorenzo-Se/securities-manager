<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('invited_email');
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('share_type');
            $table->decimal('share_amount', 20, 8)->nullable();
            $table->decimal('share_percentage', 5, 2)->nullable();
            $table->boolean('show_total_balance')->default(false);
            $table->string('status');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index('invited_email');
            $table->index(['invited_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_shares');
    }
};
