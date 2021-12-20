<?php

return [
    /* -----------------------------
    *  Define the base header values of your openapi spec file
    *  -----------------------------
    *   These are the base parameters that provide context to the api.
    */

    'openapi' => '3.0.0',

    'info' => [
        'title' => 'My test api',
        'version' => '1.0',
    ],

    'servers' => [
        [
            'url' => 'http://{tenant:demo}.test.test/api/v1',
            'description' => 'My dev server',
        ],
    ],

    //'output' => storage_path('text.txt'),

/* -----------------------------
    *  Define your allowed routes
    *  -----------------------------
    *   This defines the prefixes that will be required to index the routes from your application.
    *   You can index the tag related to the prefix with /{$tag}
    */

    'allowed' => [
        'api/v1/{$tag}',
    ],

    /* -----------------------------
    *  Define your auth middlewares
    *  -----------------------------
    *   When defined this will apply the security scheme to each route where the middleware or middlewaregroup is encountered.
    *   Supported types: basic, bearer, apiKey:<header|name>, apiKey:<request> ,openId:<url-here>
    */

    'middleware' => [
        // 'api' => 'openId:https://myapi.example.com/open-idconfig;optional_name'
    ],

    /* -----------------------------
    *  Default response types per verb
    *  -----------------------------
    *
    *   Default responses are overwritten & appended by reponses found by the generator.
    */

    'default_responses' => [
        'get' => [
            '200' => [
                'description' => 'OK',
            ],
        ],
        'post' => [
            '200' => [
                'description' => 'OK',
            ],
            '202' => [
                'description' => 'Action is will be executed.',
            ],
        ],
        'put' => [
            '200' => [
                'description' => 'OK',
            ],
            '202' => [
                'description' => 'Action is will be executed.',
            ],
        ],
        'patch' => [
            '200' => [
                'description' => 'OK',
            ],
            '202' => [
                'description' => 'Action is will be executed.',
            ],
        ],
        'delete' => [
            '204' => [
                'description' => 'Resource deleted.',
            ],
        ],
        '*' => [
            '400' => [
                'description' => 'Bad request.',
            ],
            '401' => [
                'description' => 'Unauthorized.',
            ],
            '403' => [
                'description' => 'Forbidden.',
            ],
            '404' => [
                'description' => 'Route/Resource not found.',
            ],
        ],
    ],
];
