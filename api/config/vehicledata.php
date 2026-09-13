<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consulta de dados veiculares por placa
    |--------------------------------------------------------------------------
    |
    | O domínio depende apenas de PlateLookupProvider. Para trocar de provedor,
    | crie uma nova classe em App\Modules\VehicleData\Gateways\{StudlyCase}Provider
    | e adicione a chave em "providers". As credenciais (token) são salvas por
    | tenant; a URL base de cada provedor fica fixa no código.
    |
    */

    'default' => env('VEHICLE_DATA_PROVIDER', 'apiPlacas'),

    'providers' => [
        'apiPlacas',
    ],

];
