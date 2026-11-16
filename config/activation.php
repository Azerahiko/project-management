<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation Expiry
    |--------------------------------------------------------------------------
    |
    | The number of hours an invitation activation link remains valid after
    | it has been issued. After this window the link is rejected and an
    | administrator must resend the invitation.
    |
    */

    'expiry_hours' => env('ACTIVATION_EXPIRY_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Activation Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum activation attempts per IP + email combination within the
    | lockout window. Uses Laravel's shared cache backend.
    |
    */

    'max_attempts' => env('ACTIVATION_MAX_ATTEMPTS', 5),

    'lockout_minutes' => env('ACTIVATION_LOCKOUT_MINUTES', 15),

];
