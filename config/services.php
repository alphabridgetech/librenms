<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'librenms' => [
    'url' => env('LIBRENMS_URL'),
    'token' => env('LIBRENMS_TOKEN'),
    ],
    'gemini' => [
    'key' => env('GEMINI_API_KEY'),
    'endpoint' => env('GEMINI_ENDPOINT'),
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],


];
