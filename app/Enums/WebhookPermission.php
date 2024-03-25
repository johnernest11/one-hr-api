<?php

namespace App\Enums;

enum WebhookPermission: string
{
    case CREATE_TEST_RESOURCES = 'webhook_create_test_resources';
    case VIEW_TEST_RESOURCES = 'webhook_view_test_resources';
}
