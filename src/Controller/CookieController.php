<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Form\CookieConsentType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CookieController extends AbstractController
{
    #[Route('/cookie/', name: 'app_cookie', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $consent = Consent::getBySession($request->getSession());

        $form = $this->createForm(CookieConsentType::class, null, [
            'cookies_granted' => $consent->getCookies() === ConsentType::Granted,
            'osm_granted' => $consent->getOsm() === ConsentType::Granted,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if ($data['cookies']) {
                $consent->setCookies(ConsentType::Granted);
                $consent->setOsm($data['osm'] ? ConsentType::Granted : ConsentType::Denied);
            } else {
                $consent->setCookies(ConsentType::Denied);
                $consent->setOsm(ConsentType::Denied);
            }

            $consent->save($request->getSession());

            $response = $this->redirectToRoute('app_cookie');

            if ($consent->getCookies() === ConsentType::Granted) {
                foreach ($consent->getHtmlCookies() as $cookie) {
                    $response->headers->setCookie($cookie);
                }
            } else {
                $response->headers->clearCookie(Consent::TYPE_COOKIES);
                $response->headers->clearCookie(Consent::TYPE_OSM);
            }

            return $response;
        }

        return $this->render('cookie/index.html.twig', [
            'form' => $form,
            'consent' => $consent,
        ]);
    }
}
