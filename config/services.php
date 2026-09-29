<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'librenms' => [
    'url' => 'http://localhost:8000/',
    ],
    'chatbot' => [
        'key' => env('CHATBOT_API_KEY'),
        'endpoint' => env('CHATBOT_ENDPOINT'),
        'model' => env('CHATBOT_MODEL'),
    ],


];
