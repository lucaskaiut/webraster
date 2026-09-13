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

];
