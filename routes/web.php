<?php

declare(strict_types=1);

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\AiInsightsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppActivityController;
use App\Http\Controllers\BugAndFeedbackController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Export\CsvController;
use App\Http\Controllers\Export\ExcelController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Import\ClockifyController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\MenubarController;
use App\Http\Controllers\Overview\DayController;
use App\Http\Controllers\Overview\MonthController;
use App\Http\Controllers\Overview\WeekController;
use App\Http\Controllers\Overview\YearController;
use App\Http\Controllers\PandaDashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\StartStopController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimestampController;
use App\Http\Controllers\UpdaterController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WindowController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('overview.week.index'))->name('home');

Route::name('overview.')->prefix('overview')->group(function (): void {
    Route::resource('day', DayController::class)->only(['index', 'show'])->parameter('day', 'date');
    Route::resource('week', WeekController::class)->only(['index', 'show'])->parameter('week', 'date');
    Route::resource('month', MonthController::class)->only(['index', 'show'])->parameter('month', 'date');
    Route::resource('year', YearController::class)->only(['index', 'show'])->parameter('year', 'date');
});

Route::get('quit', fn () => \Native\Laravel\Facades\App::quit())->name('quit');

Route::get('welcome', [WelcomeController::class, 'index'])->name('welcome.index');
Route::patch('welcome', [WelcomeController::class, 'update'])->name('welcome.update');
Route::get('welcome/finish/{openSettings?}', [WelcomeController::class, 'finish'])->name('welcome.finish');

Route::name('menubar.')->prefix('menubar')->group(function (): void {
    Route::get('', [MenubarController::class, 'index'])->name('index');
    Route::post('break', [MenubarController::class, 'storeBreak'])->name('storeBreak');
    Route::post('work', [MenubarController::class, 'storeWork'])->name('storeWork');
    Route::post('stop', [MenubarController::class, 'storeStop'])->name('storeStop');
});

Route::name('window.')->prefix('window')->group(function (): void {
    Route::get('updater/{darkMode}', [WindowController::class, 'openUpdater'])->name('updater.open');
    Route::get('overview/{darkMode}', [WindowController::class, 'openOverview'])->name('overview.open');
    Route::get('settings/{darkMode}', [WindowController::class, 'openSettings'])->name('settings.open');
});

Route::name('settings.')->prefix('settings')->group(function (): void {
    Route::get('', fn () => redirect()->route('settings.general.edit'))->name('index');
    Route::name('general.')->prefix('general')->group(function (): void {
        Route::get('edit', [GeneralController::class, 'edit'])->name('edit');
        Route::patch('', [GeneralController::class, 'update'])->name('update');
        Route::patch('locale', [GeneralController::class, 'updateLocale'])->name('updateLocale');
    });
    Route::name('start-stop.')->prefix('start-stop')->group(function (): void {
        Route::get('edit', [StartStopController::class, 'edit'])->name('edit');
        Route::patch('', [StartStopController::class, 'update'])->name('update');
    });
});

Route::name('updater.')->prefix('updater')->group(function (): void {
    Route::get('', [UpdaterController::class, 'index'])->name('index');
    Route::patch('auto-update', [UpdaterController::class, 'updateAutoUpdate'])->name('updateAutoUpdate');
    Route::post('install', [UpdaterController::class, 'install'])->name('install');
    Route::post('check', [UpdaterController::class, 'check'])->name('check');
});

Route::resource('import-export', ImportExportController::class);
Route::name('import.')->prefix('import')->group(function (): void {
    Route::resource('clockify', ClockifyController::class)->only(['create', 'store']);
});
Route::name('export.')->prefix('export')->group(function (): void {
    Route::post('csv', CsvController::class)->name('csv');
    Route::post('excel', ExcelController::class)->name('excel');
});

Route::resource('work-schedule', WorkScheduleController::class)->only('index', 'create', 'store', 'edit', 'update', 'destroy');

Route::resource('app-activity', AppActivityController::class)->only(['index', 'show']);

Route::name('absence.')->prefix('absence')->group(function (): void {
    Route::get('', [AbsenceController::class, 'index'])->name('index');
    Route::get('{date}', [AbsenceController::class, 'show'])->name('show');
    Route::post('{date}', [AbsenceController::class, 'store'])->name('store');
    Route::delete('{date}/{absence}', [AbsenceController::class, 'destroy'])->name('destroy');
});

Route::get('timestamp/create/{datetime}/{endDatetime?}', [TimestampController::class, 'create'])->name('timestamp.create')
    ->where('endDatetime', '\d{4}-\d{2}-\d{2}\s\d{2}\:\d{2}\:\d{2}');
Route::post('timestamp/{datetime}', [TimestampController::class, 'store'])->name('timestamp.store');
Route::resource('timestamp', TimestampController::class)->only(['edit', 'update', 'destroy']);
Route::post('timestamp/fill', [TimestampController::class, 'fill'])->name('timestamp.fill');

Route::name('bug-and-feedback.')->prefix('bug-and-feedback')->group(function (): void {
    Route::get('', [BugAndFeedbackController::class, 'index'])->name('index');
    Route::get('export', [BugAndFeedbackController::class, 'export'])->name('export');
    Route::get('import', [BugAndFeedbackController::class, 'import'])->name('import');
});

