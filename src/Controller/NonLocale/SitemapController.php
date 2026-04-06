<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Service\Event\SitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class SitemapController extends AbstractController
{
    public function __construct(
        private readonly SitemapService $sitemapService,
    ) {}

    #[Route('/sitemap.xml', name: 'app_sitemap')]
    public function index(Request $request): Response
    {
        return $this->sitemapService->getContent($request->getHost());
    }
}
