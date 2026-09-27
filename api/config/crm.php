<?php

return [
    'ai' => [
        'endpoint' => env('CRM_AI_ENDPOINT', env('ASSISTANT_ENDPOINT', 'https://api.openai.com/v1')),
        'api_key' => env('CRM_AI_API_KEY', env('ASSISTANT_API_KEY')),
        'model' => env('CRM_AI_MODEL', env('ASSISTANT_MODEL', 'gpt-4o-mini')),
        'temperature' => (float) env('CRM_AI_TEMPERATURE', env('ASSISTANT_TEMPERATURE', 0.3)),
        'max_tokens' => env('CRM_AI_MAX_TOKENS') !== null ? (int) env('CRM_AI_MAX_TOKENS') : null,
        'max_tool_iterations' => (int) env('CRM_AI_MAX_TOOL_ITERATIONS', 4),
        'handoff_default_customer_message' => env(
            'CRM_AI_HANDOFF_MESSAGE',
            'Vou encaminhar sua conversa para um atendente humano, que dará continuidade em breve. Obrigado pela paciência!',
        ),
    ],
];
