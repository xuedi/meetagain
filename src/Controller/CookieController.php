<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CookieController extends AbstractController
{
    #[Route('/cookie/', name: 'app_cookie', methods: ['POST'])]
    public function index(): Response
    {
        /*
        * TODO: do some old school formBuilder stuff
                $consent = Consent::getBySession($request->getSession());
                $consent->setCookies(ConsentType::Granted);
                $consent->setOsm($request->get('osmConsent') === 'true' ? ConsentType::Granted : ConsentType::Denied);
                $consent->save($request->getSession());

                $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
                foreach ($consent->getHtmlCookies() as $cookie) {
           $response->headers->setCookie($cookie);
                }

                return $response;
        */
        return $this->render('cookie/index.html.twig');
    }
}
