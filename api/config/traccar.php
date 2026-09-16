<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Traccar GPS Gateway
    |--------------------------------------------------------------------------
    |
    | Fonte de dados de rastreamento. Com enabled=false, o gateway simulado
    | responde sem chamar a API externa (útil em testes e desenvolvimento).
    |
    */
    'enabled' => (bool) env('TRACCAR_ENABLED', false),

    'base_url' => rtrim((string) env('TRACCAR_BASE_URL', 'http://localhost:8082'), '/'),

    'email' => env('TRACCAR_EMAIL'),

    'password' => env('TRACCAR_PASSWORD'),

    /*
    | Token de usuário do Traccar (alternativa a email/senha).
    | Usado em Authorization: Bearer {token}.
    */
    'token' => env('TRACCAR_TOKEN'),

    'timeout' => (int) env('TRACCAR_TIMEOUT', 15),

    /*
    | Token compartilhado para autenticar o webhook de posições do Traccar.
    | O Traccar envia no header X-Traccar-Webhook-Token (via forward.header).
    | Em branco (ex.: desenvolvimento local), a verificação é ignorada.
    */
    'webhook_secret' => env('TRACCAR_WEBHOOK_SECRET'),

    /*
    | Reconciliação periódica: job a cada 5 minutos puxa do Traccar as posições
    | que o webhook não entregou. Desligue para não gerar tráfego extra.
    */
    'sync_enabled' => (bool) env('TRACCAR_SYNC_ENABLED', true),
];
