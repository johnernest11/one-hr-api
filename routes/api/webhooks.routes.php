<?php

use App\Enums\Permission;

Route::group(['as' => 'webhooks.test.'], function () {
    /**
     * This is just a test route for the webhooks.
     * You may create a WebhookController or use existing controllers such as RoleController, AppSettingController, etc.
     *
     * @Note Since the ApiKey model uses Spatie's `HasPermissions` Trait, and the
     * ApiKeyGuard setting the user() to return an ApiKey model, we can use Spatie's default
     * `permissions` middleware to check the permissions of the API Key without creating a custom middleware
     */
    Route::middleware(['permissions:'.Permission::WEBHOOK_CREATE_TEST_RESOURCES->value])
        ->post('/tests', function () {
            return response()->json(['success' => true], \Symfony\Component\HttpFoundation\Response::HTTP_CREATED);
        });

    Route::middleware(['permissions:'.Permission::WEBHOOK_CREATE_TEST_RESOURCES->value])
        ->get('/tests', function () {
            return response()->json(
                ['success' => true, 'data' => ['name' => 'Test 1', 'description' => 'Test Description']],
                \Symfony\Component\HttpFoundation\Response::HTTP_OK
            );
        });
});