// Kyukei-Panda Dashboard Routes
Route::name('panda.')->prefix('panda')->group(function (): void {
    Route::get('dashboard', [PandaDashboardController::class, 'index'])->name('dashboard');
    Route::get('status', [PandaDashboardController::class, 'status'])->name('status');
});

// Test route to check if user issue is fixed
Route::get('/test-user-fix', [App\Http\Controllers\TestController::class, 'testUserFix']);

// Test panda dashboard controller
Route::get('/test-panda-dashboard', function () {
    try {
        $user = App\Models\User::first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No users found',
                'user_count' => App\Models\User::count(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User found successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'user_count' => App\Models\User::count(),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
        ]);
    }
});

// Test panda dashboard controller JSON response
Route::get('/test-panda-json', function () {
    try {
        $controller = new App\Http\Controllers\PandaDashboardController();
        $request = new Illuminate\Http\Request();

        // Get the data that would be passed to the view
        $user = App\Models\User::first();
        $today = Carbon\Carbon::today();

        $dailyUsage = App\Models\DailyPandaLimit::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Controller data retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'daily_usage' => [
                    'pandas_used' => $dailyUsage?->pandas_used ?? 0,
                    'total_break_minutes' => $dailyUsage?->total_break_minutes ?? 0,
                ],
                'today' => $today->toDateString(),
            ],
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'trace' => $e->getTraceAsString(),
        ]);
    }
});

// Analytics Routes
Route::name('analytics.')->prefix('analytics')->group(function (): void {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('dashboard', [AnalyticsController::class, 'dashboard'])->name('dashboard');
    Route::get('team', [AnalyticsController::class, 'team'])->name('team');
    Route::get('productivity', [AnalyticsController::class, 'productivity'])->name('productivity');
    Route::get('export', [AnalyticsController::class, 'export'])->name('export');
});

// Team Management Routes
Route::resource('teams', TeamController::class);
Route::post('teams/{team}/invite', [TeamController::class, 'invite'])->name('teams.invite');
Route::delete('teams/{team}/members/{member}', [TeamController::class, 'removeMember'])->name('teams.remove-member');
Route::patch('teams/{team}/members/{member}/role', [TeamController::class, 'updateMemberRole'])->name('teams.update-member-role');

// Project Management Routes
Route::resource('projects', ProjectController::class);

// Client Management Routes
Route::resource('clients', ClientController::class);

// Reports Routes
Route::name('reports.')->prefix('reports')->group(function (): void {
    Route::get('/', [ReportsController::class, 'index'])->name('index');
    Route::get('time-tracking', [ReportsController::class, 'timeTracking'])->name('time-tracking');
    Route::get('team-summary', [ReportsController::class, 'teamSummary'])->name('team-summary');
    Route::get('client-billing', [ReportsController::class, 'clientBilling'])->name('client-billing');
    Route::get('export/time-tracking', [ReportsController::class, 'exportTimeTracking'])->name('export.time-tracking');
    Route::get('export/invoice', [ReportsController::class, 'exportInvoice'])->name('export.invoice');
});

// AI Insights Routes
Route::name('ai.')->prefix('ai')->group(function (): void {
    Route::get('/', [AiInsightsController::class, 'index'])->name('dashboard');
    Route::get('break-predictions', [AiInsightsController::class, 'getBreakPredictions'])->name('break-predictions');
    Route::get('productivity-trends', [AiInsightsController::class, 'getProductivityTrends'])->name('productivity-trends');
    Route::get('anomalies', [AiInsightsController::class, 'detectAnomalies'])->name('anomalies');
    Route::get('team-dynamics', [AiInsightsController::class, 'getTeamDynamics'])->name('team-dynamics');
    Route::get('project-predictions', [AiInsightsController::class, 'getProjectPredictions'])->name('project-predictions');
    Route::get('recommendations', [AiInsightsController::class, 'getPersonalizedRecommendations'])->name('recommendations');
    Route::get('mobile-insights', [AiInsightsController::class, 'getMobileInsights'])->name('mobile-insights');
    Route::get('insights-report', [AiInsightsController::class, 'generateInsightsReport'])->name('insights-report');
});

// Health Check Routes
Route::get('/health', [HealthController::class, 'check'])->name('health.check');
Route::get('/ping', [HealthController::class, 'ping'])->name('health.ping');

// Slack Integration Routes (Kyukei-Panda)
Route::name('slack.')->prefix('slack')->group(function (): void {
    Route::post('events', [SlackController::class, 'events'])->name('events');
    Route::post('commands', [SlackController::class, 'slashCommand'])->name('commands');
});

Route::get('open', function (Request $request): void {
    if (\Native\Laravel\Support\Environment::isWindows()) {
        shell_exec('explorer "'.$request->string('url').'"');
    } else {
        shell_exec('open "'.$request->string('url').'"');
    }
})->name('open');

Route::get('/app-icon/{appIconName}', function ($appIconName) {
    if (! Storage::disk('app-icon')->exists($appIconName)) {
        abort(404);
    }

    return Storage::disk('app-icon')->response($appIconName, null, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('appIconName', '.*')->name('app-icon.show');
