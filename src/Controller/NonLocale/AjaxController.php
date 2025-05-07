<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ajax')]
class AjaxController extends AbstractController
{
    #[Route('/', name: 'app_ajax', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('_non_locale/ajax.html.twig');
    }

    #[Route('/cookie', name: 'app_ajax_cookie_accept', methods: ['GET'])]
    public function acceptCookiesIndex(Request $request): Response
    {
        $request->getSession()->set('consent_accepted', true);
        if ($request->get('osmConsent') === 'true') {
            $request->getSession()->set('consent_osm', true);
        } else {
            $request->getSession()->set('consent_osm', false);
        }

        return new JsonResponse('Saved preferences', Response::HTTP_OK);
    }
}
