<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QR cross-station replay window
    |--------------------------------------------------------------------------
    |
    | When an employee's QR records a punch at station A, scans at a different
    | station B within this many seconds are rejected. Helps detect copied QR
    | codes used at another kiosk. Set 0 to disable.
    |
    */

    'qr_cross_station_window_seconds' => (int) env('QR_CROSS_STATION_WINDOW_SECONDS', 90),

    /*
    |--------------------------------------------------------------------------
    | Punch time windows (schedule-based)
    |--------------------------------------------------------------------------
    |
    | Punches are accepted in order (AM in → AM out → PM in → PM out → overtime)
    | at any time of day. Late/undertime are still computed from the work schedule
    | when punches are saved.
    |
    */

];
