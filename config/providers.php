<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deal Providers
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoints for the sources that supply supermarket offers
    | (Lidl, Aldi, Netto, Kaufland, Edeka, ...). Each provider gets its own
    | key so direct-retailer adapters can be added next to the aggregator.
    |
    */

    /*
    | marktguru's public web-app keys (the same ones its website sends). They
    | are not account-bound; override via env only if marktguru rotates them.
    */
    'marktguru' => [
        'api_key' => env('MARKTGURU_API_KEY', '8Kk+pmbf7TgJ9nVj2cXeA7P5zBGv8iuutVVMRfOfvNE='),
        'client_key' => env('MARKTGURU_CLIENT_KEY', 'WU/RH+PMGDi+gkZer3WbMelt6zcYHSTytNB7VpTia90='),
        'base_url' => env('MARKTGURU_BASE_URL', 'https://api.marktguru.de/api/v1'),
        'web_url' => env('MARKTGURU_WEB_URL', 'https://www.marktguru.de'),
    ],

    /*
    | Aldi Nord's website, read for its weekly offers where marktguru has none
    | (marktguru only carries Aldi Süd).
    */
    'aldi_nord' => [
        'web_url' => env('ALDI_NORD_WEB_URL', 'https://www.aldi-nord.de'),
    ],

];
