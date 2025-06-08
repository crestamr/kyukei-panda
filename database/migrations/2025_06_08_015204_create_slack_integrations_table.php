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
        Schema::create('slack_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('slack_team_id');
            $table->string('slack_channel_id');
            $table->string('channel_name');
            $table->boolean('is_panda_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->string('bot_token')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'slack_channel_id']);
            $table->index(['slack_team_id', 'is_active']);
            $table->index(['is_panda_enabled', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_integrations');
    }
};
