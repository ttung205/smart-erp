<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | Internal Smart ERP Microservices
    |--------------------------------------------------------------------------
    */
    'identity' => [
        // Không có giá trị mặc định: thiếu JWT_SECRET thì service báo lỗi cấu hình thay vì âm thầm dùng secret công khai
        'jwt_secret' => env('JWT_SECRET'),
        'jwt_ttl' => (int) env('JWT_TTL', 86400),
    ],

    'internal' => [
        'key' => env('INTERNAL_SERVICE_KEY'),
    ],

    'catalog' => [
        'url' => rtrim(env('CATALOG_SERVICE_URL', 'http://127.0.0.1:8002'), '/'),
        'timeout' => (int) env('CATALOG_SERVICE_TIMEOUT', 3),
    ],

];
