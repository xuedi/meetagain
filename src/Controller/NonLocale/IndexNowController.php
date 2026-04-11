<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Service\Seo\IndexNowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexNowController extends AbstractController
{
    public function __construct(
        private readonly IndexNowService $indexNowService,
    ) {}

    #[Route('/{key}.txt', name: 'app_indexnow', requirements: ['key' => '[a-zA-Z0-9\-]{8,128}'])]
    public function index(string $key): Response
    {
        $configuredKey = $this->indexNowService->getOrCreateKey();

        if ($key !== $configuredKey) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            $configuredKey,
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }
}
