<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Push (Expo Push Service)
    |--------------------------------------------------------------------------
    |
    | A API envia as notificações push para o serviço da Expo, que entrega via
    | FCM (Android) e APNs (iOS). O app registra o token via /notifications/devices.
    |
    */
    'push' => [
        'enabled' => (bool) env('PUSH_ENABLED', true),

        'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),

        'receipts_url' => env(
            'EXPO_PUSH_RECEIPTS_URL',
            'https://exp.host/--/api/v2/push/getReceipts',
        ),

        /*
        | Token de acesso do projeto Expo (opcional; recomendado em produção).
        | Enviado como Authorization: Bearer {token}.
        */
        'access_token' => env('EXPO_ACCESS_TOKEN'),

        'timeout' => (int) env('EXPO_PUSH_TIMEOUT', 15),
    ],
];
