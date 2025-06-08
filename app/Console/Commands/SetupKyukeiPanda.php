<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupKyukeiPanda extends Command
{
    protected $signature = 'kyukei-panda:setup {--force : Force setup even if already configured}';
    protected $description = 'Set up Kyukei-Panda application with proper configuration';

    public function handle(): int
    {
        $this->info('🐼 Setting up Kyukei-Panda Ultimate Platform...');
        $this->newLine();

        // Check if .env exists
        if (!File::exists(base_path('.env'))) {
            $this->createEnvironmentFile();
        }

        // Fix configuration issues
        $this->fixConfiguration();

        // Set up database
        $this->setupDatabase();

        // Set up cache and sessions
        $this->setupCache();

        // Set up real-time features
        $this->setupRealTimeFeatures();

        // Generate application key if needed
        $this->generateAppKey();

        // Run migrations
        $this->runMigrations();

        // Clear and cache config
        $this->optimizeApplication();

        $this->newLine();
        $this->info('🎉 Kyukei-Panda setup completed successfully!');
        $this->info('🌐 You can now access your application at: ' . config('app.url'));
        $this->newLine();

        return self::SUCCESS;
    }

    private function createEnvironmentFile(): void
    {
        $this->info('📝 Creating environment file...');
        
        if (File::exists(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->line('✅ Environment file created from .env.example');
        } else {
            $this->createBasicEnvFile();
            $this->line('✅ Basic environment file created');
        }
    }

    private function createBasicEnvFile(): void
    {
        $envContent = <<<ENV
APP_NAME="Kyukei-Panda"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=sqlite
DB_DATABASE=database/kyukei_panda.sqlite

BROADCAST_CONNECTION=null
CACHE_STORE=file
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Pusher Configuration (Optional)
PUSHER_APP_ID=kyukei-panda-app
PUSHER_APP_KEY=kyukei-panda-key
PUSHER_APP_SECRET=kyukei-panda-secret
PUSHER_APP_CLUSTER=mt1

# Kyukei-Panda WebSocket
KYUKEI_WEBSOCKET_KEY=kyukei-panda-local
KYUKEI_WEBSOCKET_SECRET=kyukei-panda-secret
KYUKEI_WEBSOCKET_APP_ID=kyukei-panda

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@kyukei-panda.com"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"
ENV;

        File::put(base_path('.env'), $envContent);
    }

    private function fixConfiguration(): void
    {
        $this->info('🔧 Fixing configuration issues...');
        
        $configService = app(ConfigurationService::class);
        $result = $configService->validateAndFixConfiguration();
        
        if ($result['issues_found']) {
            foreach ($result['fixes_applied'] as $component => $fixes) {
                foreach ($fixes as $fix) {
                    $this->line("  ✅ {$component}: {$fix}");
                }
            }
        } else {
            $this->line('✅ No configuration issues found');
        }
    }

    private function setupDatabase(): void
    {
        $this->info('🗄️ Setting up database...');
        
        $dbConnection = config('database.default');
        
        if ($dbConnection === 'sqlite') {
            $dbPath = database_path('kyukei_panda.sqlite');
            if (!File::exists($dbPath)) {
                File::put($dbPath, '');
                $this->line('✅ SQLite database file created');
            }
        }
        
        try {
            \DB::connection()->getPdo();
            $this->line('✅ Database connection successful');
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed: ' . $e->getMessage());
            $this->line('💡 Please check your database configuration in .env file');
        }
    }

    private function setupCache(): void
    {
        $this->info('💾 Setting up cache and sessions...');
        
        try {
            \Cache::put('setup_test', 'success', 1);
            if (\Cache::get('setup_test') === 'success') {
                $this->line('✅ Cache is working');
            }
        } catch (\Exception $e) {
            $this->warn('⚠️ Cache setup issue: ' . $e->getMessage());
        }
    }

    private function setupRealTimeFeatures(): void
    {
        $this->info('⚡ Setting up real-time features...');
        
        $broadcastDriver = config('broadcasting.default');
        
        if ($broadcastDriver === 'null') {
            $this->line('ℹ️ Real-time features disabled (using null driver)');
            $this->line('💡 To enable real-time features, configure Pusher or use Kyukei WebSocket');
        } else {
            $this->line("✅ Real-time features configured with {$broadcastDriver} driver");
        }
    }

    private function generateAppKey(): void
    {
        if (empty(config('app.key'))) {
            $this->info('🔑 Generating application key...');
            $this->call('key:generate');
        } else {
            $this->line('✅ Application key already exists');
        }
    }

    private function runMigrations(): void
    {
        $this->info('🏗️ Running database migrations...');
        
        try {
            $this->call('migrate', ['--force' => true]);
            $this->line('✅ Database migrations completed');
        } catch (\Exception $e) {
            $this->error('❌ Migration failed: ' . $e->getMessage());
        }
    }

    private function optimizeApplication(): void
    {
        $this->info('⚡ Optimizing application...');
        
        try {
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('view:cache');
            $this->line('✅ Application optimized');
        } catch (\Exception $e) {
            $this->warn('⚠️ Optimization warning: ' . $e->getMessage());
        }
    }
}
