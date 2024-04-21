<?php

namespace App\Services\AppSettings;

use App\Enums\MfaMethod;
use App\Models\AppSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppSettingsService implements AppSettingsManager
{
    private AppSettings $model;

    public function __construct(AppSettings $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function setThemeConfig(string $theme): bool
    {
        $this->model::updateOrCreate(['name', 'theme'], ['value' => $theme]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getThemeConfig(): array
    {
        return $this->model::whereDay('name', 'theme')->first()->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function setMfaConfig(bool $enabled, MfaMethod ...$mfaOptions): bool
    {
        $value = json_encode([
            'enabled' => $enabled,
            'steps' => $mfaOptions,
        ]);

        $this->model::updateOrCreate(['name', 'mfa'], ['value' => $value]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getMfaConfig(): array
    {
        return $this->model::where('name', 'mfa')->first()->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function setSettings(array $settings): Collection
    {
        return DB::transaction(function () use ($settings) {

            if (isset($settings['theme'])) {
                $value = ['value' => $settings['theme'], 'created_at' => now(), 'updated_at' => now()];
                AppSettings::updateOrCreate(['name' => 'theme'], $value);
            }

            if (isset($settings['mfa'])) {
                $mfaValue = $this->json_encode_mfa_value($settings['mfa']);
                $value = ['name' => 'mfa', 'value' => $mfaValue, 'created_at' => now(), 'updated_at' => now()];
                AppSettings::updateOrCreate(['name' => 'mfa'], $value);
            }

            return AppSettings::all();
        });
    }

    private function json_encode_mfa_value(array $mfaSettings): string
    {
        $mfaValue = [];

        if (isset($mfaSettings['enabled'])) {
            $mfaValue['enabled'] = $mfaSettings['enabled'];
        }
        if (isset($mfaSettings['steps'])) {
            $mfaValue['steps'] = $mfaSettings['steps'];
        }

        // We set the current if the enabled flag is not given
        $currentMfaConfig = $this->model::where('name', 'mfa')->first();
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

    /**
     * {@inheritDoc}
     */
    public function getSettings(): Collection
    {
        return $this->model::all();
    }
}
