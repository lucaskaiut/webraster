<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateways de pagamento ativos (Finance)
    |--------------------------------------------------------------------------
    |
    | Cada chave camelCase deve corresponder a uma classe em
    | App\Modules\Finance\Gateways\{StudlyCase}Gateway.
    |
    | Exemplos:
    |   asaas  → AsaasGateway
    |   stripe → StripeGateway
    |
    | O domínio de cobrança (charge, webhook, liquidação) depende apenas
    | de PaymentGatewayInterface. Novas implementações entram neste array.
    |
    */

    'active' => [
        'asaas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Antecedência da cobrança
    |--------------------------------------------------------------------------
    |
    | Quantos dias antes de next_billing_at a cobrança é gerada.
    |
    */

    'days_before_due' => (int) env('FINANCE_DAYS_BEFORE_DUE', 30),

    /*
    |--------------------------------------------------------------------------
    | Validade da cobrança no gateway
    |--------------------------------------------------------------------------
    |
    | Minutos de validade da cobrança gerada no gateway (PIX/boleto/cartão).
    | O vencimento da fatura é controlado pela plataforma; quando a cobrança
    | gerada expira, uma nova é emitida no próximo pagamento do cliente.
    |
    */

    'gateway_expires_minutes' => (int) env('FINANCE_GATEWAY_EXPIRES_MINUTES', 30),

];
