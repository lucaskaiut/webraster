<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateways de pagamento ativos
    |--------------------------------------------------------------------------
    |
    | Cada chave camelCase deve corresponder a uma classe em
    | App\Modules\Billing\Gateways\{StudlyCase}Gateway.
    |
    | Exemplos:
    |   mockPix          → MockPixGateway
    |   asaasPix         → AsaasPixGateway
    |   asaasCreditCard  → AsaasCreditCardGateway
    |
    | Apenas gateways listados aqui ficam disponíveis para o usuário
    | ao assinar um plano. Novas implementações precisam ser registradas
    | neste array para entrarem em operação.
    |
    */

    'active' => [
        // 'mockPix',
        'asaasPix',
        'asaasCreditCard', // requer ASAAS_API_KEY em .env
    ],

    /*
    |--------------------------------------------------------------------------
    | Antecedência da cobrança
    |--------------------------------------------------------------------------
    |
    | Quantos dias antes de next_billing_at a fatura local é gerada.
    | O usuário entra na plataforma, escolhe o método e paga.
    |
    */

    'days_before_due' => (int) env('BILLING_DAYS_BEFORE_DUE', 3),

    /*
    |--------------------------------------------------------------------------
    | Janela de pagamento (fatura imediata)
    |--------------------------------------------------------------------------
    |
    | No cadastro sem trial a fatura nasce com due_date = agora.
    | expires_at = agora + payment_window_days. Após isso, a fatura
    | local vence e a assinatura vai para PAST_DUE (mesmo fluxo de inadimplência).
    |
    */

    'payment_window_days' => (int) env('BILLING_PAYMENT_WINDOW_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Cobrança recorrente (cron)
    |--------------------------------------------------------------------------
    |
    | Quando a assinatura é recorrente e possui credit_card_token, o comando
    | billing:generate-invoices tenta cobrar automaticamente via gateway.
    | Após esgotar as tentativas, gera a fatura local normal (aguarda método).
    |
    */

    'recurring_charge_max_attempts' => (int) env('BILLING_RECURRING_CHARGE_MAX_ATTEMPTS', 3),

    'recurring_charge_retry_delay_ms' => (int) env('BILLING_RECURRING_CHARGE_RETRY_DELAY_MS', 500),

    'recurring_remote_ip' => env('BILLING_RECURRING_REMOTE_IP', '127.0.0.1'),

];
