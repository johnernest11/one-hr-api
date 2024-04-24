<?php

namespace App\Services\AppSettings;

use App\Enums\MfaOption;
use Illuminate\Database\Eloquent\Collection;

interface AppSettingsManager
{
    /**
     * Set the theme of the application
     */
    public function setTheme(string $theme): bool;

    /**
     * Get the theme configurations
     */
    public function getTheme(): string;

    /**
     * Set MFA Configurations
     */
    public function setMfaConfig(bool $enabled, MfaOption ...$mfaOptions): bool;

    /**
     * Get the MFA configurations
     */
    public function getMfaConfig(): array;

    /**
     * Set all the settings configuration
     */
    public function setSettings(array $settings): Collection|array;

    /**
     * Get all the settings configuration
     */
    public function getSettings(): Collection|array;
}
