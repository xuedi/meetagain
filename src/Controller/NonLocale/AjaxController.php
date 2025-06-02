<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Entity\Session\Consent;
use App\Entity\Session\ConsentType;
use App\Service\CaptchaService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AjaxController extends AbstractController
{
    #[Route('/ajax/', name: 'app_ajax', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('_non_locale/ajax.html.twig');
    }

    #[Route('/ajax/cookie/accept', name: 'app_ajax_cookie_accept', methods: ['GET'])]
    public function acceptCookiesIndex(Request $request): Response
    {
        $consent = Consent::getBySession($request->getSession());
        $consent->setCookies(ConsentType::Granted);
        $consent->setOsm($request->get('osmConsent') === 'true' ? ConsentType::Granted : ConsentType::Denied);
        $consent->save($request->getSession());

        $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        
        return $response;
    }

    #[Route('/ajax/cookie/deny', name: 'app_ajax_cookie_deny', methods: ['GET'])]
    public function denyCookiesIndex(Request $request): Response
    {
        $consent = Consent::getBySession($request->getSession());
        $consent->setCookies(ConsentType::Denied);
        $consent->setOsm(ConsentType::Denied);
        $consent->save($request->getSession());

        $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
        $response->headers->clearCookie(Consent::TYPE_COOKIES);
        $response->headers->clearCookie(Consent::TYPE_OSM);

        return $response;
    }

    #[Route('/ajax/get-captcha-count', name: 'app_ajax_get_captcha_count', methods: ['GET'])]
    public function getCaptchaCountIndex(Request $request, CaptchaService $captchaService): Response
    {
        $captchaService->setSession($request->getSession());
        return new JsonResponse([
            'count' => $captchaService->getRefreshCount(),
            'next' => $captchaService->getRefreshTime(),
        ]);
    }
}
