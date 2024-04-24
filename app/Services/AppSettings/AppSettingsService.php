<?php

namespace App\Services\AppSettings;

use App\Enums\MfaOption;
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
    public function setTheme(string $theme): bool
    {
        $this->model::updateOrCreate(['name' => 'theme'], ['value' => $theme]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTheme(): string
    {
        return $this->model::where('name', 'theme')->first()->value;
    }

    /**
     * {@inheritDoc}
     */
    public function setMfaConfig(bool $enabled, MfaOption ...$mfaOptions): bool
    {
        $stepsInArrayVal = array_map(fn (MfaOption $option) => $option->value, $mfaOptions);
        $stepsUnique = array_unique($stepsInArrayVal);

        $value = json_encode([
            'enabled' => $enabled,
            'steps' => $stepsUnique,
        ]);

        $this->model::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getMfaConfig(): array
    {
        $value = $this->model::where('name', 'mfa')->first()->value;

        return json_decode($value, true);
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
     * {@inheritDoc}
     */
    public function getSettings(): Collection
    {
        return $this->model::all();
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
}
