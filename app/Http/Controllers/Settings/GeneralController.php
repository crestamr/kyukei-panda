<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\HolidayRegionEnum;
use App\Events\LocaleChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateLocaleRequest;
use App\Jobs\CalculateWeekBalance;
use App\Settings\GeneralSettings;
use DateTimeZone;
use Inertia\Inertia;
use Native\Laravel\Enums\SystemThemesEnum;
use Native\Laravel\Facades\App;
use Native\Laravel\Facades\System;
use Illuminate\Support\Facades\Log;
use App\Services\NativeAppService;

class GeneralController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GeneralSettings $settings, NativeAppService $nativeApp)
    {
        return Inertia::render('Settings/General/Edit', [
            'openAtLogin' => $nativeApp->getOpenAtLogin(),
            'theme' => $settings->theme ?? SystemThemesEnum::SYSTEM->value,
            'showTimerOnUnlock' => $settings->showTimerOnUnlock,
            'holidayRegion' => $settings->holidayRegion,
            'holidayRegions' => HolidayRegionEnum::toArray(),
            'locale' => $settings->locale,
            'appActivityTracking' => $settings->appActivityTracking,
            'timezones' => DateTimeZone::listIdentifiers(),
            'timezone' => $settings->timezone,
            'nativeAppStatus' => $nativeApp->getStatus(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGeneralSettingsRequest $request, GeneralSettings $settings, NativeAppService $nativeApp)
    {
        $data = $request->validated();

        $settings->showTimerOnUnlock = $data['showTimerOnUnlock'];
        $settings->holidayRegion = $data['holidayRegion'];
        $settings->appActivityTracking = $data['appActivityTracking'];
        $settings->timezone = $data['timezone'];

        if ($data['theme'] !== $settings->theme ?? SystemThemesEnum::SYSTEM->value) {
            $settings->theme = $data['theme'];
            $nativeApp->setSystemTheme(SystemThemesEnum::tryFrom($data['theme']));
        }

        if ($data['locale'] !== $settings->locale) {
            $settings->locale = $data['locale'];
            LocaleChanged::broadcast();
        }

        if ($data['openAtLogin'] !== $nativeApp->getOpenAtLogin()) {
            $nativeApp->setOpenAtLogin($data['openAtLogin']);
        }

        $settings->save();

        CalculateWeekBalance::dispatch();

        return redirect()->route('settings.general.edit');
    }

    public function updateLocale(UpdateLocaleRequest $request, GeneralSettings $settings): void
    {
        $data = $request->validated();
        if ($data['locale'] !== $settings->locale) {

            $settings->locale = $data['locale'];
            $settings->save();
            LocaleChanged::broadcast();
        }
    }

    /**
     * Safely get open at login status without throwing connection errors.
     */
    private function safeGetOpenAtLogin(): bool
    {
        try {
            return App::openAtLogin();
        } catch (\Exception $e) {
            Log::debug('Native app not available for openAtLogin check: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Safely set open at login status without throwing connection errors.
     */
    private function safeSetOpenAtLogin(bool $openAtLogin): void
    {
        try {
            App::openAtLogin($openAtLogin);
        } catch (\Exception $e) {
            Log::debug('Native app not available for openAtLogin setting: ' . $e->getMessage());
        }
    }

    /**
     * Safely set system theme without throwing connection errors.
     */
    private function safeSetSystemTheme(SystemThemesEnum $theme): void
    {
        try {
            System::theme($theme);
        } catch (\Exception $e) {
            Log::debug('Native app not available for theme setting: ' . $e->getMessage());
        }
    }
}
