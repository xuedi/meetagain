<?php declare(strict_types=1);

return [
    'paths' => [
        '/api/films' => [
            'get' => [
                'operationId' => 'listFilms',
                'tags' => ['filmclub'],
                'summary' => 'List films (most recent first)',
                'x-example' => "curl 'HOST/api/films?limit=20'",
                'parameters' => [
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
                                            'items' => ['$ref' => '#/components/schemas/FilmSummary'],
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
        '/api/films/{id}' => [
            'get' => [
                'operationId' => 'getFilm',
                'tags' => ['filmclub'],
                'summary' => 'Single film with most-recent closed vote tally (live tallies are hidden until close)',
                'x-example' => 'curl HOST/api/films/12',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/FilmDetail'],
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
            'FilmSummary' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'year' => ['type' => 'integer'],
                    'runtime' => ['type' => 'integer'],
                    'genres' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'FilmDetail' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/FilmSummary'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'latestVote' => [
                                'type' => ['object', 'null'],
                                'properties' => [
                                    'voteId' => ['type' => 'integer'],
                                    'eventId' => ['type' => 'integer'],
                                    'isOpen' => ['type' => 'boolean'],
                                    'closesAt' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                                    'totalBallots' => ['type' => 'integer'],
                                    'tallyForFilm' => ['type' => ['integer', 'null']],
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
            'name' => 'filmclub',
            'description' => 'Films and votes (plugin)',
        ],
    ],
];
