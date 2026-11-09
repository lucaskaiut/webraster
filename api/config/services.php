<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'build' => [
        'token' => env('BUILD_TOKEN'),
    ],

    /*
    | Vercel: cadastro dos subdomínios dos tenants no projeto do painel web.
    | O token precisa de acesso ao time (team_id) dono do projeto.
    */
    'vercel' => [
        'token' => env('VERCEL_API_TOKEN'),
        'team_id' => env('VERCEL_TEAM_ID'),
        'project' => env('VERCEL_PROJECT', 'webraster'),
        'base_url' => env('VERCEL_API_URL', 'https://api.vercel.com'),
        'timeout' => (int) env('VERCEL_TIMEOUT', 15),
    ],

    /*
    | Cloudflare: apontamentos DNS dos subdomínios dos tenants.
    | O token precisa de Zone:DNS:Edit na zona. Sem CLOUDFLARE_ZONE_ID, a zona
    | é localizada pelo nome do domínio base (exige também Zone:Zone:Read).
    */
    'cloudflare' => [
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'base_url' => env('CLOUDFLARE_API_URL', 'https://api.cloudflare.com/client/v4'),
        'timeout' => (int) env('CLOUDFLARE_TIMEOUT', 15),
    ],

];
