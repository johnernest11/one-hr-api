<?php

return [
    /*
     |--------------------------------------------------------------------------
     | JWT Configuration keys
     |--------------------------------------------------------------------------
     |
     | Defined here are the configurations for
     | the JWT Authentication
     |
    */
    'signing_key' => env('JWT_SIGNING_KEY'),
    'lifetime_minutes' => env('JWT_LIFETIME_MINUTES', 360),
    'issuer' => env('JWT_ISSUER'),
    'audience' => env('JWT_AUDIENCE'),
    'id' => env('JWT_ID'),
];
