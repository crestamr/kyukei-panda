<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActivityHistory;
use App\Services\ActivityCategorizationService;
use Illuminate\Console\Command;

class CategorizeActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyukei-panda:categorize-activities {--force : Force re-categorization of all activities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Categorize existing activity history using AI-powered categorization';

    /**
     * Execute the console command.
     */
    public function handle(ActivityCategorizationService $categorizationService): int
    {
        $this->info('🐼 Kyukei-Panda Activity Categorization');
        $this->info('=====================================');

        $force = $this->option('force');

        if ($force) {
            $this->warn('Force mode enabled - will re-categorize all activities');
        }

        // Get activity histories to categorize
        $activityHistories = ActivityHistory::all()->filter(function ($activityHistory) use ($force) {
            return $force || !$activityHistory->isMigrated();
        });
        $total = $activityHistories->count();

        if ($total === 0) {
            $this->info('No activities to categorize.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} activities to categorize...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $categorized = 0;
        $errors = 0;

        foreach ($activityHistories as $activityHistory) {
            try {
                $categorizationService->categorizeActivityHistory($activityHistory);
                $categorized++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error categorizing activity {$activityHistory->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Categorization complete!");
        $this->info("📊 Results:");
        $this->info("   - Successfully categorized: {$categorized}");

        if ($errors > 0) {
            $this->warn("   - Errors: {$errors}");
        }

        // Show category breakdown
        $this->showCategoryBreakdown();

        return Command::SUCCESS;
    }

    /**
     * Show breakdown of categories created.
     */
    private function showCategoryBreakdown(): void
    {
        $this->newLine();
        $this->info("📈 Category Breakdown:");

        $categories = \App\Models\Category::withCount('activities')
            ->orderBy('activities_count', 'desc')
            ->get();

        $headers = ['Category', 'Activities', 'Productivity Score', 'Color'];
        $rows = [];

        foreach ($categories as $category) {
            $rows[] = [
                $category->name,
                $category->activities_count,
                $category->productivity_score,
                $category->color
            ];
        }

        $this->table($headers, $rows);
    }
}
