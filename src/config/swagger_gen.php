<?php

return [
    'allowed' => [
        'api/v1'
    ],
    'openapi' => "3.0.0",

    'info' => [
        'title' => "My test api",
        'version' => "1.0",
    ],

    'servers' => [
        [
            'url' => 'http://dev.test.test/api/v1',
            'description' => 'My dev server'
        ],
    ],

    'output' => [
        'path' => storage_path('text.txt')
    ],

    'default_responses' => [
        'get' => [
            '200' => [
                'description' => 'OK'
            ]
        ],
        'post' => [
            '200' => [
                'description' => 'OK'
            ],    
            '202' => [
            'description' => 'Action is will be executed.'
            ],
        ],
        'put' => [
            '200' => [
                'description' => 'OK'
            ],    
            '202' => [
            'description' => 'Action is will be executed.'
            ],
        ],
        'patch' => [
            '200' => [
                'description' => 'OK'
            ],    
            '202' => [
            'description' => 'Action is will be executed.'
            ],
        ],
        'delete' => [
            '204' => [
                'description' => 'Resource deleted.'
            ],
        ],
        '*' => [
            '400' => [
                'description' => 'Bad request.'
            ],
            '401' => [
                'description' => 'Unauthorized.'
            ],
            '403' => [
                'description' => 'Forbidden.'
            ],
            '404' => [
                'description' => 'Route/Resource not found.'
            ],
        ]
    ],
];