<?php

// config for NetOs/Balena

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | A balenaCloud session token or named API key. Sent as a bearer token on
    | every request. Individual calls may override this at runtime with
    | Balena::withToken().
    |
    */

    'token' => env('BALENA_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | API location
    |--------------------------------------------------------------------------
    |
    | The base URL and the Pine API version. These are separate because only
    | resource endpoints are versioned: /user/v1/whoami and the /supervisor/*
    | proxy live at the host root. Point base_url at your own installation to
    | use openBalena.
    |
    */

    'base_url' => env('BALENA_API_URL', 'https://api.balena-cloud.com'),

    'version' => env('BALENA_API_VERSION', 'v7'),

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    |
    | balena does not publish its rate limits; it returns a Retry-After header
    | instead so clients can adapt at runtime. Retries honour that header and
    | apply only to 429 and 5xx responses and connection failures.
    |
    */

    'timeout' => (int) env('BALENA_TIMEOUT', 15),

    'retry' => [
        'times' => (int) env('BALENA_RETRY_TIMES', 3),
    ],

];
