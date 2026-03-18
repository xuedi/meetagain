<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    #[Route('/api/', name: 'app_api')]
    public function index(): Response
    {
        return $this->render('_non_locale/api.html.twig', [
            'sections' => $this->getEndpointDefinitions(),
        ]);
    }

    /**
     * @return array<int, array{title: string, badge: string|null, endpoints: array<int, array{method: string, apiPath: string, description: string, example: string, route: string|null}>}>
     */
    private function getEndpointDefinitions(): array
    {
        return [
            [
                'title' => 'Public Endpoints',
                'badge' => null,
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/status',
                        'description' => 'Service health check',
                        'example' => 'curl HOST/api/status',
                        'route' => null,
                    ],
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/translations',
                        'description' => 'Translation strings',
                        'example' => 'curl HOST/api/translations',
                        'route' => 'app_api_translations',
                    ],
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/glossary',
                        'description' => 'Glossary data',
                        'example' => 'curl HOST/api/glossary',
                        'route' => 'app_api_glossary',
                    ],
                ],
            ],
            [
                'title' => 'Authentication',
                'badge' => null,
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'apiPath' => '/api/auth/token',
                        'route' => null,
                        'description' => 'Generate Bearer token (email + password)',
                        'example' => "TOKEN=\$(curl -s -X POST HOST/api/auth/token \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"email\":\"admin@example.com\",\"password\":\"...\"}' | jq -r .token)",
                    ],
                    [
                        'method' => 'DELETE',
                        'apiPath' => '/api/auth/token',
                        'route' => null,
                        'description' => 'Revoke current token (requires Bearer)',
                        'example' => "curl -s -X DELETE HOST/api/auth/token \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                    ],
                ],
            ],
            [
                'title' => 'CMS Pages',
                'badge' => 'Token required',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/cms/',
                        'route' => null,
                        'description' => 'List all CMS pages',
                        'example' => "curl HOST/api/cms/ \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                    ],
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/cms/{id}',
                        'route' => null,
                        'description' => 'Get full page with blocks',
                        'example' => "curl HOST/api/cms/1 \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                    ],
                    [
                        'method' => 'POST',
                        'apiPath' => '/api/cms/',
                        'route' => null,
                        'description' => 'Create page — body: {slug, titles, linkNames}',
                        'example' => "curl -X POST HOST/api/cms/ \\\n  -H \"Authorization: Bearer \$TOKEN\" \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"slug\":\"my-page\",\"titles\":{\"en\":\"My Page\",\"de\":\"Meine Seite\"},\"linkNames\":{\"en\":\"My Page\",\"de\":\"Meine Seite\"}}'",
                    ],
                    [
                        'method' => 'PUT',
                        'apiPath' => '/api/cms/{id}',
                        'route' => null,
                        'description' => 'Update metadata — slug, published, titles, linkNames',
                        'example' => "curl -X PUT HOST/api/cms/1 \\\n  -H \"Authorization: Bearer \$TOKEN\" \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"published\":true,\"titles\":{\"en\":\"Updated Title\"}}'",
                    ],
                    [
                        'method' => 'DELETE',
                        'apiPath' => '/api/cms/{id}',
                        'route' => null,
                        'description' => 'Delete page (not allowed if locked)',
                        'example' => "curl -X DELETE HOST/api/cms/1 \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                    ],
                ],
            ],
            [
                'title' => 'System Logs',
                'badge' => 'Token required',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'apiPath' => '/api/logs',
                        'description' => 'Read recent application log entries',
                        'example' => "curl HOST/api/logs?limit=50&level=WARNING \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                        'route' => 'app_api_logs',
                    ],
                ],
            ],
            [
                'title' => 'CMS Blocks',
                'badge' => 'Token required',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'apiPath' => '/api/cms/{id}/blocks',
                        'route' => null,
                        'description' => 'Add block — body: {language, type, priority, json}',
                        'example' => "curl -X POST HOST/api/cms/1/blocks \\\n  -H \"Authorization: Bearer \$TOKEN\" \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"language\":\"en\",\"type\":1,\"priority\":1,\"json\":{\"text\":\"Hello World\"}}'",
                    ],
                    [
                        'method' => 'PUT',
                        'apiPath' => '/api/cms/{id}/blocks/{blockId}',
                        'route' => null,
                        'description' => 'Update block json and/or priority',
                        'example' => "curl -X PUT HOST/api/cms/1/blocks/5 \\\n  -H \"Authorization: Bearer \$TOKEN\" \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"priority\":2,\"json\":{\"text\":\"Updated content\"}}'",
                    ],
                    [
                        'method' => 'DELETE',
                        'apiPath' => '/api/cms/{id}/blocks/{blockId}',
                        'route' => null,
                        'description' => 'Delete block',
                        'example' => "curl -X DELETE HOST/api/cms/1/blocks/5 \\\n  -H \"Authorization: Bearer \$TOKEN\"",
                    ],
                ],
            ],
        ];
    }

    #[Route('/api/glossary', name: 'app_api_glossary', methods: ['GET'])]
    public function glossaryIndex(): Response
    {
        return new JsonResponse('glossary');
    }

    #[Route('/api/status', name: 'app_api_status', methods: ['GET'])]
    public function statusIndex(): Response
    {
        return new JsonResponse('OK');
    }

    #[Route('/api/translations', name: 'app_api_translations', methods: ['GET'])]
    public function translationsIndex(): Response
    {
        return new JsonResponse('translations');
    }
}
