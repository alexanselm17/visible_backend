<?php

return [

    'visible_qr' => [
        'public_url' => env('VISIBLE_QR_PUBLIC_URL', 'https://app.visibledm.com'),
    ],

    'talksasa' => [
        'api_key' => env('TALKSASA_API_KEY'),
        'base_url' => env('TALKSASA_BASE_URL', 'https://bulksms.talksasa.com/api/v3'),
        'send_path' => env('TALKSASA_SEND_PATH', 'sms/send'),
        'sender_id' => env('TALKSASA_SENDER_ID', 'VisibleDM'),
        'timeout' => (int) env('TALKSASA_TIMEOUT', 30),
        'otp_ttl_minutes' => (int) env('TALKSASA_OTP_TTL_MINUTES', 10),
        'otp_resend_cooldown_seconds' => (int) env('TALKSASA_OTP_RESEND_COOLDOWN_SECONDS', 60),
        'otp_max_attempts' => (int) env('TALKSASA_OTP_MAX_ATTEMPTS', 5),
    ],

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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'verification_model' => env('OPENAI_VERIFICATION_MODEL', 'gpt-4o'),
    ],

];
