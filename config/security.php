<?php

return [

    /*
    | When true and APP_ENV is production, the app refuses to boot with APP_DEBUG=true.
    */

    'block_debug_in_production' => filter_var(
        env('SECURITY_BLOCK_DEBUG_IN_PRODUCTION', false),
        FILTER_VALIDATE_BOOL
    ),

];
