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
        Schema::table('users', function (Blueprint $table) {
            $table->string('slack_user_id')->nullable()->after('email');
            $table->string('slack_username')->nullable()->after('slack_user_id');
            $table->string('timezone')->default('UTC')->after('slack_username');
            $table->string('avatar_url')->nullable()->after('timezone');

            $table->index('slack_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['slack_user_id']);
            $table->dropColumn(['slack_user_id', 'slack_username', 'timezone', 'avatar_url']);
        });
    }
};
