<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domínio dinâmico dos tenants
    |--------------------------------------------------------------------------
    |
    | Ao criar uma empresa filha, o painel web passa a responder em
    | {identifier}.{base_domain}: o subdomínio é cadastrado no projeto da
    | Vercel (services.vercel) e o apontamento é criado no Cloudflare
    | (services.cloudflare). Com enabled=false nada é provisionado.
    |
    */
    'enabled' => (bool) env('TENANT_DOMAINS_ENABLED', false),

    'base_domain' => env('TENANT_DOMAINS_BASE', 'nuvemax.srv.br'),

    /*
    | Subdomínios que nunca serão usados por tenants (infraestrutura).
    | Registros já existentes no Cloudflare também são preservados: o
    | provisionamento falha em vez de sobrescrevê-los.
    */
    'reserved' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'TENANT_DOMAINS_RESERVED',
            'www,api,app,admin,mail,smtp,ftp,ns1,ns2,painel,panel,agendamento,monitor,dispositivos,webraster-api,webraster-ws,new-webraster,dash-tracker',
        )),
    ))),

    'queue' => env('TENANT_DOMAINS_QUEUE', 'default'),
];
