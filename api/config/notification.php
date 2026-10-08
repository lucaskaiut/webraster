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

        /*
        | Catálogo de sons de notificação que o usuário escolhe por tipo de
        | alerta (alert_sound_preferences). As chaves precisam espelhar o
        | catálogo do app (src/constants/notification-sounds.ts):
        |
        | - file: nome do arquivo embarcado no iOS (payload `sound`).
        | - channel: canal Android criado pelo app (payload `channelId`).
        |   O canal precisa existir no aparelho antes do push, senão a
        |   notificação não é exibida.
        */
        'sounds' => [
            'default' => ['file' => 'default', 'channel' => 'default'],
            'chime' => ['file' => 'chime.wav', 'channel' => 'alert_sound_chime'],
            'alert' => ['file' => 'alert.wav', 'channel' => 'alert_sound_alert'],
            'siren' => ['file' => 'siren.wav', 'channel' => 'alert_sound_siren'],
        ],
    ],
];
