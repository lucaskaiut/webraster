<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso horário operacional
    |--------------------------------------------------------------------------
    |
    | A jornada dos técnicos e as sugestões de horário são calculadas neste
    | fuso; as datas continuam gravadas em UTC.
    |
    */

    'timezone' => env('SCHEDULING_TIMEZONE', 'America/Sao_Paulo'),

    'default_duration_minutes' => (int) env('SCHEDULING_DEFAULT_DURATION_MINUTES', 60),

    // Grade operacional: duração/deslocamento arredondam para cima e os
    // horários sugeridos andam neste intervalo.
    'slot_step_minutes' => (int) env('SCHEDULING_SLOT_STEP_MINUTES', 15),

    'reminder_minutes' => (int) env('SCHEDULING_REMINDER_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Google Maps Platform
    |--------------------------------------------------------------------------
    |
    | A chave de servidor é usada para Autocomplete (Places API) e para o
    | cálculo de rotas (Routes API). Quando ela não estiver configurada, o
    | sistema continua usando Nominatim e OSRM como alternativas.
    |
    */

    'google' => [
        'api_key' => env('GOOGLE_MAPS_SERVER_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'places_url' => env('GOOGLE_PLACES_URL', 'https://places.googleapis.com'),
        'routes_url' => env('GOOGLE_ROUTES_URL', 'https://routes.googleapis.com'),
        'language' => env('GOOGLE_MAPS_LANGUAGE', 'pt-BR'),
        'timeout' => (int) env('GOOGLE_MAPS_TIMEOUT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocodificação (Google Places / Nominatim)
    |--------------------------------------------------------------------------
    |
    | O autocomplete usa a Places API quando houver chave do Google. Sem a
    | chave, mantém o Nominatim/OpenStreetMap como fallback. O servidor
    | público do Nominatim exige User-Agent identificável e no máximo 1 req/s.
    |
    */

    'geocoding' => [
        'enabled' => (bool) env('GEOCODING_ENABLED', true),
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'NuvemaxTracker/1.0 (contato@nuvemax.com.br)'),
        'country_codes' => env('NOMINATIM_COUNTRY_CODES', 'br'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 8),
        'cache_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotas (Google Routes / OSRM)
    |--------------------------------------------------------------------------
    |
    | As rotas do Google são solicitadas com TRAFFIC_UNAWARE: o tempo ignora
    | completamente as condições de trânsito e usa apenas a malha viária.
    | Se o Google falhar, o OSRM é usado; em último caso, a estimativa usa a
    | distância em linha reta × fator de correção ÷ velocidade média.
    |
    */

    'routing' => [
        'enabled' => (bool) env('ROUTING_ENABLED', true),
        'provider' => env('ROUTING_PROVIDER', 'google'),
        'url' => env('OSRM_URL', 'https://router.project-osrm.org'),
        'profile' => env('OSRM_PROFILE', 'driving'),
        'timeout' => (int) env('OSRM_TIMEOUT', 8),
        'cache_hours' => 24,
        'fallback_speed_kmh' => (float) env('ROUTING_FALLBACK_SPEED_KMH', 35),
        'fallback_road_factor' => (float) env('ROUTING_FALLBACK_ROAD_FACTOR', 1.3),
    ],

];
