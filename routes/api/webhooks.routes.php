<?php

use App\Enums\WebhookPermission;
use Symfony\Component\HttpFoundation\Response;

Route::group(['as' => 'webhooks.test.'], function () {
    /**
     * This is just a test route for the webhooks.
     * You may create a WebhookController or use existing controllers such as RoleController, AppSettingController, etc.
     *
     * @Note Since the ApiKey model uses Spatie's `HasPermissions` Trait, and the
     * ApiKeyGuard setting the user() to return an ApiKey model, we can use Spatie's default
     * `permissions` middleware to check the permissions of the API Key without creating a custom middleware
     */
    Route::middleware(['auth:api_key', 'api_key_permission:'.WebhookPermission::CREATE_TEST_RESOURCES->value])
        ->post('/test-resources', function () {
            return response()
                ->json(['success' => true, 'message' => 'Test Resource Created'], Response::HTTP_CREATED);
        });

    Route::middleware(['auth:api_key', 'api_key_permission:'.WebhookPermission::VIEW_TEST_RESOURCES->value])
        ->get('/test-resources', function () {
            return response()->json(
                ['success' => true, 'data' => ['name' => 'Test 1', 'description' => 'Test Description']],
                Response::HTTP_OK
            );
        });
});
