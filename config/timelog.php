<?php

return [
    /*
     |--------------------------------------------------------------------------
     | All configurations related to Time Logs
     |--------------------------------------------------------------------------
     | This contains all configurations related to the time logs and related modules.
     |
     */
    'duplicate_scan_limit' => env('DUPLICATE_SCAN_LIMIT_MINUTES', 15),
    'locator_duplicate_scan_limit' => env('LOCATOR_DUPLICATE_SCAN_LIMIT_MINUTES', 3),
];
