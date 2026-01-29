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
   
    'key'=> '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',
    'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    'model' => 'arcee-ai/trinity-large-preview:free',
    ],


];
