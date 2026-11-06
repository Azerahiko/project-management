<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activation Code Length
    |--------------------------------------------------------------------------
    |
    | The length of the numeric activation code generated for new users.
    | Must be between 6 and 10 digits.
    |
    */

    'code_length' => env('ACTIVATION_CODE_LENGTH', 8),

    /*
    |--------------------------------------------------------------------------
    | Activation Code Expiry
    |--------------------------------------------------------------------------
    |
    | The number of hours an activation code remains valid after it has been
    | issued. After this window the code is rejected and must be regenerated.
    |
    */

    'expiry_hours' => env('ACTIVATION_EXPIRY_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum activation attempts per IP + email combination within the
    | lockout window. Uses Laravel's shared cache backend.
    |
    */

    'max_attempts' => env('ACTIVATION_MAX_ATTEMPTS', 5),

    'lockout_minutes' => env('ACTIVATION_LOCKOUT_MINUTES', 15),

];
