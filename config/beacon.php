<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | When false, no listeners are registered and nothing is recorded.
    |
    */

    'enabled' => env('BEACON_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Database connection used to read and write traffic logs.
    | Set to null to use the default application connection.
    |
    */

    'storage' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | Fraction of requests to record. 1.0 = 100%, 0.1 = 10%, 0 = disabled.
    | Applied independently per incoming request and per outgoing call.
    |
    */

    'sampling_rate' => (float) env('BEACON_SAMPLING_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Header & Parameter Redaction
    |--------------------------------------------------------------------------
    |
    | Header names (case-insensitive) and parameter keys (dot notation)
    | whose values are masked before being persisted. Applied to both
    | directions and to both request and response sides.
    |
    */

    'hidden_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-csrf-token',
    ],

    'hidden_parameters' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        '_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Incoming HTTP
    |--------------------------------------------------------------------------
    |
    | body_size_limit_kb: max payload/response size before "Truncated".
    |   Set to null or 0 to disable truncation.
    | only_paths:  if non-empty, only paths matching one of these patterns
    |              are recorded. Empty = no restriction. (Str::is)
    | ignore_paths: glob patterns matched against $request->path() (Str::is).
    |              Applied after only_paths.
    | ignore_methods: HTTP methods to skip (case-insensitive).
    | ignore_status_codes: response status codes to skip.
    |
    */

    'incoming' => [
        'enabled' => true,
        'body_size_limit_kb' => 64,
        'only_paths' => [
            // 'api/*',
        ],
        'ignore_paths' => [
            'beacon*',
            'horizon*',
            'telescope*',
            '_ignition*',
        ],
        'ignore_methods' => [],
        'ignore_status_codes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing HTTP
    |--------------------------------------------------------------------------
    |
    | ignore_hosts: glob patterns matched against the request URI host.
    |
    */

    'outgoing' => [
        'enabled' => true,
        'body_size_limit_kb' => 64,
        'ignore_hosts' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Request Collectors
    |--------------------------------------------------------------------------
    |
    | What to attach to each incoming request log entry.
    | model_actions controls which Eloquent events are recorded.
    |
    */

    'collect' => [
        'queries' => true,
        'models' => true,
        'jobs' => true,
        'memory' => true,
        'model_actions' => ['created', 'updated', 'deleted', 'restored', 'retrieved'],
        'max_queries_per_request' => null, // null or 0 = unlimited
    ],

];
