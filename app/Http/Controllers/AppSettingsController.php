<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Http\Requests\AppSettingsRequest;
use App\Services\AppSettingsManager;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AppSettingsController extends ApiController
{
    private AppSettingsManager $appSettingsManager;

    public function __construct(AppSettingsManager $appSettingsManager)
    {
        $this->appSettingsManager = $appSettingsManager;
    }

    /**
     * Fetch all the app settings
     */
    public function index(AppSettingsRequest $request): JsonResponse
    {
        $settings = $this->appSettingsManager->getSettings();

        return $this->success(['data' => $settings], Response::HTTP_OK);
    }

    /**
     * Store the app settings (will overwrite the existing)
     *
     * @throws Throwable
     */
    public function store(AppSettingsRequest $request): JsonResponse
    {
        // Don't allow MFA management if `allow_api_management` is set to false
        if ($request->validated('mfa')) {
            $mfaConfig = $this->appSettingsManager->getMfaConfig();
            if (! $mfaConfig['allow_api_management']) {
                return $this->error('MFA configuration is disabled', Response::HTTP_FORBIDDEN, ApiErrorCode::FORBIDDEN);
            }
        }

        $settings = $this->appSettingsManager->setSettings($request->validated());

        return $this->success(['data' => $settings], Response::HTTP_OK);
    }
}
