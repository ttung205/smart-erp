<?php

return [

    'identity' => [
        // Không có giá trị mặc định: thiếu JWT_SECRET thì service báo lỗi cấu hình thay vì âm thầm dùng secret công khai
        'jwt_secret' => env('JWT_SECRET'),
        'url' => env('IDENTITY_SERVICE_URL', 'http://127.0.0.1:8001'),
    ],

    'internal' => [
        // Gửi kèm header X-Internal-Service-Key khi gọi identity-service (không có token người dùng)
        'key' => env('INTERNAL_SERVICE_KEY'),
    ],

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'erp_rabbit'),
        'password' => env('RABBITMQ_PASSWORD', 'rabbit_secret'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'erp.events'),
    ],

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

];
