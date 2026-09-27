<?php

return [
    'default_base_url' => env('EVOLUTION_API_BASE_URL', 'https://evolution.lucaskaiut.com.br'),

    'evolution' => [
        'base_url' => env('EVOLUTION_API_BASE_URL', 'https://evolution.lucaskaiut.com.br'),
        'api_key' => env('EVOLUTION_API_KEY'),
    ],

    /** @var list<string> */
    'active_providers' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('CHAT_MESSAGING_PROVIDERS', 'evolution')),
    ))),

    'ai' => [
        'lock_seconds' => (int) env('CRM_AI_LOCK_SECONDS', 120),
        'history_limit' => (int) env('CRM_AI_HISTORY_LIMIT', 30),
    ],

    /** Fila dedicada para webhook/IA — processada antes de `default` no worker. */
    'queue' => env('CHAT_QUEUE', 'chat'),
];
