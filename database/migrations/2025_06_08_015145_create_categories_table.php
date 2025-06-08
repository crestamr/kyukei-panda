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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#10B981');
            $table->decimal('productivity_score', 3, 2)->default(0.50); // 0.00-1.00
            $table->boolean('is_productive')->default(true);
            $table->text('description')->nullable();
            $table->json('keywords')->nullable(); // For AI categorization
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('is_global')->default(false); // Global categories for all teams
            $table->timestamps();

            $table->index(['team_id', 'is_productive']);
            $table->index(['is_global', 'is_productive']);
            $table->index('productivity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
