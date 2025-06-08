<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_histories', function (Blueprint $table): void {
            $table->index(['app_identifier', 'started_at', 'ended_at']);
            $table->unsignedInteger('duration')->after('ended_at')->default(0);
        });

        // PostgreSQL compatible duration calculation
        DB::statement('UPDATE activity_histories SET duration = EXTRACT(EPOCH FROM (ended_at - started_at))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_histories', function (Blueprint $table): void {
            $table->dropIndex(['app_identifier', 'started_at', 'ended_at']);
            $table->dropColumn('duration');
        });
    }
};
