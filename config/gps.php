<?php

/*
|--------------------------------------------------------------------------
| Configuration GPS (18gps)
|--------------------------------------------------------------------------
|
| IMPORTANT — à lire avant toute modification :
|
| Ces valeurs DOIVENT être lues via config('gps.…') dans le code applicatif,
| JAMAIS via env('GPS_…') direct.
|
| Pourquoi : sous `php artisan config:cache`, Laravel ne charge plus le fichier
| .env, donc tout appel à env() en dehors des fichiers config/ renvoie null.
| C'est ce qui vidait le mot de passe de commande des boîtiers (pwd = '') :
| le provider 18gps répondait alors « PWD_ERROR » avec un CmdNo vide, et AUCUNE
| commande de coupure n'atteignait jamais le boîtier — alors que la commande et
| le mot de passe étaient corrects.
|
| Seuls les fichiers de ce dossier config/ peuvent appeler env() sans risque.
|
*/

return [

    // Endpoints provider (identiques pour les deux comptes).
    'api_url'   => env('GPS_API_URL', 'http://apitest.18gps.net/GetDateServices.asmx'),
    'login_url' => env('GPS_LOGIN_URL', 'http://appzzl.18gps.net/'),

    // Paramètres loginSystem.
    'login_type' => env('GPS_LOGIN_TYPE', 'ENTERPRISE'),
    'language'   => env('GPS_LANGUAGE', 'en'),
    'timezone'   => env('GPS_TIMEZONE', '8'),
    'apply'      => env('GPS_APPLY', 'APP'),
    'is_md5'     => (int) env('GPS_IS_MD5', 0),

    'token_ttl'    => (int) env('GPS_TOKEN_TTL', 1140),
    'http_timeout' => (int) env('GPS_HTTP_TIMEOUT', 20),

    // Comptes / résolution.
    'default_account'     => env('GPS_DEFAULT_ACCOUNT', 'tracking'),
    'account_resolve_ttl' => (int) env('GPS_ACCOUNT_RESOLVE_TTL', 300),

    // Seuils métier.
    'offline_threshold_minutes' => (int) env('GPS_OFFLINE_THRESHOLD_MINUTES', 25),
    'moving_threshold'          => (float) env('GPS_MOVING_THRESHOLD', 5.0),

    // Duree de vie du releve live de la flotte. Voir refreshLiveFleetMap().
    'live_fleet_ttl' => (int) env('GPS_LIVE_FLEET_TTL', 900),

    /*
     | Limites géographiques d'exploitation (Cameroun).
     |
     | Le fournisseur renvoie parfois des coordonnées aberrantes : un boîtier
     | localisé au Tchad alors qu'il est à Yaoundé, un autre à Shenzhen — la
     | position d'usine du fabricant. Les afficher telles quelles trompe
     | l'utilisateur bien plus qu'une position simplement datée.
     |
     | Mêmes valeurs que celles utilisées par l'ingestion Node, qui rejette déjà
     | ces points avec la raison « outside_operational_bounds ».
     */
    'bounds' => [
        'lat_min' => (float) env('GPS_BOUND_LAT_MIN', 1.5),
        'lat_max' => (float) env('GPS_BOUND_LAT_MAX', 13.5),
        'lon_min' => (float) env('GPS_BOUND_LNG_MIN', 8.0),
        'lon_max' => (float) env('GPS_BOUND_LNG_MAX', 16.5),
    ],

    // Cartographie.
    'map_type'   => env('GPS_MAP_TYPE', 'BAIDU'),
    'map_option' => env('GPS_MAP_OPTION', 'cn'),

    /*
     | Décodage du statut boîtier (bits).
     | relais = false  -> moteur CUT (relais ouvert)
     | relais = true   -> ON (contact allumé) / OFF (contact coupé)
     */
    'status' => [
        'acc_index'    => (int) env('GPS_STATUS_ACC_INDEX', 0),
        'relay_index'  => (int) env('GPS_STATUS_RELAY_INDEX', 2),
        'relay_invert' => (bool) env('GPS_STATUS_RELAY_INVERT', false),
        'bit_order'    => env('GPS_STATUS_BIT_ORDER', 'MSB'),
    ],

    // Repli global si un compte n'a pas ses propres identifiants.
    'login_name'          => env('GPS_LOGIN_NAME', ''),
    'login_password'      => env('GPS_LOGIN_PASSWORD', ''),
    'device_cmd_password' => env('GPS_DEVICE_CMD_PASSWORD'),

    /*
     | Identifiants par compte.
     | device_cmd_password = mot de passe de COMMANDE du boîtier (paramètre `pwd`
     | de SendCommands). S'il est vide, le provider renvoie PWD_ERROR.
     */
    'accounts' => [
        'tracking' => [
            'login_name'          => env('GPS_TRACKING_LOGIN_NAME', ''),
            'login_password'      => env('GPS_TRACKING_LOGIN_PASSWORD', ''),
            'device_cmd_password' => env('GPS_TRACKING_DEVICE_CMD_PASSWORD'),
        ],
        'mobility' => [
            'login_name'          => env('GPS_MOBILITY_LOGIN_NAME', ''),
            'login_password'      => env('GPS_MOBILITY_LOGIN_PASSWORD', ''),
            'device_cmd_password' => env('GPS_MOBILITY_DEVICE_CMD_PASSWORD'),
        ],
    ],

];
