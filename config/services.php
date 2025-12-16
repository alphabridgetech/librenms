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
        'key' => 'AIzaSyD0LCwKAo5--G6h7Lm6M8ZXr8VDHHtslXM',
    // 'key' => 'AIzaSyBI8LgWDpvKsogtG0A1o-J-wcQl4oUQdKI',
    // 'key' =>'AIzaSyDF9NA7oPPWEBxxD_lQcmnxrUjY9ZLF8p8',
    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],


];
