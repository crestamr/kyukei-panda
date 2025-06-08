<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Native\Laravel\Facades\App;
use Native\Laravel\Facades\System;
use Native\Laravel\Enums\SystemThemesEnum;

class NativeAppService
{
    /**
     * Check if the native app is available.
     */
    public function isNativeAppAvailable(): bool
    {
        try {
            // Try to make a simple call to check if native app is running
            App::openAtLogin();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Safely get open at login status.
     */
    public function getOpenAtLogin(): bool
    {
        try {
            return App::openAtLogin();
        } catch (\Exception $e) {
            Log::debug('Native app not available for openAtLogin check: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Safely set open at login status.
     */
    public function setOpenAtLogin(bool $openAtLogin): bool
    {
        try {
            App::openAtLogin($openAtLogin);
            return true;
        } catch (\Exception $e) {
            Log::debug('Native app not available for openAtLogin setting: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Safely get system theme.
     */
    public function getSystemTheme(): ?SystemThemesEnum
    {
        try {
            return System::theme();
        } catch (\Exception $e) {
            Log::debug('Native app not available for theme check: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely set system theme.
     */
    public function setSystemTheme(SystemThemesEnum $theme): bool
    {
        try {
            System::theme($theme);
            return true;
        } catch (\Exception $e) {
            Log::debug('Native app not available for theme setting: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get native app status for debugging.
     */
    public function getStatus(): array
    {
        $status = [
            'available' => false,
            'open_at_login' => false,
            'theme' => null,
            'error' => null,
        ];

        try {
            $status['available'] = true;
            $status['open_at_login'] = App::openAtLogin();
            $status['theme'] = System::theme()?->value;
        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
            $status['available'] = false;
        }

        return $status;
    }

    /**
     * Send notification through native app.
     */
    public function sendNotification(string $title, string $body): bool
    {
        try {
            \Native\Laravel\Facades\Notification::title($title)
                ->message($body)
                ->show();
            return true;
        } catch (\Exception $e) {
            Log::debug('Native notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Show panda break notification.
     */
    public function showPandaBreakNotification(int $pandaCount = 1): bool
    {
        $pandas = str_repeat('🐼', $pandaCount);
        $title = "Kyukei-Panda Break Time! {$pandas}";
        $body = "Time for a healthy break! You've earned {$pandaCount} panda" . ($pandaCount > 1 ? 's' : '') . "!";
        
        return $this->sendNotification($title, $body);
    }

    /**
     * Show productivity milestone notification.
     */
    public function showProductivityMilestone(string $milestone, float $score): bool
    {
        $title = "🎉 Productivity Milestone Achieved!";
        $body = "{$milestone} - Score: " . round($score * 100) . "%";
        
        return $this->sendNotification($title, $body);
    }

    /**
     * Get native app configuration.
     */
    public function getConfiguration(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_version' => config('nativephp.version', '1.0.0'),
            'app_id' => config('nativephp.app_id'),
            'deep_link_scheme' => config('nativephp.deeplink_scheme'),
            'updater_enabled' => config('nativephp.updater.enabled', false),
            'window_config' => [
                'width' => config('nativephp.window.width', 1200),
                'height' => config('nativephp.window.height', 800),
                'min_width' => config('nativephp.window.min_width', 800),
                'min_height' => config('nativephp.window.min_height', 600),
                'resizable' => config('nativephp.window.resizable', true),
                'always_on_top' => config('nativephp.window.always_on_top', false),
            ],
        ];
    }

    /**
     * Check if running in native environment.
     */
    public function isRunningInNative(): bool
    {
        return app()->bound('native.app') || 
               isset($_SERVER['NATIVEPHP']) || 
               config('app.env') === 'native';
    }

    /**
     * Get platform information.
     */
    public function getPlatformInfo(): array
    {
        try {
            return [
                'platform' => PHP_OS_FAMILY,
                'is_native' => $this->isRunningInNative(),
                'is_available' => $this->isNativeAppAvailable(),
                'node_version' => $this->getNodeVersion(),
                'electron_version' => $this->getElectronVersion(),
            ];
        } catch (\Exception $e) {
            return [
                'platform' => PHP_OS_FAMILY,
                'is_native' => false,
                'is_available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Node.js version.
     */
    private function getNodeVersion(): ?string
    {
        try {
            $output = shell_exec('node --version 2>&1');
            return trim($output);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Electron version.
     */
    private function getElectronVersion(): ?string
    {
        try {
            $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
            return $packageJson['devDependencies']['electron'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
