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

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        preg_split('/\s*,\s*/', (string) env(
            'CORS_ALLOWED_ORIGINS',
            env('FRONTEND_URL', 'http://localhost'),
        )) ?: [],
    ))),

    // Regex extras separados por vírgula (CORS_ALLOWED_ORIGIN_PATTERNS). Com
    // TENANT_DOMAINS_ENABLED, os subdomínios dos tenants ({x}.TENANT_DOMAINS_BASE)
    // são liberados automaticamente.
    'allowed_origins_patterns' => array_values(array_filter([
        ...array_map(trim(...), explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))),
        env('TENANT_DOMAINS_ENABLED', false)
            ? '#^https://[a-z0-9-]+\\.'.preg_quote(trim((string) env('TENANT_DOMAINS_BASE', 'nuvemax.srv.br'), '. '), '#').'$#'
            : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
