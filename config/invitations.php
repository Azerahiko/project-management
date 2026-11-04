<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation Expiry
    |--------------------------------------------------------------------------
    |
    | The number of hours an invitation token remains valid after it has been
    | issued. After this window the token is rejected and must be reissued.
    |
    */

    'expiry_hours' => env('INVITATION_EXPIRY_HOURS', 72),

];
