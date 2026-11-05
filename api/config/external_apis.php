<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registro de chamadas a APIs externas
    |--------------------------------------------------------------------------
    |
    | Toda requisição feita pelo client HTTP do Laravel (Http::...) cujo
    | destino esteja no catálogo abaixo é gravada em external_api_requests,
    | com request e response, para o painel de métricas de APIs externas.
    | Destinos fora do catálogo são ignorados.
    |
    */

    'logging' => [
        'enabled' => (bool) env('EXTERNAL_API_LOGGING_ENABLED', true),

        // Corpos de request/response acima deste tamanho são truncados.
        'max_body_bytes' => (int) env('EXTERNAL_API_LOGGING_MAX_BODY_BYTES', 65536),

        // Registros mais antigos que isso são removidos pelo model:prune.
        'retention_days' => (int) env('EXTERNAL_API_LOGGING_RETENTION_DAYS', 90),
    ],

    // Fuso usado para agrupar o gráfico quando o painel não informa um.
    'display_timezone' => env('EXTERNAL_API_DISPLAY_TIMEZONE', 'America/Sao_Paulo'),

    // Cabeçalhos e parâmetros de query mascarados antes de gravar.
    'redact' => [
        'headers' => ['x-goog-api-key', 'authorization', 'x-api-key', 'access_token', 'cookie', 'set-cookie', 'proxy-authorization'],
        'query' => ['key', 'api_key', 'apikey', 'token', 'access_token'],
    ],

    'providers' => [
        'google' => 'Google',
    ],

    /*
    | Catálogo de serviços. A primeira entrada cujo host (da URL base) e
    | caminho casarem com a requisição define o serviço. "base_url_config"
    | aponta para a chave de config com a URL base (assim a URL configurada
    | por ambiente é respeitada); "base_url" é usado quando não houver.
    */
    'services' => [
        'google.routes' => [
            'provider' => 'google',
            'label' => 'Routes API',
            'base_url_config' => 'scheduling.google.routes_url',
            'base_url' => 'https://routes.googleapis.com',
            'path' => null,
        ],
        'google.places.autocomplete' => [
            'provider' => 'google',
            'label' => 'Places API · Autocomplete',
            'base_url_config' => 'scheduling.google.places_url',
            'base_url' => 'https://places.googleapis.com',
            'path' => '#^/v1/places:autocomplete#',
        ],
        'google.places.details' => [
            'provider' => 'google',
            'label' => 'Places API · Place Details',
            'base_url_config' => 'scheduling.google.places_url',
            'base_url' => 'https://places.googleapis.com',
            'path' => '#^/v1/places/[^/:]+$#',
        ],
        'google.places' => [
            'provider' => 'google',
            'label' => 'Places API · Outros',
            'base_url_config' => 'scheduling.google.places_url',
            'base_url' => 'https://places.googleapis.com',
            'path' => null,
        ],
        'google.geocoding' => [
            'provider' => 'google',
            'label' => 'Geocoding API',
            'base_url' => 'https://maps.googleapis.com',
            'path' => '#^/maps/api/geocode/#',
        ],
        'google.maps' => [
            'provider' => 'google',
            'label' => 'Maps Platform · Outros',
            'base_url' => 'https://maps.googleapis.com',
            'path' => null,
        ],
    ],

];
