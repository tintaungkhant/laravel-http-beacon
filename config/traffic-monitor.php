<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Body Size Limit (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum size of a logged request payload or response body, in kilobytes.
    | Anything larger is replaced with "Truncated" in the log entry.
    | Set to null or 0 to disable truncation and log bodies of any size.
    |
    */

    'incoming' => [
        'body_size_limit_kb' => 64,
    ],

    'outgoing' => [
        'body_size_limit_kb' => 64,
    ],

];
