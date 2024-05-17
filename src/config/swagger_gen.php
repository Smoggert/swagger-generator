<?php

return [
    /* -----------------------------
    *  Define the base header values of your openapi spec file
    *  -----------------------------
    *   These are the base parameters that provide context to the api.
    */

    'apis' => [
        'default' => [
            'openapi' => '3.0.0',

            'info' => [
                'title' => 'My test api',
                'version' => '1.0',
            ],

            'servers' => [
                [
                    'url' => 'https://{subdomain:test}.test.test/api/v1',
                    'description' => 'My dev server',
                ],
            ],

            'parsers' => [
                \Smoggert\SwaggerGenerator\Parsers\QueryLaravelAttributeParser::class,
            ],
            //'output' => storage_path('text.txt'),

            /* -----------------------------
            *  Define your allowed routes
            *  -----------------------------
            *   This defines the prefixes that will be required to index the routes from your application.
            *   You can index the tag related to the prefix with /{$tag}, skip resource related parameters with {id}
            */

            'allowed' => [
                'api/v1/{$tag}',
            ],

            /* -----------------------------
            *  Define your excluded routes
            *  -----------------------------
            *   This defines the exact routes that won't be included.
            *   Indicate resource related parameters with {id}
            */

            'exclude' => [
                // 'api/v1/resources/{id}'
            ],

            /* -----------------------------
            *  Define your auth middlewares
            *  -----------------------------
            *   When defined this will apply the security scheme to each route where the middleware or middleware-group is encountered.
            *   Possible schemes: https://swagger.io/docs/specification/authentication/
            *
            *   Syntax is a bit double with the alias & class reference, but this is due to how Laravel's Console kernel messes with Middleware.
            */

            'middleware' => [
                /*        'auth:api' => [
                    'class' => \App\Http\Middleware\Authenticate::class . ":api",
                    'schema' => [
                        'name' => 'MyOauthToken',
                        'type' => 'oauth2',
                        'scheme' => 'bearer',
                        'description' => "Authorization code flow for 3rd party implementations. !! 'client_id' & 'client_secret' !! passed in the body for token fetching with grant_type code.",
                        'bearerFormat' => 'JWT',
                        'flows' => [
                            'authorizationCode' => [
                                'authorizationUrl' => "https://test.test/oauth/authorize",
                                'tokenUrl'=> "https://test.test/oauth/token",
                                'refreshUrl' => "https://test.test/oauth/token/refresh",
                                'scopes' => [
                                    'scope' => 'Explain scope',
                                ]
                            ]
                        ]
                    ]
                ]*/
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
        ],
    ],
];
