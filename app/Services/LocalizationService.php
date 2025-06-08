<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LocalizationService
{
    private const SUPPORTED_LOCALES = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸', 'rtl' => false],
        'ja' => ['name' => '日本語', 'flag' => '🇯🇵', 'rtl' => false],
        'es' => ['name' => 'Español', 'flag' => '🇪🇸', 'rtl' => false],
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷', 'rtl' => false],
        'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪', 'rtl' => false],
        'zh' => ['name' => '中文', 'flag' => '🇨🇳', 'rtl' => false],
        'ko' => ['name' => '한국어', 'flag' => '🇰🇷', 'rtl' => false],
        'pt' => ['name' => 'Português', 'flag' => '🇧🇷', 'rtl' => false],
        'it' => ['name' => 'Italiano', 'flag' => '🇮🇹', 'rtl' => false],
        'ru' => ['name' => 'Русский', 'flag' => '🇷🇺', 'rtl' => false],
        'ar' => ['name' => 'العربية', 'flag' => '🇸🇦', 'rtl' => true],
        'hi' => ['name' => 'हिन्दी', 'flag' => '🇮🇳', 'rtl' => false],
    ];

    private const TIMEZONE_MAPPINGS = [
        'en' => 'America/New_York',
        'ja' => 'Asia/Tokyo',
        'es' => 'Europe/Madrid',
        'fr' => 'Europe/Paris',
        'de' => 'Europe/Berlin',
        'zh' => 'Asia/Shanghai',
        'ko' => 'Asia/Seoul',
        'pt' => 'America/Sao_Paulo',
        'it' => 'Europe/Rome',
        'ru' => 'Europe/Moscow',
        'ar' => 'Asia/Riyadh',
        'hi' => 'Asia/Kolkata',
    ];

    /**
     * Get all supported locales with metadata.
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Set application locale and update user preferences.
     */
    public function setLocale(string $locale, ?int $userId = null): bool
    {
        if (!$this->isLocaleSupported($locale)) {
            Log::warning("Unsupported locale requested: {$locale}");
            return false;
        }

        // Set application locale
        App::setLocale($locale);

        // Set Carbon locale for date formatting
        Carbon::setLocale($locale);

        // Update user preferences if user is provided
        if ($userId) {
            $this->updateUserLocalePreference($userId, $locale);
        }

        // Cache locale for session
        Cache::put("user_locale_{$userId}", $locale, 86400);

        Log::info("Locale set to {$locale} for user {$userId}");
        return true;
    }

    /**
     * Get user's preferred locale.
     */
    public function getUserLocale(?int $userId = null): string
    {
        if ($userId) {
            // Check cache first
            $cachedLocale = Cache::get("user_locale_{$userId}");
            if ($cachedLocale && $this->isLocaleSupported($cachedLocale)) {
                return $cachedLocale;
            }

            // Check database
            $user = \App\Models\User::find($userId);
            if ($user && $user->locale && $this->isLocaleSupported($user->locale)) {
                Cache::put("user_locale_{$userId}", $user->locale, 86400);
                return $user->locale;
            }
        }

        // Detect from browser
        $browserLocale = $this->detectBrowserLocale();
        if ($browserLocale) {
            return $browserLocale;
        }

        // Default to English
        return 'en';
    }

    /**
     * Get localized panda messages for different contexts.
     */
    public function getPandaMessages(string $context = 'break'): array
    {
        $locale = App::getLocale();
        
        $messages = [
            'en' => [
                'break' => [
                    'Time for a panda break! 🐼',
                    'Your productivity panda suggests a break! 🐼',
                    'Panda wisdom: Take a moment to recharge! 🐼',
                    'Break time! Even pandas need rest! 🐼',
                ],
                'productivity' => [
                    'Great work! Your panda is proud! 🐼',
                    'Productivity level: Panda Master! 🐼',
                    'You\'re on fire! Keep it up! 🐼',
                    'Excellent focus! Panda approved! 🐼',
                ],
                'encouragement' => [
                    'You\'ve got this! Panda believes in you! 🐼',
                    'Stay strong! Your panda is cheering! 🐼',
                    'Keep going! Panda power! 🐼',
                    'Almost there! Panda motivation! 🐼',
                ],
            ],
            'ja' => [
                'break' => [
                    'パンダ休憩の時間です！🐼',
                    'あなたの生産性パンダが休憩を提案しています！🐼',
                    'パンダの知恵：充電する時間を取りましょう！🐼',
                    '休憩時間！パンダも休息が必要です！🐼',
                ],
                'productivity' => [
                    '素晴らしい仕事！あなたのパンダが誇りに思っています！🐼',
                    '生産性レベル：パンダマスター！🐼',
                    '絶好調ですね！その調子で！🐼',
                    '素晴らしい集中力！パンダ承認！🐼',
                ],
                'encouragement' => [
                    'あなたならできる！パンダがあなたを信じています！🐼',
                    '頑張って！あなたのパンダが応援しています！🐼',
                    '続けて！パンダパワー！🐼',
                    'もう少し！パンダモチベーション！🐼',
                ],
            ],
            'es' => [
                'break' => [
                    '¡Hora de un descanso panda! 🐼',
                    '¡Tu panda de productividad sugiere un descanso! 🐼',
                    'Sabiduría panda: ¡Tómate un momento para recargar! 🐼',
                    '¡Hora del descanso! ¡Incluso los pandas necesitan descansar! 🐼',
                ],
                'productivity' => [
                    '¡Excelente trabajo! ¡Tu panda está orgulloso! 🐼',
                    'Nivel de productividad: ¡Maestro Panda! 🐼',
                    '¡Estás en llamas! ¡Sigue así! 🐼',
                    '¡Excelente concentración! ¡Aprobado por el panda! 🐼',
                ],
                'encouragement' => [
                    '¡Tú puedes! ¡El panda cree en ti! 🐼',
                    '¡Mantente fuerte! ¡Tu panda te está animando! 🐼',
                    '¡Sigue adelante! ¡Poder panda! 🐼',
                    '¡Casi ahí! ¡Motivación panda! 🐼',
                ],
            ],
        ];

        $localeMessages = $messages[$locale] ?? $messages['en'];
        return $localeMessages[$context] ?? $localeMessages['break'];
    }

    /**
     * Format time duration according to locale.
     */
    public function formatDuration(int $seconds, ?string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return match($locale) {
            'ja' => $hours > 0 ? "{$hours}時間{$minutes}分" : "{$minutes}分",
            'zh' => $hours > 0 ? "{$hours}小时{$minutes}分钟" : "{$minutes}分钟",
            'ko' => $hours > 0 ? "{$hours}시간 {$minutes}분" : "{$minutes}분",
            'es' => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
            'fr' => $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min",
            'de' => $hours > 0 ? "{$hours}Std {$minutes}Min" : "{$minutes}Min",
            'pt' => $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min",
            'it' => $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min",
            'ru' => $hours > 0 ? "{$hours}ч {$minutes}мин" : "{$minutes}мин",
            'ar' => $hours > 0 ? "{$hours}س {$minutes}د" : "{$minutes}د",
            'hi' => $hours > 0 ? "{$hours}घं {$minutes}मि" : "{$minutes}मि",
            default => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
        };
    }

    /**
     * Get localized date format.
     */
    public function getDateFormat(?string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();

        return match($locale) {
            'ja' => 'Y年m月d日',
            'zh' => 'Y年m月d日',
            'ko' => 'Y년 m월 d일',
            'de' => 'd.m.Y',
            'fr' => 'd/m/Y',
            'es' => 'd/m/Y',
            'pt' => 'd/m/Y',
            'it' => 'd/m/Y',
            'ru' => 'd.m.Y',
            'ar' => 'd/m/Y',
            'hi' => 'd/m/Y',
            default => 'm/d/Y',
        };
    }

    /**
     * Get timezone for locale.
     */
    public function getTimezoneForLocale(?string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();
        return self::TIMEZONE_MAPPINGS[$locale] ?? 'UTC';
    }

    /**
     * Generate localized productivity insights.
     */
    public function getLocalizedInsights(array $data): array
    {
        $locale = App::getLocale();
        $insights = [];

        // Productivity score insight
        $score = $data['productivity_score'] ?? 0;
        if ($score >= 80) {
            $insights[] = $this->getLocalizedMessage('insights.high_productivity', ['score' => $score]);
        } elseif ($score >= 60) {
            $insights[] = $this->getLocalizedMessage('insights.good_productivity', ['score' => $score]);
        } else {
            $insights[] = $this->getLocalizedMessage('insights.low_productivity', ['score' => $score]);
        }

        // Break compliance insight
        $pandasUsed = $data['pandas_used'] ?? 0;
        if ($pandasUsed >= 6) {
            $insights[] = $this->getLocalizedMessage('insights.excellent_breaks');
        } elseif ($pandasUsed >= 3) {
            $insights[] = $this->getLocalizedMessage('insights.good_breaks');
        } else {
            $insights[] = $this->getLocalizedMessage('insights.need_more_breaks');
        }

        return $insights;
    }

    /**
     * Export translations for frontend.
     */
    public function exportTranslationsForFrontend(?string $locale = null): array
    {
        $locale = $locale ?? App::getLocale();
        $cacheKey = "frontend_translations_{$locale}";

        return Cache::remember($cacheKey, 3600, function () use ($locale) {
            $translationPath = resource_path("lang/{$locale}");
            $translations = [];

            if (File::exists($translationPath)) {
                $files = File::files($translationPath);
                foreach ($files as $file) {
                    $key = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $translations[$key] = include $file->getPathname();
                }
            }

            // Add panda-specific translations
            $translations['panda'] = [
                'messages' => $this->getPandaMessages('break'),
                'productivity' => $this->getPandaMessages('productivity'),
                'encouragement' => $this->getPandaMessages('encouragement'),
            ];

            return $translations;
        });
    }

    /**
     * Detect browser locale from Accept-Language header.
     */
    private function detectBrowserLocale(): ?string
    {
        $acceptLanguage = request()->header('Accept-Language');
        if (!$acceptLanguage) {
            return null;
        }

        $languages = explode(',', $acceptLanguage);
        foreach ($languages as $language) {
            $locale = trim(explode(';', $language)[0]);
            $locale = substr($locale, 0, 2); // Get language code only
            
            if ($this->isLocaleSupported($locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Check if locale is supported.
     */
    private function isLocaleSupported(string $locale): bool
    {
        return array_key_exists($locale, self::SUPPORTED_LOCALES);
    }

    /**
     * Update user's locale preference in database.
     */
    private function updateUserLocalePreference(int $userId, string $locale): void
    {
        try {
            \App\Models\User::where('id', $userId)->update([
                'locale' => $locale,
                'timezone' => $this->getTimezoneForLocale($locale),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update user locale preference", [
                'user_id' => $userId,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get localized message with parameters.
     */
    private function getLocalizedMessage(string $key, array $params = []): string
    {
        $message = __($key, $params);
        return $message !== $key ? $message : "Missing translation: {$key}";
    }

    /**
     * Generate RTL CSS classes for right-to-left languages.
     */
    public function getRTLClasses(?string $locale = null): array
    {
        $locale = $locale ?? App::getLocale();
        $isRTL = self::SUPPORTED_LOCALES[$locale]['rtl'] ?? false;

        if (!$isRTL) {
            return [];
        }

        return [
            'dir' => 'rtl',
            'text-align' => 'right',
            'classes' => [
                'rtl',
                'text-right',
                'flex-row-reverse',
            ],
        ];
    }

    /**
     * Get currency format for locale.
     */
    public function formatCurrency(float $amount, ?string $locale = null): string
    {
        $locale = $locale ?? App::getLocale();
        
        $currencies = [
            'en' => ['symbol' => '$', 'position' => 'before'],
            'ja' => ['symbol' => '¥', 'position' => 'before'],
            'es' => ['symbol' => '€', 'position' => 'after'],
            'fr' => ['symbol' => '€', 'position' => 'after'],
            'de' => ['symbol' => '€', 'position' => 'after'],
            'zh' => ['symbol' => '¥', 'position' => 'before'],
            'ko' => ['symbol' => '₩', 'position' => 'before'],
            'pt' => ['symbol' => 'R$', 'position' => 'before'],
            'it' => ['symbol' => '€', 'position' => 'after'],
            'ru' => ['symbol' => '₽', 'position' => 'after'],
            'ar' => ['symbol' => 'ر.س', 'position' => 'after'],
            'hi' => ['symbol' => '₹', 'position' => 'before'],
        ];

        $currency = $currencies[$locale] ?? $currencies['en'];
        $formatted = number_format($amount, 2);

        return $currency['position'] === 'before' 
            ? $currency['symbol'] . $formatted
            : $formatted . ' ' . $currency['symbol'];
    }
}
