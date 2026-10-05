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
    // Jenkins test runner page (/jenkins). Use an API token, not the password.
    // From inside the docker container the host's Jenkins is http://172.17.0.1:8080
    'jenkins' => [
        'url' => env('JENKINS_URL', 'http://172.17.0.1:8080'),
        'user' => env('JENKINS_USER'),
        'token' => env('JENKINS_TOKEN'),
    ],


];
