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
        Schema::create('panda_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('slack_user_id');
            $table->string('slack_channel_id');
            $table->string('slack_message_ts');
            $table->string('channel_name')->nullable();
            $table->integer('panda_count')->default(1);
            $table->integer('break_duration')->default(10); // minutes
            $table->timestamp('break_timestamp');
            $table->boolean('is_valid')->default(true);
            $table->text('message_text')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'break_timestamp']);
            $table->index(['slack_user_id', 'break_timestamp']);
            $table->index(['break_timestamp', 'is_valid']);
            $table->unique(['slack_channel_id', 'slack_message_ts']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panda_breaks');
    }
};
