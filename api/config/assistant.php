<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Assistente de IA
    |--------------------------------------------------------------------------
    |
    | O motor de chat é genérico e independente de domínio. O comportamento
    | do assistente (system prompt + ferramentas) é definido por um "agent",
    | apontado abaixo — cada projeto cria o seu sem tocar no motor.
    |
    | ASSISTANT_AGENT deve ser a FQCN de uma classe que implemente
    | App\Modules\Assistant\Contracts\AssistantAgent.
    |
    */

    'enabled' => env('ASSISTANT_ENABLED', true),

    'agent' => env(
        'ASSISTANT_AGENT',
        \App\Modules\Assistant\Agents\GenericAssistantAgent::class,
    ),

    'connection' => [
        'endpoint' => env('ASSISTANT_ENDPOINT', 'https://api.openai.com/v1'),
        'api_key' => env('ASSISTANT_API_KEY'),
        'model' => env('ASSISTANT_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('ASSISTANT_TEMPERATURE', 0.2),
        'max_tokens' => env('ASSISTANT_MAX_TOKENS') !== null ? (int) env('ASSISTANT_MAX_TOKENS') : null,
    ],

    'history_limit' => (int) env('ASSISTANT_HISTORY_LIMIT', 40),

    'max_tool_iterations' => (int) env('ASSISTANT_MAX_TOOL_ITERATIONS', 6),
];
