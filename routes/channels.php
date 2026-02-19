<?php

use App\Enums\Role;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Only the timelogger role can subscribe to the timelogs channel.
Broadcast::channel('timelogs', function ($user) {
    return $user->hasRole(Role::TIME_LOGGER->value);
});
