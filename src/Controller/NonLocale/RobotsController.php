<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Service\Config\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RobotsController extends AbstractController
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[Route('/robots.txt', name: 'app_robots')]
    public function index(Request $request): Response
    {
        $sitemapUrl = $request->getSchemeAndHttpHost() . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /api/v1/',
        ];

        if ($this->configService->isTdmReservationEnabled()) {
            $lines[] = 'Content-Usage: train-ai=n';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $sitemapUrl;

        return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}
