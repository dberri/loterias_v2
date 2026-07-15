<?php

return [
    'auto_publish' => env('CONTENT_AUTO_PUBLISH', false),

    'default' => env('CONTENT_DEFAULT', 'openai'),

    'drivers' => [
        'openai' => [
            'api_key' => config('openai.api_key'),
            'organization' => config('openai.organization'),
            'request_timeout' => config('openai.request_timeout'),
            'model' => env('CONTENT_OPENAI_MODEL', 'gpt-4o-mini'),
        ],
    ],
];
