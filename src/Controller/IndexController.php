<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\Cms\CmsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    public function __construct(
        private readonly CmsService $cms,
    ) {}

    #[Route('/', name: 'app_default')]
    public function index(Request $request): Response
    {
        return $this->cms->handle($request->getLocale(), 'index', $this->getResponse());
    }
}
