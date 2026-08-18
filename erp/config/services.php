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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_API_ENDPOINT', 'https://api.brevo.com'),
    ],

    'mram' => [
        'api_key'  => env('MRAM_SMS_API_KEY'),
        'endpoint' => env('MRAM_SMS_ENDPOINT', 'https://msg.mram.com.bd/smsapi'),
        'sender_id' => env('MRAM_SMS_SENDER_ID', '8809601017803'),
    ],

    'sso' => [
        'secret'     => env('SSO_SECRET'),
        'dm_portal'  => env('DM_PORTAL_URL', 'https://dm.epal.com.bd'),
    ],

    'dm' => [
        'url' => env('DM_API_URL'),
        'token' => env('DM_API_TOKEN'),
        'documents_url' => env('DM_DOCUMENTS_URL'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
    ],

];
