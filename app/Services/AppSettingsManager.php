<?php

namespace App\Services;

use App\Enums\VerificationMethod;
use App\Models\AppSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppSettingsManager
{
    /**
     * Set the theme of the application
     */
    public function setTheme(string $theme): bool
    {
        AppSettings::updateOrCreate(['name' => 'theme'], ['value' => $theme]);

        return true;
    }

    /**
     * Get current theme set
     */
    public function getTheme(): string
    {
        return AppSettings::where('name', 'theme')->first()->value;
    }

    /**
     * Set MFA configurations
     */
    public function setMfaConfig(bool $enabled, bool $allowApiManagement = true, VerificationMethod ...$mfaMethods): bool
    {
        $stepsInArrayVal = array_map(fn (VerificationMethod $option) => $option->value, $mfaMethods);
        $stepsUnique = array_unique($stepsInArrayVal);

        $value = json_encode([
            'enabled' => $enabled,
            'steps' => $stepsUnique,
            'allow_api_management' => $allowApiManagement,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        return true;
    }

    /**
     * Get the MFA configurations
     */
    public function getMfaConfig(): array
    {
        $value = AppSettings::where('name', 'mfa')->first()->value;

        return json_decode($value, true);
    }

    /**
     * Set the application settings
     *
     * @throws Throwable
     */
    public function setSettings(array $settings): Collection
    {
        return DB::transaction(function () use ($settings) {
            if (isset($settings['theme'])) {
                AppSettings::updateOrCreate(['name' => 'theme'], ['value' => $settings['theme']]);
            }

            if (isset($settings['mfa'])) {
                $mfaValue = $this->jsonEncodeMfaValue($settings['mfa']);
                AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $mfaValue]);
            }

            return AppSettings::all();
        });
    }

    /**
     * Get the current application settings
     */
    public function getSettings(): Collection
    {
        return AppSettings::all();
    }

    private function jsonEncodeMfaValue(array $mfaSettings): string
    {
        $mfaValue = [];

        if (isset($mfaSettings['enabled'])) {
            $mfaValue['enabled'] = $mfaSettings['enabled'];
        }

        if (isset($mfaSettings['steps'])) {
            $mfaValue['steps'] = array_unique($mfaSettings['steps']);
        }

        if (isset($mfaSettings['allow_api_management'])) {
            $mfaValue['allow_api_management'] = $mfaSettings['allow_api_management'];
        }

        // We set the current if the enabled flag is not given
        $currentMfaConfig = AppSettings::where('name', 'mfa')->first();
        if ($currentMfaConfig) {
            $currentMfaValue = json_decode($currentMfaConfig->value, true);
            if (! isset($mfaValue['enabled'])) {
                $mfaValue['enabled'] = $currentMfaValue['enabled'];
            }

            if (! isset($mfaValue['steps'])) {
                $mfaValue['steps'] = $currentMfaValue['steps'];
            }

            if (! isset($mfaValue['allow_api_management'])) {
                $mfaValue['allow_api_management'] = $currentMfaValue['allow_api_management'];
            }
        }

        return json_encode($mfaValue);
    }
}
