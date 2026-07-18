<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    */

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | 'verify' => false : IMPORTANT.
    |
    | Une partie des comptes ont un hash bcrypt au format $2b$ (issu du
    | provisioning externe / synchronisation Keycloak). PHP les vérifie
    | parfaitement avec password_verify(), MAIS password_get_info() ne reconnaît
    | pas $2b$ comme « bcrypt » (il renvoie 'unknown'). Or, quand verify=true,
    | Laravel fait ce contrôle AVANT de vérifier et lève :
    |   RuntimeException: "This password does not use the Bcrypt algorithm."
    | -> ce qui faisait planter (500) la règle `current_password` (changement de
    |    mot de passe partenaire, confirmation de coupure moteur, etc.).
    |
    | En désactivant ce pré-contrôle, on laisse password_verify() faire son
    | travail : il accepte $2b$ comme $2y$ (mêmes hash bcrypt) et refuse tout le
    | reste. Aucune faille : on ne valide que de vrais hash bcrypt.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => false,
        'limit' => env('BCRYPT_LIMIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => false,
        'time_cost' => env('ARGON_TIME_COST', 4),
    ],

];
