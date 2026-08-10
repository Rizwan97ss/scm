<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('FRONTEND_URLS', 'http://localhost:5173'))),

    /*
    | Subdomain-per-tenant means the frontend's origin varies per school
    | (riverside-demo.localtest.me, acme-academy.localtest.me, ...) — an
    | exact-match list can't express that, so this pattern matches the
    | central domain and any of its subdomains on the frontend dev port.
    | TENANCY_FRONTEND_DOMAIN_PATTERN lets this be overridden per-environment
    | without editing code (e.g. a real wildcard prod domain later).
    */
    'allowed_origins_patterns' => array_filter([
        env('TENANCY_FRONTEND_DOMAIN_PATTERN'),
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
