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
    public function setMfaConfig(bool $enabled, VerificationMethod ...$mfaOptions): bool
    {
        $stepsInArrayVal = array_map(fn (VerificationMethod $option) => $option->value, $mfaOptions);
        $stepsUnique = array_unique($stepsInArrayVal);

        $value = json_encode([
            'enabled' => $enabled,
            'steps' => $stepsUnique,
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
                $mfaValue = $this->json_encode_mfa_value($settings['mfa']);
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

    private function json_encode_mfa_value(array $mfaSettings): string
    {
        $mfaValue = [];

        if (isset($mfaSettings['enabled'])) {
            $mfaValue['enabled'] = $mfaSettings['enabled'];
        }
        if (isset($mfaSettings['steps'])) {
            $mfaValue['steps'] = array_unique($mfaSettings['steps']);
        }

        // We set the current if the enabled flag is not given
        $currentMfaConfig = AppSettings::where('name', 'mfa')->first();
        if ($currentMfaConfig) {
            $currentMfaValue = json_decode($currentMfaConfig->value, true);
            if (! isset($mfaValue['enabled'])) {
                $currentEnabledValue = $currentMfaValue['enabled'];
                $mfaValue['enabled'] = $currentEnabledValue;
            }

            // We set the current if the steps are not given
            if (! isset($mfaValue['steps'])) {
                $currentStepsValue = $currentMfaValue['steps'];
                $mfaValue['steps'] = $currentStepsValue;
            }
        }

        return json_encode($mfaValue);
    }
}
