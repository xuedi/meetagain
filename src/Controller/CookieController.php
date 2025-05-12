<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CookieController extends AbstractController
{
    #[Route('/cookie/', name: 'app_cookie', methods: ['POST'])]
    public function index(Request $request): Response
    {
        $request->getSession()->set('consent_accepted', true);
        if ($request->get('osm_consent_checkbox') ?? 'off' === 'on') {
            $request->getSession()->set('consent_osm', true);
        } else {
            $request->getSession()->set('consent_osm', false);
        }

        return $this->render('cookie/index.html.twig');
    }
}
