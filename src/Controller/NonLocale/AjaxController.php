<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use App\Service\Config\LocaleCookieService;
use App\Service\Member\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AjaxController extends AbstractController
{
    public function __construct(
        private readonly LocaleCookieService $localeCookieService,
        private readonly ConsentService $consentService,
    ) {}

    #[Route('/ajax/', name: 'app_ajax', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('_non_locale/ajax.html.twig');
    }

    #[Route('/ajax/cookie/accept', name: 'app_ajax_cookie_accept', methods: ['POST'])]
    public function acceptCookiesIndex(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cookie_accept', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $consent = Consent::getBySession($request->getSession());
        $consent->setCookies(ConsentType::Granted);
        $consent->setOsm($request->request->get('osmConsent') === 'true' ? ConsentType::Granted : ConsentType::Denied);
        $consent->setExternalMedia($request->request->get('externalMediaConsent') === 'true' ? ConsentType::Granted : ConsentType::Denied);
        $consent->save($request->getSession());

        $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->setCookie($this->localeCookieService->createCookie($request->getLocale()));

        return $response;
    }

    #[Route('/ajax/consent/external-media', name: 'app_ajax_consent_external_media', methods: ['POST'])]
    public function grantExternalMedia(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('external_media_consent', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
        $this->consentService->setShowExternalMedia(true, $response);

        return $response;
    }

    #[Route('/ajax/cookie/deny', name: 'app_ajax_cookie_deny', methods: ['POST'])]
    public function denyCookiesIndex(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cookie_deny', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $consent = Consent::getBySession($request->getSession());
        $consent->setCookies(ConsentType::Denied);
        $consent->setOsm(ConsentType::Denied);
        $consent->setExternalMedia(ConsentType::Denied);
        $consent->save($request->getSession());

        $response = new JsonResponse('Saved preferences', Response::HTTP_OK);
        foreach ($consent->getHtmlCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
