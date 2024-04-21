<?php

namespace App\Services\AppSettings;

use App\Enums\MfaMethod;
use Illuminate\Database\Eloquent\Collection;

interface AppSettingsManager
{
    /**
     * Set the theme of the application
     */
    public function setThemeConfig(string $theme): bool;

    /**
     * Get the theme configurations
     */
    public function getThemeConfig(): Collection|array;

    /**
     * Set MFA Configurations
     */
    public function setMfaConfig(bool $enabled, MfaMethod ...$mfaOptions): bool;

    /**
     * Get the MFA configurations
     */
    public function getMfaConfig(): Collection|array;

    /**
     * Set all the settings configuration
     */
    public function setSettings(array $settings): Collection|array;

    /**
     * Get all the settings configuration
     */
    public function getSettings(): Collection|array;
}
