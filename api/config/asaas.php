<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asaas API (credenciais globais)
    |--------------------------------------------------------------------------
    |
    | Credenciais compartilhadas por todos os métodos Asaas (PIX, cartão, etc.).
    | Sandbox: https://api-sandbox.asaas.com/v3
    | Produção: https://api.asaas.com/v3
    |
    */

    'api_key' => env('ASAAS_API_KEY'),

    'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com/v3'),

    'user_agent' => env('ASAAS_USER_AGENT', 'NoxConnect/1.0'),

    /*
    | Timeout padrão (segundos). Cobranças com cartão usam no mínimo 60s.
    */
    'timeout' => (int) env('ASAAS_TIMEOUT', 30),

    'credit_card_timeout' => (int) env('ASAAS_CREDIT_CARD_TIMEOUT', 60),

];
