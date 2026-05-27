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

    'edgetts' => [
        'token' => env('EDGE_TTS_TOKEN'),
    ],

    'cf_worker' => [
        'audio_domain' => 'https://audio.070022.xyz',
        'audio_key' => env('CF_WORKER_AUDIO_KEY'),
        'audio_ttl' => 1800,
    ],

    'qiniu' => [
        'ak' => env('QINIU_AK'),
        'sk' => env('QINIU_SK'),
        'bucket' => env('QINIU_BUCKET'),
        'host' => env('QINIU_HOST'),
    ],
];
