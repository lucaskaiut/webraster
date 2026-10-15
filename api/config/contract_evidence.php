<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Armazenamento das evidências
    |--------------------------------------------------------------------------
    |
    | Disco privado (não servido publicamente) onde ficam o documento
    | apresentado, o PDF assinado e a imagem da assinatura manuscrita.
    |
    */

    'disk' => env('CONTRACT_EVIDENCE_DISK', 'local'),

    'directory' => 'contract-evidence',

    /*
    |--------------------------------------------------------------------------
    | Validação pública
    |--------------------------------------------------------------------------
    |
    | Base da URL impressa no selo (QR Code) do contrato assinado; o código de
    | verificação é anexado ao final. Padrão: FRONTEND_URL/validar-contrato.
    |
    */

    'verification_url' => env('CONTRACT_VERIFICATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Sessão de assinatura
    |--------------------------------------------------------------------------
    */

    'session_ttl_minutes' => (int) env('CONTRACT_SIGNING_SESSION_TTL', 30),

    // Sessões presas em "completing" além desse prazo são reconciliadas.
    'completing_timeout_minutes' => 5,

    // Mantém o endpoint antigo (POST clients/{client}/contract/signature)
    // para versões do app anteriores à sessão de assinatura.
    'allow_legacy_signing' => (bool) env('CONTRACT_ALLOW_LEGACY_SIGNING', true),

    /*
    |--------------------------------------------------------------------------
    | Consentimento
    |--------------------------------------------------------------------------
    |
    | Ao alterar o texto, incremente a versão: o texto exato e o hash são
    | gravados em cada assinatura.
    |
    */

    'consent' => [
        'version' => '2026-10-14.1',
        'text' => 'Declaro que li integralmente o contrato apresentado nesta tela, que os dados informados são verdadeiros '
            .'e que concordo em assiná-lo eletronicamente. Estou ciente de que a assinatura desenhada é um registro gráfico '
            .'vinculado a esta versão do documento e de que serão registrados data e hora do servidor, endereço IP, '
            .'dispositivo e conta utilizados, como evidências desta assinatura.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Chaves de assinatura do manifesto (Ed25519)
    |--------------------------------------------------------------------------
    |
    | A chave privada NUNCA deve ser versionada. Informe-a por variável de
    | ambiente (base64 do secret key de 64 bytes do libsodium) ou por arquivo
    | montado como secret. Gere com: php artisan contract-evidence:generate-key
    |
    | trusted_public_keys: chaves públicas aceitas na verificação, no formato
    | "key_id:base64,key_id2:base64". Mantenha as chaves antigas após a
    | rotação para que manifestos históricos continuem verificáveis.
    |
    */

    'signing' => [
        'key_id' => env('CONTRACT_EVIDENCE_KEY_ID'),
        'private_key' => env('CONTRACT_EVIDENCE_PRIVATE_KEY'),
        'private_key_path' => env('CONTRACT_EVIDENCE_PRIVATE_KEY_PATH'),
        'trusted_public_keys' => env('CONTRACT_EVIDENCE_TRUSTED_KEYS', ''),
    ],

];
