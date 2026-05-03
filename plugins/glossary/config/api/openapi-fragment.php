<?php declare(strict_types=1);

return [
    'paths' => [
        '/api/glossary' => [
            'get' => [
                'operationId' => 'listGlossary',
                'tags' => ['glossary'],
                'summary' => 'List approved glossary entries',
                'x-example' => "curl 'HOST/api/glossary?limit=20'",
                'parameters' => [
                    ['$ref' => '#/components/parameters/Locale'],
                    [
                        'name' => 'category',
                        'in' => 'query',
                        'description' => 'Filter by category slug (greeting, swearing, flirting, slang, abbreviation, regular, idioms).',
                        'schema' => ['type' => 'string'],
                    ],
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
                                            'items' => ['$ref' => '#/components/schemas/GlossaryEntry'],
                                        ],
                                        'total' => ['type' => 'integer'],
                                        'limit' => ['type' => 'integer'],
                                        'offset' => ['type' => 'integer'],
                                    ],
                                ],
                                'example' => [
                                    'items' => [
                                        [
                                            'id' => 12,
                                            'phrase' => '你好',
                                            'pinyin' => 'nǐ hǎo',
                                            'explanation' => 'A common greeting meaning "hello".',
                                            'categorySlug' => 'greeting',
                                            'createdAt' => '2026-04-12T10:14:00+00:00',
                                        ],
                                    ],
                                    'total' => 1,
                                    'limit' => 50,
                                    'offset' => 0,
                                ],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Glossary plugin not active for this domain',
                        'content' => [
                            'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
                        ],
                    ],
                ],
            ],
        ],
        '/api/glossary/{id}' => [
            'get' => [
                'operationId' => 'getGlossaryEntry',
                'tags' => ['glossary'],
                'summary' => 'Single glossary entry by id',
                'x-example' => 'curl HOST/api/glossary/12',
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
                                'schema' => ['$ref' => '#/components/schemas/GlossaryEntry'],
                            ],
                        ],
                    ],
                    '404' => [
                        'description' => 'Not found',
                        'content' => [
                            'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'components' => [
        'schemas' => [
            'GlossaryEntry' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'phrase' => ['type' => 'string'],
                    'pinyin' => ['type' => ['string', 'null']],
                    'explanation' => ['type' => 'string'],
                    'categorySlug' => ['type' => 'string'],
                    'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ],
    ],
    'tags' => [
        [
            'name' => 'glossary',
            'description' => 'Glossary entries (plugin)',
        ],
    ],
];
