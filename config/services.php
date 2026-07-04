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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],


    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    
    'techsoft_sms' => [
        'token'     => env('TECHSOFT_SMS_API_TOKEN'),
        'sender_id' => env('TECHSOFT_SMS_SENDER_ID', 'PROXYM'),
    ],


    'tracking_webhook' => [
        'token' => env('TRACKING_WEBHOOK_TOKEN'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    'brevo' => [
    'key' => env('BREVO_API_KEY'),
    'sender_email' => env('BREVO_SENDER_EMAIL'),
    'sender_name' => env('BREVO_SENDER_NAME', 'FLEETRA BY PROXYM GROUP'),
    'template_reset_id' => (int) env('BREVO_TEMPLATE_RESET_ID', 2),
],


'partner_lease_api' => [
    'base_url' => env('PARTNER_LEASE_API_BASE_URL'),
    'timeout' => (int) env('PARTNER_LEASE_API_TIMEOUT', 20),
],

'keycloak' => [
    'base_url' => env('KEYCLOAK_BASE_URL'),
    'realm' => env('KEYCLOAK_REALM', 'proxymgroup'),

    'client_id' => env('KEYCLOAK_CLIENT_ID'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),

    'issuer' => env('KEYCLOAK_ISSUER'),
    'jwks_url' => env('KEYCLOAK_JWKS_URL'),
    'token_url' => env('KEYCLOAK_TOKEN_URL'),
    'logout_url' => env('KEYCLOAK_LOGOUT_URL'),

    'admin_client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID', 'admin-cli'),
    'admin_user' => env('KEYCLOAK_ADMIN_USER'),
    'admin_password' => env('KEYCLOAK_ADMIN_PASSWORD'),

    // Realm utilisé pour obtenir le token admin.
    // Souvent master.
    'admin_auth_realm' => env('KEYCLOAK_ADMIN_AUTH_REALM', 'master'),

    // Realm dans lequel on crée les chauffeurs.
    'admin_target_realm' => env('KEYCLOAK_ADMIN_TARGET_REALM', env('KEYCLOAK_REALM', 'proxymgroup')),

    // Client de l’application Tracking.
    'tracking_client_id' => env('KEYCLOAK_ADMIN_TARGET_CLIENT_ID', 'tracking_app'),

    // Client de l’application recouvrement.
    'recouvrement_client_id' => env('KEYCLOAK_RECOUVREMENT_CLIENT_ID', 'recouvrement_app'),

    // Compatibilité ancien code.
    'admin_target_client_id' => env('KEYCLOAK_ADMIN_TARGET_CLIENT_ID', 'tracking_app'),
],



];
