<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_panda_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('pandas_used')->default(0);
            $table->integer('total_break_minutes')->default(0);
            $table->timestamp('limit_exceeded_at')->nullable();
            $table->timestamp('first_break_at')->nullable();
            $table->timestamp('last_break_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'pandas_used']);
            $table->index(['user_id', 'date', 'pandas_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_panda_limits');
    }
};
