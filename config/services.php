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
    'gemini' => [
    'key' => 'AIzaSyBI8LgWDpvKsogtG0A1o-J-wcQl4oUQdKI',
    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],


];
