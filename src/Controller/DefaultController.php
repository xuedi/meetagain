<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\CmsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class DefaultController extends AbstractController
{
    #[Route('/{page}', name: 'app_catch_all', requirements: ['page' => Requirement::CATCH_ALL], priority: -20)]
    public function catchAll(Request $request, CmsService $cms, string $page): Response
    {
        return $cms->handle($request->getLocale(), $page);
    }
}
