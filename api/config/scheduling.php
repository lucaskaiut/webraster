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
    | Geocodificação (Nominatim / OpenStreetMap)
    |--------------------------------------------------------------------------
    |
    | O servidor público exige User-Agent identificável e no máximo 1 req/s;
    | os resultados ficam em cache e nas coordenadas gravadas nos registros.
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
    | Rotas (OSRM)
    |--------------------------------------------------------------------------
    |
    | Sem trânsito em tempo real. Se o OSRM falhar, a estimativa usa a
    | distância em linha reta × fator de correção ÷ velocidade média.
    |
    */

    'routing' => [
        'enabled' => (bool) env('ROUTING_ENABLED', true),
        'url' => env('OSRM_URL', 'https://router.project-osrm.org'),
        'profile' => env('OSRM_PROFILE', 'driving'),
        'timeout' => (int) env('OSRM_TIMEOUT', 8),
        'cache_hours' => 24,
        'fallback_speed_kmh' => (float) env('ROUTING_FALLBACK_SPEED_KMH', 35),
        'fallback_road_factor' => (float) env('ROUTING_FALLBACK_ROAD_FACTOR', 1.3),
    ],

];
