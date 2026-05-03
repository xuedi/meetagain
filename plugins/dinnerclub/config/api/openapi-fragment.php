<?php declare(strict_types=1);

return [
    'paths' => [
        '/api/dinners' => [
            'get' => [
                'operationId' => 'listDinners',
                'tags' => ['dinnerclub'],
                'summary' => 'List dinners (most recent first)',
                'x-example' => "curl 'HOST/api/dinners?limit=10'",
                'parameters' => [
                    [
                        'name' => 'limit',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    ],
                    [
                        'name' => 'offset',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'items' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/DinnerSummary'],
                                        ],
                                        'total' => ['type' => 'integer'],
                                        'limit' => ['type' => 'integer'],
                                        'offset' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Plugin not active',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                ],
            ],
        ],
        '/api/dinners/{id}' => [
            'get' => [
                'operationId' => 'getDinner',
                'tags' => ['dinnerclub'],
                'summary' => 'Single dinner with courses and dishes',
                'x-example' => 'curl HOST/api/dinners/42',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                    ],
                    ['$ref' => '#/components/parameters/Locale'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/DinnerDetail'],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Not found',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                ],
            ],
        ],
        '/api/dishes' => [
            'get' => [
                'operationId' => 'listDishes',
                'tags' => ['dinnerclub'],
                'summary' => 'List approved dishes',
                'x-example' => "curl 'HOST/api/dishes?locale=en&limit=20'",
                'parameters' => [
                    ['$ref' => '#/components/parameters/Locale'],
                    [
                        'name' => 'limit',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
                    ],
                    [
                        'name' => 'offset',
                        'in' => 'query',
                        'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'items' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/DishSummary'],
                                        ],
                                        'total' => ['type' => 'integer'],
                                        'limit' => ['type' => 'integer'],
                                        'offset' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/dishes/{id}' => [
            'get' => [
                'operationId' => 'getDish',
                'tags' => ['dinnerclub'],
                'summary' => 'Dish detail with translations',
                'x-example' => 'curl HOST/api/dishes/12',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                    ],
                    ['$ref' => '#/components/parameters/Locale'],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/DishDetail'],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Not found',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                    ],
                ],
            ],
        ],
    ],
    'components' => [
        'schemas' => [
            'DinnerSummary' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'eventId' => ['type' => ['integer', 'null']],
                    'eventTitle' => ['type' => ['string', 'null']],
                    'startsAt' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'pricePerPerson' => ['type' => ['number', 'null']],
                    'reservationName' => ['type' => ['string', 'null']],
                    'courseCount' => ['type' => 'integer'],
                ],
            ],
            'DinnerDetail' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/DinnerSummary'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'courses' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'name' => ['type' => 'string'],
                                        'sortOrder' => ['type' => 'integer'],
                                        'items' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'sortOrder' => ['type' => 'integer'],
                                                    'isPrimary' => ['type' => 'boolean'],
                                                    'dish' => ['$ref' => '#/components/schemas/DishSummary'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'DishSummary' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'phonetic' => ['type' => ['string', 'null']],
                    'origin' => ['type' => ['string', 'null']],
                    'previewImageUrl' => ['type' => ['string', 'null'], 'format' => 'uri'],
                    'likes' => ['type' => 'integer'],
                ],
            ],
            'DishDetail' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/DishSummary'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'translations' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => ['string', 'null']],
                                        'description' => ['type' => ['string', 'null']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'tags' => [
        [
            'name' => 'dinnerclub',
            'description' => 'Dinners and dishes (plugin)',
        ],
    ],
];
